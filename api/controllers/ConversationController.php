<?php
/**
 * ============================================
 * Controller: Conversation (Histórico de conversas)
 * ============================================
 *
 * Rotas (todas exigem autenticação; o usuário vem SEMPRE do token JWT
 * validado pelo AuthMiddleware, nunca de um campo enviado pelo navegador):
 *
 *   GET    /api/conversations                  listar
 *   POST   /api/conversations                  criar
 *   GET    /api/conversations/:id              abrir (com mensagens)
 *   PUT    /api/conversations/:id              renomear
 *   DELETE /api/conversations/:id              excluir
 *   POST   /api/conversations/:id/messages     enviar mensagem + resposta da IA
 */

require_once __DIR__ . '/../models/Conversation.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Goal.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../middleware/RateLimiter.php';
require_once __DIR__ . '/../services/AIService.php';

class ConversationController {
    private $db;
    private $conversationModel;
    
    const MAX_CONTENT = 4000;
    const MIN_CONTENT = 1;
    const HISTORY_MESSAGES = 12;  // últimas mensagens enviadas à IA como memória
    const HISTORY_ITEM_CHARS = 600; // limite de cada item do histórico

    private function greetingReply($content) {
        if (!preg_match('/^(?:oi+|ol[aá]+|oie+|bom\s+dia|boa\s+tarde|boa\s+noite|e\s*a[ií]|hey|hello)[!?.\s]*$/iu', trim($content))) return null;
        return 'Olá! Sou o Próximo Passo. Posso analisar uma decisão considerando suas finanças, objetivos e tempo disponível. Também posso criar ou concluir objetivos e tarefas e atualizar sua renda, gastos, reserva ou horas livres quando você pedir. Por exemplo: “Crie um objetivo para juntar R$ 3.000” ou “Minha renda mensal agora é R$ 4.000”. Como posso ajudar?';
    }

    private function applyItemAction($userId, $conversationId, $userMessage, $action) {
        $type = $action['action'] ?? 'none';
        if (!in_array($type, ['create_goal', 'create_goal_monthly_plan', 'set_plan_funding_source', 'complete_goal', 'create_task', 'complete_task', 'add_goal_note', 'clarify'], true)) return null;
        if ($type === 'create_goal_monthly_plan') (new Task($this->db))->ensureMonthlyPlansSchema();
        if ($type === 'set_plan_funding_source') (new Task($this->db))->ensureMonthlyPlansSchema();
        if ($type === 'complete_task') (new Task($this->db))->ensureCompletionSchema();
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM mensagens WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $userMessage['id']]);
            $existing = $this->conversationModel->findReply($userMessage['id']);
            if ($existing) { $this->db->commit(); return $existing; }
            if ($type === 'clarify') {
                $text = 'Preciso de mais detalhes. ' . trim((string) ($action['question'] ?? 'Diga o nome exato ou a data necessária.'));
            } elseif ($type === 'create_goal' || $type === 'create_goal_monthly_plan' || $type === 'create_task') {
                $name = trim(strip_tags((string) ($action['name'] ?? '')));
                if (mb_strlen($name) < 3 || mb_strlen($name) > 200) throw new InvalidArgumentException('Informe um nome claro para o objetivo ou tarefa.');
                if ($type === 'create_goal' || $type === 'create_goal_monthly_plan') {
                    if ($type === 'create_goal_monthly_plan') {
                        $amount = $action['monthly_amount'] ?? null;
                        $day = filter_var($action['due_day'] ?? null, FILTER_VALIDATE_INT);
                        if (!is_numeric($amount) || !is_finite((float) $amount) || (float) $amount <= 0 || (float) $amount > 99999999.99 || !$day || $day < 1 || $day > 31) {
                            throw new InvalidArgumentException('Para criar o plano mensal, diga quanto pretende aportar e em que dia do mês (de 1 a 31).');
                        }
                        $fundingSource = $action['funding_source'] ?? 'indefinida';
                        if (!in_array($fundingSource, ['sobra', 'reserva', 'outro'], true)) {
                            throw new InvalidArgumentException('Preciso de mais detalhes. O aporte mensal sairá da sobra após os gastos, da reserva já guardada ou de outra fonte?');
                        }
                        $targetId = filter_var($action['target_id'] ?? null, FILTER_VALIDATE_INT);
                        if ($targetId) {
                            $target = $this->db->prepare('SELECT id, nome FROM objetivos WHERE id = :id AND usuario_id = :uid AND cancelado_em IS NULL AND progresso < 100 FOR UPDATE');
                            $target->execute([':id' => $targetId, ':uid' => $userId]);
                            $selected = $target->fetch();
                            if (!$selected) throw new InvalidArgumentException('Não encontrei o objetivo indicado para o aporte.');
                            $name = $selected['nome'];
                        } elseif (preg_match('/^(aporte|depósito|poupança)\s+mensal$/iu', $name)) {
                            throw new InvalidArgumentException('Qual objetivo deve receber esse aporte mensal?');
                        }
                    }
                    $same = $selected ?? null;
                    if (!$same) {
                        $check = $this->db->prepare('SELECT id, progresso FROM objetivos WHERE usuario_id = :uid AND cancelado_em IS NULL AND LOWER(nome) = LOWER(:name) LIMIT 1');
                        $check->execute([':uid' => $userId, ':name' => $name]);
                        $same = $check->fetch();
                    }
                    if ($type === 'create_goal_monthly_plan' && !$same) {
                        $active = $this->db->prepare('SELECT COUNT(*) FROM objetivos WHERE usuario_id = :uid AND cancelado_em IS NULL AND progresso < 100');
                        $active->execute([':uid' => $userId]);
                        if ((int) $active->fetchColumn() > 0) throw new InvalidArgumentException('Preciso de mais detalhes. Qual dos seus objetivos deve receber o aporte mensal? Diga o nome do objetivo.');
                    }
                    if ($same && (int) $same['progresso'] < 100) {
                        $id = $same['id'];
                        $text = "Esse objetivo já está ativo: {$name}.";
                    } else {
                        $id = (new Goal($this->db))->create(['user_id' => $userId, 'name' => $name, 'description' => '', 'progress' => 0, 'deadline' => null]);
                        $text = "Objetivo ativo criado: {$name}.";
                    }
                    if ($type === 'create_goal_monthly_plan') {
                        $amountLabel = 'R$ ' . number_format((float) $amount, 2, ',', '.');
                        $taskName = "Aportar {$amountLabel} para {$name}";
                        if (mb_strlen($taskName) > 200) $taskName = mb_substr($taskName, 0, 200);
                        $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
                        $due = Task::monthlyDueDate((int) $today->format('Y'), (int) $today->format('m'), $day);
                        $start = new DateTimeImmutable($due);
                        if ($start < $today) $start = $today->modify('first day of next month');
                        $taskModel = new Task($this->db);
                        $targetAmount = $action['target_amount'] ?? null;
                        if (preg_match('/R\$\s*([\d.]+(?:,\d{1,2})?)/u', $name, $match)) {
                            $targetAmount = (float) str_replace(',', '.', str_replace('.', '', $match[1]));
                        }
                        if (!is_numeric($targetAmount) || (float) $targetAmount <= 0 || (float) $targetAmount > 99999999.99) $targetAmount = null;
                        if (!$taskModel->saveMonthlyPlan($userId, $id, $taskName, (float) $amount, $day, $start->format('Y-m-01'), $targetAmount, $fundingSource)) throw new RuntimeException('Falha ao salvar plano mensal');
                        if ($start->format('Y-m') === $today->format('Y-m')) $taskModel->materializeMonthlyPlans($userId);
                        $text .= " Plano mensal de aporte de {$amountLabel} vinculado ao objetivo, todo dia {$day} (ou no último dia dos meses mais curtos). A tarefa aparece no mês correspondente. Primeiro vencimento: " . $start->format('d/m/Y') . '. Ao concluir um aporte, registre o novo saldo da reserva para acompanhar o progresso.';
                    } elseif (preg_match('/reserva|juntar|economizar|poupar/iu', $name)) {
                        $text .= ' Quanto pretende aportar por mês para esse objetivo?';
                    }
                } else {
                    $due = (string) ($action['due_date'] ?? '');
                    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $due);
                    if (!$date || $date->format('Y-m-d') !== $due) throw new InvalidArgumentException('Qual é a data de vencimento? Repita o pedido completo com a data.');
                    $id = (new Task($this->db))->create(['user_id' => $userId, 'title' => $name, 'priority' => 'media', 'due_date' => $due, 'estimated_minutes' => 30, 'goal_id' => null, 'status' => 'pendente']);
                    $text = "Tarefa criada: {$name}, vencimento {$due}.";
                }
                if (!$id) throw new RuntimeException('Falha ao criar item');
            } else {
                $id = filter_var($action['target_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id || $id < 1) throw new InvalidArgumentException('Não identifiquei qual item concluir. Diga o nome exato.');
                if ($type === 'set_plan_funding_source') {
                    $fundingSource = $action['funding_source'] ?? 'indefinida';
                    if (!in_array($fundingSource, ['sobra', 'reserva', 'outro'], true)) throw new InvalidArgumentException('O aporte sairá da sobra mensal, da reserva ou de outra fonte?');
                    $stmt = $this->db->prepare('UPDATE planos_mensais p JOIN objetivos o ON o.id = p.objetivo_id SET p.origem_recursos = :source WHERE p.usuario_id = :uid AND p.objetivo_id = :goal AND o.usuario_id = :uid_goal AND o.cancelado_em IS NULL');
                    $stmt->execute([':source' => $fundingSource, ':uid' => $userId, ':goal' => $id, ':uid_goal' => $userId]);
                    if (!$stmt->rowCount()) {
                        $check = $this->db->prepare('SELECT id FROM planos_mensais WHERE usuario_id = :uid AND objetivo_id = :goal LIMIT 1');
                        $check->execute([':uid' => $userId, ':goal' => $id]);
                        if (!$check->fetch()) throw new InvalidArgumentException('Não encontrei um plano mensal ativo nesse objetivo.');
                    }
                    $label = ['sobra' => 'sobra mensal', 'reserva' => 'reserva', 'outro' => 'outra fonte'][$fundingSource];
                    $text = "Origem do aporte mensal atualizada para {$label}.";
                } elseif ($type === 'add_goal_note') {
                    $note = trim(strip_tags((string) ($action['note'] ?? '')));
                    if (mb_strlen($note) < 10 || mb_strlen($note) > 1800) throw new InvalidArgumentException('Não identifiquei um plano claro para registrar. Descreva o que deseja adicionar.');
                    $stmt = $this->db->prepare('SELECT nome, descricao FROM objetivos WHERE id = :id AND usuario_id = :uid AND cancelado_em IS NULL FOR UPDATE');
                    $stmt->execute([':id' => $id, ':uid' => $userId]);
                    $item = $stmt->fetch();
                    if (!$item) throw new InvalidArgumentException('Objetivo não encontrado na sua conta.');
                    $description = trim((string) $item['descricao']);
                    if (mb_strlen($description . "\n" . $note) > 5000) throw new InvalidArgumentException('A descrição do objetivo está cheia. Resuma o plano antes de adicionar.');
                    $stmt = $this->db->prepare('UPDATE objetivos SET descricao = :description WHERE id = :id AND usuario_id = :uid');
                    $stmt->execute([':description' => trim($description . "\n" . $note), ':id' => $id, ':uid' => $userId]);
                    $text = "Plano adicionado ao objetivo {$item['nome']}: {$note}";
                } elseif ($type === 'complete_goal') {
                    $stmt = $this->db->prepare('SELECT nome, progresso FROM objetivos WHERE id = :id AND usuario_id = :uid AND cancelado_em IS NULL FOR UPDATE');
                    $stmt->execute([':id' => $id, ':uid' => $userId]);
                    $item = $stmt->fetch();
                    if (!$item) throw new InvalidArgumentException('Objetivo não encontrado na sua conta.');
                    (new Goal($this->db))->updateProgress($id, 100);
                    $text = "Objetivo concluído: {$item['nome']}.";
                } else {
                    $stmt = $this->db->prepare('SELECT titulo, status, objetivo_id FROM tarefas WHERE id = :id AND usuario_id = :uid AND cancelado_em IS NULL FOR UPDATE');
                    $stmt->execute([':id' => $id, ':uid' => $userId]);
                    $item = $stmt->fetch();
                    if (!$item) throw new InvalidArgumentException('Tarefa não encontrada na sua conta.');
                    $stmt = $this->db->prepare("UPDATE tarefas SET status = 'concluida', concluida_em = COALESCE(concluida_em, NOW()) WHERE id = :id AND usuario_id = :uid AND cancelado_em IS NULL");
                    $stmt->execute([':id' => $id, ':uid' => $userId]);
                    (new Goal($this->db))->syncProgressFromTasks($userId, $item['objetivo_id']);
                    $text = "Tarefa concluída: {$item['titulo']}.";
                }
            }
            $reply = $this->conversationModel->addMessage($conversationId, 'ia', 'texto', $text, null, $userMessage['id']);
            $this->conversationModel->touch($conversationId);
            $this->db->commit();
            return $reply;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function applyProfileUpdates($userId, $conversationId, $userMessage, $updates) {
        $fields = [
            'monthly_income' => ['column' => 'renda_mensal', 'label' => 'Renda mensal', 'max' => 99999999.99],
            'monthly_expenses' => ['column' => 'gastos_mensais', 'label' => 'Gastos mensais', 'max' => 99999999.99],
            'savings' => ['column' => 'reserva', 'label' => 'Reserva', 'max' => 99999999.99],
            'weekly_free_hours' => ['column' => 'horas_livres_semana', 'label' => 'Disponibilidade semanal', 'max' => 168]
        ];
        $this->db->beginTransaction();
        try {
            // A trava impede aplicar duas vezes um reenvio simultâneo.
            $lock = $this->db->prepare('SELECT id FROM mensagens WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $userMessage['id']]);
            $existing = $this->conversationModel->findReply($userMessage['id']);
            if ($existing) { $this->db->commit(); return $existing; }
            $stmt = $this->db->prepare('SELECT renda_mensal, gastos_mensais, reserva, horas_livres_semana FROM usuarios WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $userId]);
            $current = $stmt->fetch();
            $changes = [];
            if (count($updates) > 4) throw new InvalidArgumentException('Peça até quatro alterações por mensagem.');
            foreach ($updates as $update) {
                $field = $update['field'] ?? '';
                $operation = $update['operation'] ?? '';
                $value = $update['value'] ?? null;
                if (!isset($fields[$field]) || !in_array($operation, ['set', 'add', 'subtract'], true) ||
                    !is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
                    throw new InvalidArgumentException('Não consegui validar os valores pedidos. Informe o campo e o valor novamente.');
                }
                $spec = $fields[$field];
                $column = $spec['column'];
                $before = (float) $current[$column];
                $after = $operation === 'set' ? (float) $value : $before + ($operation === 'add' ? 1 : -1) * (float) $value;
                if ($after < 0 || $after > $spec['max'] || ($field === 'weekly_free_hours' && floor($after) !== $after)) {
                    throw new InvalidArgumentException('O valor resultante está fora do limite permitido. Nenhum dado foi alterado.');
                }
                $current[$column] = $after;
                $changes[$column] = $spec['label'] . ': ' . ($field === 'weekly_free_hours' ? $after . ' horas/semana' : 'R$ ' . number_format($after, 2, ',', '.'));
            }
            if (!$changes) throw new InvalidArgumentException('Nenhuma alteração válida encontrada.');
            foreach ($current as $column => $value) {
                if (!in_array($column, ['renda_mensal', 'gastos_mensais', 'reserva', 'horas_livres_semana'], true)) continue;
                $sql = $this->db->prepare("UPDATE usuarios SET {$column} = :value WHERE id = :id");
                $sql->execute([':value' => $value, ':id' => $userId]);
            }
            $reply = $this->conversationModel->addMessage($conversationId, 'ia', 'texto', 'Atualizado: ' . implode('; ', $changes) . '.', null, $userMessage['id']);
            $this->conversationModel->touch($conversationId);
            $this->db->commit();
            return $reply;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function __construct($db) {
        $this->db = $db;
        $this->conversationModel = new Conversation($db);
        $this->conversationModel->ensureSchema();
    }
    
    // ------------------------------------------------
    // Formatação
    // ------------------------------------------------
    
    private function iso($datetime) {
        $ts = $datetime ? strtotime($datetime) : false;
        return $ts ? date('c', $ts) : null;
    }
    
    private function formatConversation($c) {
        return [
            'id' => (int) $c['id'],
            'titulo' => $c['titulo'],
            'criado_em' => $c['criado_em'],
            'atualizado_em' => $c['atualizado_em'],
            'criado_em_iso' => $this->iso($c['criado_em']),
            'atualizado_em_iso' => $this->iso($c['atualizado_em'])
        ];
    }
    
    private function formatMessage($m) {
        $out = [
            'id' => (int) $m['id'],
            'conversa_id' => (int) $m['conversa_id'],
            'remetente' => $m['remetente'],
            'formato' => $m['formato'],
            'conteudo' => $m['conteudo'],
            'client_id' => $m['client_id'],
            'resposta_de' => $m['resposta_de'] !== null ? (int) $m['resposta_de'] : null,
            'criado_em' => $m['criado_em'],
            'criado_em_iso' => $this->iso($m['criado_em'])
        ];
        if ($m['formato'] === 'analise') {
            $decoded = json_decode($m['conteudo'], true);
            $out['analise'] = is_array($decoded) ? $decoded : null;
        }
        return $out;
    }
    
    /** Conversa do usuário ou 404 (não revela se existe para outro usuário). */
    private function requireOwned($userId, $id) {
        $conversation = $this->conversationModel->findOwned((int) $id, $userId);
        if (!$conversation) {
            errorResponse('Conversa não encontrada', 404);
        }
        return $conversation;
    }
    
    private function cleanTitle($raw) {
        $title = trim(strip_tags((string) $raw));
        $title = preg_replace('/\s+/u', ' ', $title);
        $length = mb_strlen($title, 'UTF-8');
        if ($length < 1) {
            errorResponse('O título não pode ficar vazio', 400);
        }
        if ($length > 150) {
            errorResponse('O título deve ter no máximo 150 caracteres', 400);
        }
        return $title;
    }
    
    private function autoTitle($content) {
        $flat = trim(preg_replace('/\s+/u', ' ', $content));
        if (mb_strlen($flat, 'UTF-8') <= 50) return $flat;
        return rtrim(mb_substr($flat, 0, 50, 'UTF-8')) . '…';
    }
    
    // ------------------------------------------------
    // Memória da conversa (contexto enviado à IA)
    // ------------------------------------------------
    
    private function shorten($text, $max) {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text, 'UTF-8') <= $max) return $text;
        return rtrim(mb_substr($text, 0, $max, 'UTF-8')) . '…';
    }
    
    /**
     * Monta o histórico (somente desta conversa e anterior à mensagem atual).
     * Mensagens da IA entram resumidas: resumo + títulos das alternativas.
     */
    private function buildHistory($conversationId, $userId, $beforeMessageId) {
        $rows = $this->conversationModel->recentBefore(
            $conversationId, $userId, $beforeMessageId, self::HISTORY_MESSAGES
        );
        
        $history = [];
        foreach ($rows as $row) {
            if ($row['remetente'] === 'usuario') {
                $text = $this->shorten($row['conteudo'], self::HISTORY_ITEM_CHARS);
            } elseif ($row['formato'] === 'texto') {
                $text = $this->shorten($row['conteudo'], self::HISTORY_ITEM_CHARS);
            } else {
                $analysis = $row['formato'] === 'analise' ? json_decode($row['conteudo'], true) : null;
                if (!is_array($analysis)) continue;
                
                $text = trim((string) ($analysis['summary'] ?? ''));
                if (!empty($analysis['alternatives']) && is_array($analysis['alternatives'])) {
                    $titles = array_filter(array_map(function ($alt) {
                        return is_array($alt) ? trim((string) ($alt['title'] ?? '')) : '';
                    }, $analysis['alternatives']));
                    if ($titles) $text .= ' Alternativas apresentadas: ' . implode('; ', $titles) . '.';
                }
                $text = $this->shorten($text, self::HISTORY_ITEM_CHARS + 100);
            }
            
            if ($text !== '') {
                $history[] = ['role' => $row['remetente'], 'text' => $text];
            }
        }
        return $history;
    }

    private function buildActionHistory($conversationId, $userId, $beforeMessageId) {
        $rows = $this->conversationModel->recentBefore($conversationId, $userId, $beforeMessageId, 6);
        $history = [];
        foreach ($rows as $row) {
            if ($row['remetente'] === 'usuario' || $row['formato'] === 'texto') {
                $text = $row['conteudo'];
            } else {
                $analysis = json_decode($row['conteudo'], true);
                $text = is_array($analysis) ? json_encode([
                    'summary' => $analysis['summary'] ?? '',
                    'alternatives' => $analysis['alternatives'] ?? []
                ], JSON_UNESCAPED_UNICODE) : '';
            }
            $text = $this->shorten($text, 2200);
            if ($text !== '') $history[] = ['role' => $row['remetente'], 'text' => $text];
        }
        return $history;
    }

    private function awaitingItemClarification($conversationId, $userId, $beforeMessageId) {
        $rows = $this->conversationModel->recentBefore($conversationId, $userId, $beforeMessageId, 2);
        $last = end($rows);
        return $last && $last['remetente'] === 'ia' && $last['formato'] === 'texto' &&
            (preg_match('/^Preciso de mais detalhes\./u', $last['conteudo']) ||
             preg_match('/Quanto pretende aportar por mês|em que dia do mês pretende aportar/iu', $last['conteudo']));
    }

    /** Usa apenas o objetivo confirmado na resposta imediatamente anterior. */
    private function reserveValueFromRecentGoal($conversationId, $userId, $beforeMessageId) {
        $rows = $this->conversationModel->recentBefore($conversationId, $userId, $beforeMessageId, 1);
        $last = $rows[0] ?? null;
        if (!$last || $last['remetente'] !== 'ia' || $last['formato'] !== 'texto' ||
            !preg_match('/^Objetivo concluído: (.+)\.$/u', trim($last['conteudo']), $match)) return null;
        $name = $match[1];
        if (!preg_match('/reserva/iu', $name) || !preg_match('/R\$\s*([\d.]+(?:,\d{1,2})?)/u', $name, $amount)) return null;
        $stmt = $this->db->prepare('SELECT id FROM objetivos WHERE usuario_id = :uid AND nome = :name AND progresso = 100 AND cancelado_em IS NULL LIMIT 1');
        $stmt->execute([':uid' => $userId, ':name' => $name]);
        if (!$stmt->fetch()) return null;
        $value = (float) str_replace(',', '.', str_replace('.', '', $amount[1]));
        return $value > 0 && $value <= 99999999.99 ? $value : null;
    }
    
    // ------------------------------------------------
    // CRUD de conversas
    // ------------------------------------------------
    
    public function index($userId) {
        $rows = $this->conversationModel->listByUser($userId);
        jsonResponse([
            'success' => true,
            'count' => count($rows),
            'conversations' => array_map([$this, 'formatConversation'], $rows)
        ], 200);
    }
    
    public function create($userId, $data) {
        $data = is_array($data) ? $data : [];
        $title = isset($data['title']) && trim((string) $data['title']) !== ''
            ? $this->cleanTitle($data['title'])
            : null;
        
        $id = $this->conversationModel->create($userId, $title);
        $conversation = $this->conversationModel->findOwned($id, $userId);
        
        jsonResponse([
            'success' => true,
            'conversation' => $this->formatConversation($conversation)
        ], 201);
    }
    
    public function show($userId, $id) {
        $conversation = $this->requireOwned($userId, $id);
        $messages = $this->conversationModel->messages((int) $id, $userId);
        
        jsonResponse([
            'success' => true,
            'conversation' => $this->formatConversation($conversation),
            'messages' => array_map([$this, 'formatMessage'], $messages)
        ], 200);
    }
    
    public function rename($userId, $id, $data) {
        $this->requireOwned($userId, $id);
        $data = is_array($data) ? $data : [];
        validateRequired($data, ['title']);
        $title = $this->cleanTitle($data['title']);
        
        if (!$this->conversationModel->rename((int) $id, $userId, $title)) {
            errorResponse('Erro ao renomear conversa', 500);
        }
        
        jsonResponse([
            'success' => true,
            'conversation' => $this->formatConversation($this->conversationModel->findOwned((int) $id, $userId))
        ], 200);
    }
    
    public function destroy($userId, $id) {
        $this->requireOwned($userId, $id);
        
        if (!$this->conversationModel->delete((int) $id, $userId)) {
            errorResponse('Erro ao excluir conversa', 500);
        }
        
        jsonResponse(['success' => true, 'message' => 'Conversa excluída'], 200);
    }
    
    // ------------------------------------------------
    // Mensagens
    // ------------------------------------------------
    
    /**
     * POST /api/conversations/:id/messages
     * Body: { "content": "...", "client_id": "uuid-gerado-no-navegador" }
     *
     * Fluxo idempotente:
     *  1. grava a mensagem do usuário (UNIQUE conversa_id + client_id);
     *  2. se já existir resposta da IA para ela, só devolve (sem nova chamada);
     *  3. senão chama a IA e grava a resposta (UNIQUE resposta_de);
     *  4. se a IA falhar, a mensagem do usuário fica salva e o mesmo
     *     envio pode ser repetido com o mesmo client_id.
     */
    public function sendMessage($userId, $id, $data) {
        // Termina e grava a resposta mesmo se o navegador fechar no meio
        ignore_user_abort(true);
        @set_time_limit(120);
        
        $data = is_array($data) ? $data : [];
        validateRequired($data, ['content', 'client_id']);
        
        $content = trim(str_replace("\0", '', (string) $data['content']));
        $clientId = (string) $data['client_id'];
        
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $clientId)) {
            errorResponse('client_id inválido', 400);
        }
        if (mb_strlen($content, 'UTF-8') < self::MIN_CONTENT) errorResponse('Digite uma mensagem.', 400);
        if (mb_strlen($content, 'UTF-8') > self::MAX_CONTENT) {
            errorResponse('Mensagem muito longa (máximo ' . self::MAX_CONTENT . ' caracteres)', 400);
        }
        
        $conversation = $this->requireOwned($userId, $id);
        $conversationId = (int) $conversation['id'];
        
        // 1. Mensagem do usuário (idempotente por client_id)
        $userMessage = $this->conversationModel->findByClientId($conversationId, $clientId);
        if (!$userMessage) {
            $userMessage = $this->conversationModel->addMessage(
                $conversationId, 'usuario', 'texto', $content, $clientId
            );
            $this->conversationModel->setAutoTitle($conversationId, $userId, $this->autoTitle($content));
            $this->conversationModel->touch($conversationId);
        }
        
        // 2. Resposta já existente (reenvio/duplicidade): não chama a IA de novo
        $reply = $this->conversationModel->findReply($userMessage['id']);
        
        // 3. Gera a resposta da IA
        if (!$reply) {
            $greeting = $this->greetingReply($userMessage['conteudo']);
            if ($greeting !== null) {
                $reply = $this->conversationModel->addMessage($conversationId, 'ia', 'texto', $greeting, null, $userMessage['id']);
                $this->conversationModel->touch($conversationId);
            }
        }
        if (!$reply) {
            // Limite de uso por usuário (a mensagem já está salva; dá para tentar depois)
            $wait = (new RateLimiter($this->db))->hit($userId);
            if ($wait > 0) {
                header('Retry-After: ' . $wait);
                jsonResponse([
                    'error' => true,
                    'retryable' => true,
                    'retry_after' => $wait,
                    'message' => RateLimiter::message($wait),
                    'user_message' => $this->formatMessage($userMessage)
                ], 429);
            }
            
            $result = null;
            try {
                $mentionsItem = (bool) preg_match('/objetiv|meta|tarefa|aporte|plano mensal|pend[eê]ncia|conclu|terminei|finalizei|completei/iu', $userMessage['conteudo']) ||
                    $this->awaitingItemClarification($conversationId, $userId, $userMessage['id']);
                if ($mentionsItem) {
                    $goals = array_values(array_filter((new Goal($this->db))->getByUserId($userId), fn($g) => !$g['cancelado_em'] && (int) $g['progresso'] < 100));
                    $tasks = array_values(array_filter((new Task($this->db))->getByUserId($userId), fn($t) => !$t['cancelado_em'] && $t['status'] === 'pendente'));
                    $actionHistory = $this->buildActionHistory($conversationId, $userId, $userMessage['id']);
                    $action = (new AIService())->extractItemAction($userMessage['conteudo'], $goals, $tasks, $actionHistory, $userId);
                    if ($action) $reply = $this->applyItemAction($userId, $conversationId, $userMessage, $action);
                }
                // Pedidos que citam objetivos ou tarefas nunca alteram valores do perfil.
                $requestsProfileChange = (bool) preg_match('/atualiz|ajust|mude|alter|defin|coloc|adicion|acrescent|retir|reduz|aument|registr|deposit|guardei|passei a ter/iu', $userMessage['conteudo']);
                if (!$reply && !$mentionsItem && $requestsProfileChange && preg_match('/reserva|renda|gastos?|disponibilidade|horas? livres?/iu', $userMessage['conteudo'])) {
                    $updates = null;
                    if (preg_match('/atualiz|ajust|coloc|registr|defin/iu', $userMessage['conteudo']) &&
                        preg_match('/reserva/iu', $userMessage['conteudo']) && !preg_match('/\d/', $userMessage['conteudo'])) {
                        $value = $this->reserveValueFromRecentGoal($conversationId, $userId, $userMessage['id']);
                        if ($value !== null) $updates = [['field' => 'savings', 'operation' => 'set', 'value' => $value]];
                        else $reply = $this->conversationModel->addMessage($conversationId, 'ia', 'texto', 'Qual é o novo saldo da sua reserva? Não encontrei um objetivo de reserva recém-concluído para usar como referência.', null, $userMessage['id']);
                    }
                    if ($updates === null && !$reply) $updates = (new AIService())->extractProfileUpdate($userMessage['conteudo'], $userId);
                    if ($updates) $reply = $this->applyProfileUpdates($userId, $conversationId, $userMessage, $updates);
                }
                if (!$reply) {
                    require_once __DIR__ . '/DecisionController.php';
                    $decisions = new DecisionController($this->db);
                    // Memória: mensagens anteriores desta conversa (não inclui a atual)
                    $history = $this->buildHistory($conversationId, $userId, $userMessage['id']);
                    $result = $decisions->analyzeForUser($userId, $userMessage['conteudo'], $history);
                }
            } catch (InvalidArgumentException $e) {
                $reply = $this->conversationModel->addMessage($conversationId, 'ia', 'texto', $e->getMessage(), null, $userMessage['id']);
            } catch (Throwable $e) {
                logMessage('Falha na IA (conversa ' . $conversationId . '): ' . $e->getMessage(), 'ERROR');
            }
            
            if (!$result && !$reply) {
                // 4. Falha da IA: a mensagem do usuário permanece salva
                jsonResponse([
                    'error' => true,
                    'retryable' => true,
                    'message' => 'Não foi possível obter a resposta da IA. Tente novamente.',
                    'user_message' => $this->formatMessage($userMessage)
                ], 502);
            }
            
            if (!$reply) $reply = $this->conversationModel->addMessage(
                $conversationId,
                'ia',
                'analise',
                json_encode($result['analysis'], JSON_UNESCAPED_UNICODE),
                null,
                $userMessage['id']
            );
            $this->conversationModel->touch($conversationId);
        }
        
        $fresh = $this->conversationModel->findOwned($conversationId, $userId);
        
        jsonResponse([
            'success' => true,
            'conversation' => $this->formatConversation($fresh),
            'user_message' => $this->formatMessage($userMessage),
            'assistant_message' => $this->formatMessage($reply),
            'user' => $reply['formato'] === 'texto' ? (new User($this->db))->findById($userId) : null
        ], 200);
    }
}
?>
