<?php
/**
 * ============================================
 * Controller: Decision (Decisões)
 * ============================================
 * 
 * Este controller gerencia:
 * - Análise de decisões com IA
 * - Histórico de decisões
 * - Sugestões rápidas
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Goal.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/Decision.php';
require_once __DIR__ . '/../services/AIService.php';
require_once __DIR__ . '/../middleware/RateLimiter.php';

class DecisionController {
    private $db;
    private $userModel;
    private $goalModel;
    private $taskModel;
    private $decisionModel;
    private $aiService;
    
    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($db);
        $this->goalModel = new Goal($db);
        $this->taskModel = new Task($db);
        $this->decisionModel = new Decision($db);
        $this->aiService = new AIService();
    }
    
    /**
     * POST /api/decisions/analyze
     * Analisa uma decisão usando IA
     * 
     * Body esperado:
     * {
     *   "description": "Estou pensando em comprar um celular de R$ 2.000"
     * }
     */
    public function analyze($userId, $data) {
        // Validar entrada
        validateRequired($data, ['description']);
        
        $description = trim($data['description']);
        
        if (strlen($description) < 10) {
            errorResponse('Descrição muito curta. Forneça mais detalhes sobre a decisão.', 400);
        }
        
        // Limite de uso da IA por usuário (protege a cota da API)
        $wait = (new RateLimiter($this->db))->hit($userId);
        if ($wait > 0) {
            header('Retry-After: ' . $wait);
            errorResponse(RateLimiter::message($wait), 429);
        }
        
        // Executa a análise e grava no histórico de decisões
        $result = $this->analyzeForUser($userId, $description);
        
        if (!$result) {
            errorResponse('Erro ao processar análise com IA. Tente novamente.', 500);
        }
        
        $decisionId = $result['decision_id'];
        $analysis = $result['analysis'];
        
        // Retornar análise
        jsonResponse([
            'success' => true,
            'decision_id' => $decisionId,
            'analysis' => $analysis
        ], 200);
    }
    
    /**
     * Núcleo da análise (reutilizado pelo chat com conversas persistidas).
     * Chama a IA com o contexto do usuário e grava em `decisoes`.
     * 
     * @param array $history Mensagens anteriores da conversa (memória), se houver
     * @return array|null ['analysis' => array, 'decision_id' => int] ou null se a IA falhar
     */
    public function analyzeForUser($userId, $description, $history = []) {
        // Buscar contexto completo do usuário
        $userContext = $this->getUserContext($userId);
        require_once __DIR__ . '/../services/MarketDataService.php';
        $marketData = (new MarketDataService())->contextFor($description);
        if ($marketData !== null) $userContext['market_data'] = $marketData;
        
        logMessage("Iniciando análise de decisão para usuário {$userId}", "INFO");
        
        $analysis = $this->aiService->analyzeDecision($description, $userContext, $history);
        
        if (!$analysis) {
            return null;
        }
        
        // Extrair valor mencionado na descrição (se houver)
        $amount = $this->extractAmount($description);
        
        // Salvar no histórico
        $decisionId = $this->decisionModel->create([
            'user_id' => $userId,
            'description' => $description,
            'amount' => $amount,
            'analysis_result' => $analysis
        ]);
        
        logMessage("Decisão analisada com sucesso. ID: {$decisionId}", "INFO");
        
        return ['analysis' => $analysis, 'decision_id' => $decisionId];
    }
    
    /**
     * GET /api/decisions/history
     * Retorna histórico de decisões do usuário
     * 
     * Query params opcionais:
     * - limit: quantidade de resultados (padrão: 20)
     */
    public function history($userId) {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        $limit = min(100, max(1, $limit)); // Entre 1 e 100
        
        $decisions = $this->decisionModel->getByUserId($userId, $limit);
        
        // Decodificar JSON de analysis_result
        foreach ($decisions as &$decision) {
            if (isset($decision['analysis_result'])) {
                $decision['analysis_result'] = json_decode($decision['analysis_result'], true);
            }
        }
        
        jsonResponse([
            'success' => true,
            'count' => count($decisions),
            'decisions' => $decisions
        ], 200);
    }
    
    /**
     * GET /api/decisions/:id
     * Retorna uma decisão específica
     */
    public function getById($userId, $decisionId) {
        $decision = $this->decisionModel->findById($decisionId);
        
        if (!$decision) {
            errorResponse('Decisão não encontrada', 404);
        }
        
        // Verificar se a decisão pertence ao usuário
        if ($decision['user_id'] != $userId) {
            errorResponse('Você não tem permissão para acessar esta decisão', 403);
        }
        
        // Decodificar analysis_result
        if (isset($decision['analysis_result'])) {
            $decision['analysis_result'] = json_decode($decision['analysis_result'], true);
        }
        
        jsonResponse([
            'success' => true,
            'decision' => $decision
        ], 200);
    }
    
    /**
     * DELETE /api/decisions/:id
     * Deleta uma decisão do histórico
     */
    public function delete($userId, $decisionId) {
        $decision = $this->decisionModel->findById($decisionId);
        
        if (!$decision) {
            errorResponse('Decisão não encontrada', 404);
        }
        
        // Verificar se a decisão pertence ao usuário
        if ($decision['user_id'] != $userId) {
            errorResponse('Você não tem permissão para deletar esta decisão', 403);
        }
        
        $success = $this->decisionModel->delete($decisionId);
        
        if (!$success) {
            errorResponse('Erro ao deletar decisão', 500);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Decisão deletada com sucesso'
        ], 200);
    }
    
    /**
     * POST /api/decisions/quick-suggest
     * Sugestões rápidas baseadas em tempo ou dinheiro disponível
     * 
     * Body esperado:
     * {
     *   "type": "time",  // ou "money"
     *   "value": 60      // 60 minutos ou R$ 60
     * }
     */
    public function quickSuggest($userId, $data) {
        validateRequired($data, ['type', 'value']);
        
        $type = $data['type'];
        $value = floatval($data['value']);
        
        if (!in_array($type, ['time', 'money'])) {
            errorResponse('Tipo inválido. Use "time" ou "money"', 400);
        }
        
        if ($value <= 0) {
            errorResponse('Valor deve ser maior que zero', 400);
        }
        
        // Buscar contexto do usuário
        $userContext = $this->getUserContext($userId);
        
        // Se for tempo, buscar tarefas que cabem no tempo
        if ($type === 'time') {
            $suggestions = $this->suggestTasksByTime($userId, $value);
        } else {
            // Se for dinheiro, sugestões baseadas em objetivos e reserva
            $suggestions = $this->suggestByMoney($userId, $value, $userContext);
        }
        
        jsonResponse([
            'success' => true,
            'type' => $type,
            'value' => $value,
            'suggestions' => $suggestions
        ], 200);
    }
    
    /**
     * Monta contexto completo do usuário para a IA
     * 
     * @param int $userId ID do usuário
     * @return array Contexto estruturado
     */
    private function getUserContext($userId) {
        // Dados do usuário
        $user = $this->userModel->findById($userId);
        
        // Objetivos ativos
        $goals = $this->goalModel->getActiveByUserId($userId);
        $commitments = $this->taskModel->getActiveMonthlyCommitments($userId);
        
        return [
            'user_id' => $userId,
            'renda_mensal' => floatval($user['renda_mensal']),
            'gastos_mensais' => floatval($user['gastos_mensais']),
            'reserva' => floatval($user['reserva']),
            'horas_livres_semana' => intval($user['horas_livres_semana']),
            'perfil_risco' => $user['perfil_risco'],
            'estilo_resposta_ia' => $user['estilo_resposta_ia'] ?? 'normal',
            'objetivos_ativos' => $goals,
            'compromissos_mensais' => $commitments
        ];
    }
    
    /**
     * Tenta extrair valor monetário da descrição
     * 
     * @param string $text Texto da decisão
     * @return float|null Valor extraído ou null
     */
    private function extractAmount($text) {
        // Padrões para capturar valores: R$ 1.000,00 ou 1000 ou 1.000
        $patterns = [
            '/R\$?\s*(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)/',  // R$ 1.000,00
            '/(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)/',         // 1.000,00
            '/(\d+)(?:\s*(?:mil|k))/i'                     // 2 mil ou 2k
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $value = $matches[1];
                
                // Converter para float
                $value = str_replace(['.', ','], ['', '.'], $value);
                $amount = floatval($value);
                
                // Se tem "mil" ou "k", multiplicar por 1000
                if (preg_match('/mil|k/i', $text)) {
                    $amount *= 1000;
                }
                
                return $amount;
            }
        }
        
        return null;
    }
    
    /**
     * Sugestões de tarefas que cabem no tempo disponível
     * 
     * @param int $userId ID do usuário
     * @param float $minutes Minutos disponíveis
     * @return array Sugestões
     */
    private function suggestTasksByTime($userId, $minutes) {
        $tasks = $this->taskModel->getPendingByUserId($userId);
        
        $suggestions = [];
        
        foreach ($tasks as $task) {
            if ($task['estimated_minutes'] <= $minutes) {
                $suggestions[] = [
                    'icon' => 'check',
                    'title' => $task['title'],
                    'desc' => $task['estimated_minutes'] . ' min · Prioridade: ' . $this->translatePriority($task['priority'])
                ];
                
                // Limitar a 5 sugestões
                if (count($suggestions) >= 5) break;
            }
        }
        
        if (empty($suggestions)) {
            $suggestions[] = [
                'icon' => '○',
                'title' => 'Nenhuma tarefa encontrada',
                'desc' => 'Não há tarefas pendentes que cabem neste tempo. Considere criar novas tarefas.'
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Sugestões baseadas em dinheiro disponível
     * 
     * @param int $userId ID do usuário
     * @param float $amount Valor disponível
     * @param array $context Contexto do usuário
     * @return array Sugestões
     */
    private function suggestByMoney($userId, $amount, $context) {
        $suggestions = [];
        
        $pct = ($context['reserva'] > 0) 
            ? round(($amount / $context['reserva']) * 100, 1) 
            : 100;
        
        $surplus = $context['renda_mensal'] - $context['gastos_mensais'];
        
        // Sugestão 1: Avaliar impacto
        if ($pct > 20 || $amount > $surplus) {
            $suggestions[] = [
                'icon' => 'bolt',
                'title' => 'Analisar como decisão importante',
                'desc' => "R$ " . number_format($amount, 2, ',', '.') . " representa {$pct}% da sua reserva. Use a análise completa."
            ];
        }
        
        // Sugestão 2: Adicionar à reserva
        if ($pct < 15 && $amount < $surplus * 0.5) {
            $suggestions[] = [
                'icon' => 'wallet',
                'title' => 'Adicionar à reserva de emergência',
                'desc' => 'Valor relativamente pequeno. Considere fortalecer sua reserva financeira.'
            ];
        }
        
        // Sugestão 3: Investir em objetivo
        $goals = $this->goalModel->getActiveByUserId($userId);
        if (!empty($goals)) {
            $goal = $goals[0];
            $suggestions[] = [
                'icon' => '◎',
                'title' => 'Aportar em "' . $goal['nome'] . '"',
                'desc' => 'Você tem um objetivo ativo com ' . $goal['progresso'] . '% de progresso.'
            ];
        }
        
        // Sugestão padrão
        if (empty($suggestions)) {
            $suggestions[] = [
                'icon' => '○',
                'title' => 'Avaliar suas prioridades',
                'desc' => 'Considere seus objetivos pessoais antes de decidir como usar este valor.'
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Traduz prioridade para português
     */
    private function translatePriority($priority) {
        $map = [
            'high' => 'Alta',
            'med' => 'Média',
            'low' => 'Baixa'
        ];
        return $map[$priority] ?? $priority;
    }
}
?>
