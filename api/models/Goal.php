<?php
/**
 * ============================================
 * Model: Goal (Objetivo)
 * ============================================
 */

class Goal {
    private $conn;
    private $table = 'objetivos';
    
    public $id;
    public $usuario_id;
    public $nome;
    public $descricao;
    public $progresso;
    public $prazo;
    public $criado_em;
    public $atualizado_em;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Buscar todos os objetivos de um usuário
     */
    public function getByUserId($userId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id 
                  ORDER BY prazo ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar objetivos ativos (progresso < 100%)
     */
    public function getActiveByUserId($userId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id 
                  AND progresso < 100 AND cancelado_em IS NULL
                  ORDER BY prazo ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar objetivo por ID
     */
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Criar novo objetivo
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (usuario_id, nome, descricao, progresso, prazo) 
                  VALUES (:usuario_id, :nome, :descricao, :progresso, :prazo)";
        
        $stmt = $this->conn->prepare($query);
        
        $nome = htmlspecialchars(strip_tags($data['name']));
        $descricao = htmlspecialchars(strip_tags($data['description']));
        $progresso = isset($data['progress']) ? max(0, min(100, $data['progress'])) : 0;
        
        $stmt->bindParam(':usuario_id', $data['user_id']);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':progresso', $progresso);
        $stmt->bindParam(':prazo', $data['deadline']);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    /**
     * Atualizar objetivo
     */
    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " 
                  SET nome = :nome,
                      descricao = :descricao,
                      progresso = :progresso,
                      prazo = :prazo
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $nome = htmlspecialchars(strip_tags($data['name']));
        $descricao = htmlspecialchars(strip_tags($data['description']));
        $progresso = max(0, min(100, $data['progress']));
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':progresso', $progresso);
        $stmt->bindParam(':prazo', $data['deadline']);
        
        return $stmt->execute();
    }
    
    /**
     * Atualizar apenas o progresso
     */
    public function updateProgress($id, $progress) {
        $query = "UPDATE " . $this->table . " 
                  SET progresso = :progresso 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $progresso = max(0, min(100, $progress));
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':progresso', $progresso);
        
        return $stmt->execute();
    }

    public function syncProgressFromTasks($userId, $goalId) {
        if (!$goalId) return;
        $goal = $this->conn->prepare('SELECT progresso FROM objetivos WHERE id = :goal AND usuario_id = :uid AND cancelado_em IS NULL');
        $goal->execute([':goal' => $goalId, ':uid' => $userId]);
        $row = $goal->fetch();
        if (!$row || (int) $row['progresso'] >= 100) return;
        $plan = $this->conn->prepare('SELECT valor, valor_alvo FROM planos_mensais WHERE usuario_id = :uid AND objetivo_id = :goal LIMIT 1');
        $plan->execute([':uid' => $userId, ':goal' => $goalId]);
        $monthly = $plan->fetch();
        $counts = $this->conn->prepare('SELECT COUNT(*) total, SUM(CASE WHEN status = :done THEN 1 ELSE 0 END) done FROM tarefas WHERE usuario_id = :uid AND objetivo_id = :goal AND cancelado_em IS NULL');
        $counts->execute([':done' => 'concluida', ':uid' => $userId, ':goal' => $goalId]);
        $tasks = $counts->fetch();
        if (!$tasks || (int) $tasks['total'] === 0) return;
        if ($monthly && (float) $monthly['valor_alvo'] > 0) {
            $progress = min(100, (int) floor(100 * (int) $tasks['done'] * (float) $monthly['valor'] / (float) $monthly['valor_alvo']));
        } elseif ($monthly) {
            return; // Sem valor alvo, concluir a tarefa do mês não conclui um plano recorrente.
        } else {
            $progress = (int) floor(100 * (int) $tasks['done'] / (int) $tasks['total']);
        }
        $this->updateProgress($goalId, $progress);
    }

    public function cancel($id, $userId) {
        $stmt = $this->conn->prepare('UPDATE objetivos SET cancelado_em = NOW() WHERE id = :id AND usuario_id = :uid AND cancelado_em IS NULL AND progresso < 100');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Deletar objetivo
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    /**
     * Contar objetivos ativos
     */
    public function countActive($userId) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id AND progresso < 100 AND cancelado_em IS NULL";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->execute();
        
        $row = $stmt->fetch();
        return $row['total'];
    }
    
    /**
     * Buscar objetivos próximos do prazo (< 30 dias)
     */
    public function getUpcomingDeadlines($userId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id 
                  AND progresso < 100 AND cancelado_em IS NULL
                  AND prazo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                  ORDER BY prazo ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>
