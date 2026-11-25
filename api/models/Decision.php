<?php
/**
 * ============================================
 * Model: Decision (Decisão)
 * ============================================
 */

class Decision {
    private $conn;
    private $table = 'decisoes';
    
    public $id;
    public $usuario_id;
    public $descricao;
    public $valor;
    public $resultado_analise;
    public $criado_em;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Buscar todas as decisões de um usuário
     */
    public function getByUserId($userId, $limit = 50) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id 
                  ORDER BY criado_em DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar decisão por ID
     */
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Criar nova decisão
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (usuario_id, descricao, valor, resultado_analise) 
                  VALUES (:usuario_id, :descricao, :valor, :resultado_analise)";
        
        $stmt = $this->conn->prepare($query);
        
        $descricao = htmlspecialchars(strip_tags($data['description']));
        
        $resultado_analise = is_array($data['analysis_result']) 
            ? json_encode($data['analysis_result'], JSON_UNESCAPED_UNICODE) 
            : $data['analysis_result'];
        
        $valor = isset($data['amount']) ? $data['amount'] : null;
        
        $stmt->bindParam(':usuario_id', $data['user_id']);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':valor', $valor);
        $stmt->bindParam(':resultado_analise', $resultado_analise);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    /**
     * Deletar decisão
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    /**
     * Buscar decisões recentes (últimos N dias)
     */
    public function getRecentByUserId($userId, $days = 7) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id 
                  AND criado_em >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  ORDER BY criado_em DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Contar decisões de um usuário
     */
    public function countByUserId($userId) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->execute();
        
        $row = $stmt->fetch();
        return $row['total'];
    }
    
    /**
     * Buscar decisões por valor (range)
     */
    public function getByAmountRange($userId, $minAmount, $maxAmount) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE usuario_id = :usuario_id 
                  AND valor BETWEEN :min_amount AND :max_amount
                  ORDER BY criado_em DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $userId);
        $stmt->bindParam(':min_amount', $minAmount);
        $stmt->bindParam(':max_amount', $maxAmount);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>
