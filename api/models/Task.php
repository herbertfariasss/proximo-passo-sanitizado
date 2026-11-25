<?php
/**
 * ============================================
 * Model: Task (Tarefa)
 * ============================================
 */

class Task {
    private $conn;
    private $table = 'tarefas';
    
    public $id;
    public $usuario_id;
    public $objetivo_id;
    public $titulo;
    public $prioridade;
    public $data_vencimento;
    public $tempo_estimado;
    public $status;
    public $criado_em;
    public $atualizado_em;
    
    public function __construct($db) {
        $this->conn = $db;
    }

    public function ensureMonthlyPlansSchema() {
        $this->conn->exec('CREATE TABLE IF NOT EXISTS planos_mensais (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            objetivo_id INT NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            valor_alvo DECIMAL(10,2) NULL,
            origem_recursos VARCHAR(20) NOT NULL DEFAULT \'indefinida\',
            dia_mes TINYINT UNSIGNED NOT NULL,
            inicio_mes DATE NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_plano_objetivo (usuario_id, objetivo_id),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (objetivo_id) REFERENCES objetivos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');
        $column = $this->conn->query("SHOW COLUMNS FROM planos_mensais LIKE 'valor_alvo'")->fetch();
        if (!$column) $this->conn->exec('ALTER TABLE planos_mensais ADD COLUMN valor_alvo DECIMAL(10,2) NULL AFTER valor');
        $sourceColumn = $this->conn->query("SHOW COLUMNS FROM planos_mensais LIKE 'origem_recursos'")->fetch();
        if (!$sourceColumn) $this->conn->exec("ALTER TABLE planos_mensais ADD COLUMN origem_recursos VARCHAR(20) NOT NULL DEFAULT 'indefinida' AFTER valor_alvo");
        $this->ensureCompletionSchema();
    }

    public function ensureCompletionSchema() {
        $column = $this->conn->query("SHOW COLUMNS FROM tarefas LIKE 'concluida_em'")->fetch();
        if (!$column) {
            $this->conn->exec('ALTER TABLE tarefas ADD COLUMN concluida_em DATETIME NULL AFTER status');
            $this->conn->exec("UPDATE tarefas SET concluida_em = atualizado_em WHERE status = 'concluida' AND concluida_em IS NULL");
        }
    }

    public static function monthlyDueDate($year, $month, $day) {
        $lastDay = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        return sprintf('%04d-%02d-%02d', $year, $month, min((int) $day, $lastDay));
    }

    public function saveMonthlyPlan($userId, $goalId, $title, $amount, $day, $startMonth, $targetAmount = null, $fundingSource = 'indefinida') {
        $stmt = $this->conn->prepare('INSERT INTO planos_mensais (usuario_id, objetivo_id, titulo, valor, valor_alvo, origem_recursos, dia_mes, inicio_mes)
            VALUES (:uid, :goal, :title, :amount, :target, :source, :day, :start)
            ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), valor = VALUES(valor), valor_alvo = VALUES(valor_alvo), origem_recursos = VALUES(origem_recursos), dia_mes = VALUES(dia_mes), inicio_mes = VALUES(inicio_mes)');
        return $stmt->execute([':uid' => $userId, ':goal' => $goalId, ':title' => $title, ':amount' => $amount, ':target' => $targetAmount, ':source' => $fundingSource, ':day' => $day, ':start' => $startMonth]);
    }

    public function materializeMonthlyPlans($userId) {
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
        $month = $today->format('Y-m-01');
        $ownsTransaction = !$this->conn->inTransaction();
        if ($ownsTransaction) $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare('SELECT p.* FROM planos_mensais p JOIN objetivos o ON o.id = p.objetivo_id
                WHERE p.usuario_id = :uid AND p.inicio_mes <= :month AND o.cancelado_em IS NULL AND o.progresso < 100 FOR UPDATE');
            $stmt->execute([':uid' => $userId, ':month' => $month]);
            foreach ($stmt->fetchAll() as $plan) {
                $due = self::monthlyDueDate((int) $today->format('Y'), (int) $today->format('m'), (int) $plan['dia_mes']);
                $check = $this->conn->prepare('SELECT id FROM tarefas WHERE usuario_id = :uid AND objetivo_id = :goal AND titulo = :title AND data_vencimento = :due AND cancelado_em IS NULL LIMIT 1');
                $check->execute([':uid' => $userId, ':goal' => $plan['objetivo_id'], ':title' => htmlspecialchars(strip_tags($plan['titulo'])), ':due' => $due]);
                if (!$check->fetch()) {
                    if (!$this->create(['user_id' => $userId, 'title' => $plan['titulo'], 'priority' => 'media', 'due_date' => $due, 'estimated_minutes' => 30, 'goal_id' => $plan['objetivo_id'], 'status' => 'pendente'])) throw new RuntimeException('Falha ao criar tarefa mensal');
                }
            }
            if ($ownsTransaction) $this->conn->commit();
        } catch (Throwable $e) {
            if ($ownsTransaction) $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Buscar todas as tarefas de um usuário
     */
    public function getByUserId($userId, $status = null) {
        $this->ensureMonthlyPlansSchema();
        $this->materializeMonthlyPlans($userId);
        $query = "SELECT * FROM " . $this->table . " WHERE usuario_id = :usuario_id";
        
        if ($status) {
            $query .= " AND status = :status AND cancelado_em IS NULL";
        }
        
        $query .= " ORDER BY data_vencimento ASC, prioridade DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUpcomingMonthlyTasks($userId) {
        $this->ensureMonthlyPlansSchema();
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
        $stmt = $this->conn->prepare('SELECT p.* FROM planos_mensais p JOIN objetivos o ON o.id = p.objetivo_id
            WHERE p.usuario_id = :uid AND o.cancelado_em IS NULL AND o.progresso < 100');
        $stmt->execute([':uid' => $userId]);
        $items = [];
        foreach ($stmt->fetchAll() as $plan) {
            $count = $this->conn->prepare("SELECT SUM(status = 'concluida') done, SUM(status = 'pendente') pending FROM tarefas WHERE usuario_id = :uid AND objetivo_id = :goal AND cancelado_em IS NULL");
            $count->execute([':uid' => $userId, ':goal' => $plan['objetivo_id']]);
            $counts = $count->fetch();
            $remaining = (float) $plan['valor_alvo'] > 0
                ? max(0, (int) ceil((float) $plan['valor_alvo'] / (float) $plan['valor']) - (int) $counts['done'] - (int) $counts['pending'])
                : 6;
            $limit = min(24, $remaining);
            for ($i = 1; $i <= $limit; $i++) {
                $month = $today->modify('first day of next month')->modify('+' . ($i - 1) . ' months');
                if ($month->format('Y-m-01') < $plan['inicio_mes']) continue;
                $items[] = [
                    'objetivo_id' => (int) $plan['objetivo_id'],
                    'titulo' => $plan['titulo'],
                    'data_vencimento' => self::monthlyDueDate((int) $month->format('Y'), (int) $month->format('m'), (int) $plan['dia_mes']),
                    'valor' => (float) $plan['valor']
                ];
            }
        }
        usort($items, fn($a, $b) => strcmp($a['data_vencimento'], $b['data_vencimento']));
        return $items;
    }

    public function getMonthlyPlanSummary($userId, $goalId) {
        $this->ensureMonthlyPlansSchema();
        $stmt = $this->conn->prepare('SELECT valor, valor_alvo, dia_mes, origem_recursos FROM planos_mensais WHERE usuario_id = :uid AND objetivo_id = :goal LIMIT 1');
        $stmt->execute([':uid' => $userId, ':goal' => $goalId]);
        $plan = $stmt->fetch();
        if (!$plan) return null;
        $stmt = $this->conn->prepare("SELECT SUM(status = 'concluida') done, SUM(status = 'pendente') pending, MAX(CASE WHEN status = 'pendente' THEN data_vencimento END) last_pending FROM tarefas WHERE usuario_id = :uid AND objetivo_id = :goal AND cancelado_em IS NULL");
        $stmt->execute([':uid' => $userId, ':goal' => $goalId]);
        $counts = $stmt->fetch();
        $projected = null;
        if ((float) $plan['valor_alvo'] > 0 && (float) $plan['valor'] > 0) {
            $remaining = max(0, (int) ceil((float) $plan['valor_alvo'] / (float) $plan['valor']) - (int) $counts['done']);
            $future = max(0, $remaining - (int) $counts['pending']);
            if ($future > 0) {
                $month = (new DateTimeImmutable('first day of next month', new DateTimeZone('America/Sao_Paulo')))->modify('+' . ($future - 1) . ' months');
                $projected = self::monthlyDueDate((int) $month->format('Y'), (int) $month->format('m'), (int) $plan['dia_mes']);
            } elseif ($remaining > 0) {
                $projected = $counts['last_pending'];
            }
        }
        return ['amount' => (float) $plan['valor'], 'target_amount' => $plan['valor_alvo'] === null ? null : (float) $plan['valor_alvo'], 'day' => (int) $plan['dia_mes'], 'funding_source' => $plan['origem_recursos'], 'projected_completion_date' => $projected];
    }

    public function getActiveMonthlyCommitments($userId) {
        $this->ensureMonthlyPlansSchema();
        $stmt = $this->conn->prepare('SELECT p.objetivo_id, o.nome AS objetivo, p.valor, p.origem_recursos, p.inicio_mes
            FROM planos_mensais p JOIN objetivos o ON o.id = p.objetivo_id
            WHERE p.usuario_id = :uid AND o.cancelado_em IS NULL AND o.progresso < 100');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar tarefas pendentes
     */
    public function getPendingByUserId($userId) {
        return $this->getByUserId($userId, 'pendente');
    }
    
    /**
     * Buscar tarefa por ID
     */
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Criar nova tarefa
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (usuario_id, objetivo_id, titulo, prioridade, data_vencimento, tempo_estimado, status) 
                  VALUES (:usuario_id, :objetivo_id, :titulo, :prioridade, :data_vencimento, :tempo_estimado, :status)";
        
        $stmt = $this->conn->prepare($query);
        
        $titulo = htmlspecialchars(strip_tags($data['title']));
        $status = isset($data['status']) ? $data['status'] : 'pendente';
        $objetivo_id = !empty($data['goal_id']) ? $data['goal_id'] : null;
        
        $stmt->bindParam(':usuario_id', $data['user_id']);
        $stmt->bindParam(':objetivo_id', $objetivo_id);
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':prioridade', $data['priority']);
        $stmt->bindParam(':data_vencimento', $data['due_date']);
        $stmt->bindParam(':tempo_estimado', $data['estimated_minutes']);
        $stmt->bindParam(':status', $status);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    /**
     * Atualizar tarefa
     */
    public function update($id, $data) {
        $this->ensureCompletionSchema();
        $query = "UPDATE " . $this->table . " 
                  SET titulo = :titulo,
                      prioridade = :prioridade,
                      data_vencimento = :data_vencimento,
                      tempo_estimado = :tempo_estimado,
                      objetivo_id = :objetivo_id,
                      status = :status,
                      concluida_em = CASE WHEN :status_date = 'concluida' THEN COALESCE(concluida_em, NOW()) ELSE NULL END
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $titulo = htmlspecialchars(strip_tags($data['title']));
        $objetivo_id = !empty($data['goal_id']) ? $data['goal_id'] : null;
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':prioridade', $data['priority']);
        $stmt->bindParam(':data_vencimento', $data['due_date']);
        $stmt->bindParam(':tempo_estimado', $data['estimated_minutes']);
        $stmt->bindParam(':objetivo_id', $objetivo_id);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':status_date', $data['status']);
        
        return $stmt->execute();
    }
    
    /**
     * Alternar status (pendente <-> concluida)
     */
    public function toggleStatus($id) {
        $this->ensureCompletionSchema();
        $query = "UPDATE " . $this->table . " 
                  SET status = CASE 
                      WHEN status = 'pendente' THEN 'concluida' 
                      ELSE 'pendente' 
                  END,
                  concluida_em = CASE WHEN status = 'pendente' THEN NOW() ELSE NULL END
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function complete($id, $userId) {
        $this->ensureCompletionSchema();
        $stmt = $this->conn->prepare("UPDATE tarefas SET status = 'concluida', concluida_em = COALESCE(concluida_em, NOW()) WHERE id = :id AND usuario_id = :uid AND cancelado_em IS NULL");
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function cancel($id, $userId) {
        $stmt = $this->conn->prepare("UPDATE tarefas SET cancelado_em = NOW() WHERE id = :id AND usuario_id = :uid AND status = 'pendente' AND cancelado_em IS NULL");
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Deletar tarefa
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    /**
     * Buscar tarefas por objetivo
     */
    public function getByGoalId($goalId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE objetivo_id = :objetivo_id 
                  ORDER BY data_vencimento ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':objetivo_id', $goalId);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Contar tarefas pendentes
     */
    public function countPending($userId) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id AND status = 'pendente' AND cancelado_em IS NULL";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->execute();
        
        $row = $stmt->fetch();
        return $row['total'];
    }
    
    /**
     * Buscar tarefas atrasadas
     */
    public function getOverdue($userId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id 
                  AND status = 'pendente' AND cancelado_em IS NULL
                  AND data_vencimento < CURDATE() 
                  ORDER BY data_vencimento ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Calcular tempo total estimado de tarefas pendentes
     */
    public function getTotalEstimatedTime($userId) {
        $query = "SELECT SUM(tempo_estimado) as total FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id AND status = 'pendente' AND cancelado_em IS NULL";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->execute();
        
        $row = $stmt->fetch();
        return $row['total'] ?? 0;
    }
}
?>
