<?php
/**
 * ============================================
 * Model: User (Usuário)
 * ============================================
 * 
 * Esta classe representa a tabela 'usuarios' no banco de dados
 * Contém todos os métodos para manipular usuários
 */

class User {
    private $conn;
    private static $aiStyleSchemaReady = false;
    private $table = 'usuarios';
    
    // Propriedades
    public $id;
    public $nome;
    public $email;
    public $senha_hash;
    public $renda_mensal;
    public $gastos_mensais;
    public $reserva;
    public $horas_livres_semana;
    public $perfil_risco;
    public $tema;
    public $criado_em;
    public $atualizado_em;
    
    public function __construct($db) {
        $this->conn = $db;
        if (!self::$aiStyleSchemaReady) {
            $column = $this->conn->query("SHOW COLUMNS FROM usuarios LIKE 'estilo_resposta_ia'")->fetch();
            if (!$column) $this->conn->exec("ALTER TABLE usuarios ADD COLUMN estilo_resposta_ia VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER tema");
            self::$aiStyleSchemaReady = true;
        }
    }
    
    /**
     * Buscar usuário por email
     */
    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Buscar usuário por ID
     */
    public function findById($id) {
        $query = "SELECT id, nome, email, renda_mensal, gastos_mensais, 
                  reserva, horas_livres_semana, perfil_risco, tema, estilo_resposta_ia,
                  criado_em, atualizado_em 
                  FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Criar novo usuário
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (nome, email, senha_hash) 
                  VALUES (:nome, :email, :senha_hash)";
        
        $stmt = $this->conn->prepare($query);
        $senha_hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $nome = htmlspecialchars(strip_tags($data['name']));
        $email = htmlspecialchars(strip_tags($data['email']));
        
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':senha_hash', $senha_hash);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    /**
     * Atualizar perfil do usuário
     */
    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " 
                  SET nome = :nome,
                      renda_mensal = :renda_mensal,
                      gastos_mensais = :gastos_mensais,
                      reserva = :reserva,
                      horas_livres_semana = :horas_livres_semana,
                      perfil_risco = :perfil_risco,
                      tema = :tema,
                      estilo_resposta_ia = :estilo_resposta_ia
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $nome = htmlspecialchars(strip_tags($data['name']));
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':renda_mensal', $data['monthly_income']);
        $stmt->bindParam(':gastos_mensais', $data['monthly_expenses']);
        $stmt->bindParam(':reserva', $data['savings']);
        $stmt->bindParam(':horas_livres_semana', $data['weekly_free_hours']);
        $stmt->bindParam(':perfil_risco', $data['risk_profile']);
        $stmt->bindParam(':tema', $data['theme']);
        $stmt->bindParam(':estilo_resposta_ia', $data['ai_response_style']);
        
        return $stmt->execute();
    }
    
    /**
     * Atualizar somente o nome de exibição
     * (não toca em dados financeiros nem no e-mail)
     */
    public function updateName($id, $name) {
        $query = "UPDATE " . $this->table . " SET nome = :nome WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':nome', $name);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /**
     * Atualizar somente o tema (light, dark ou auto)
     */
    public function updateTheme($id, $theme) {
        $query = "UPDATE " . $this->table . " SET tema = :tema WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':tema', $theme);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /**
     * Verificar senha
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Atualizar senha do usuário
     */
    public function updatePassword($id, $newPassword) {
        $query = "UPDATE " . $this->table . " 
                  SET senha_hash = :senha_hash 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $senha_hash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':senha_hash', $senha_hash);
        
        return $stmt->execute();
    }
    
    /**
     * Verificar se email já está cadastrado
     */
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Deletar usuário
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    /**
     * Calcular sobra mensal (renda - gastos)
     */
    public function getMonthlySurplus($id) {
        $user = $this->findById($id);
        if ($user) {
            return $user['renda_mensal'] - $user['gastos_mensais'];
        }
        return 0;
    }
}
?>
