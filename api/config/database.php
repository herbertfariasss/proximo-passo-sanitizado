<?php
/**
 * ============================================
 * Classe de Conexão com o Banco de Dados
 * ============================================
 * 
 * Esta classe gerencia a conexão com o MySQL usando PDO (PHP Data Objects)
 * PDO é mais seguro que mysqli e previne SQL Injection
 * 
 * COMO USAR:
 * $database = new Database();
 * $conn = $database->getConnection();
 */

class Database {
    // Configurações do banco: vêm do .env (DB_HOST, DB_NAME, DB_USER, DB_PASS).
    // Os valores abaixo são os padrões do XAMPP/WAMP quando o .env não define nada.
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset = 'utf8mb4';        // Charset para suportar emojis e caracteres especiais
    
    public function __construct() {
        require_once __DIR__ . '/env.php';
        $this->host = env('DB_HOST', 'localhost');
        $this->db_name = env('DB_NAME', 'proximo_passo');
        $this->username = env('DB_USER', 'root');
        $this->password = env('DB_PASS', '');
    }
    
    private $conn; // Variável que armazena a conexão
    
    /**
     * Obtém a conexão com o banco de dados
     * 
     * @return PDO Objeto de conexão PDO ou null se falhar
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            // String de conexão (DSN - Data Source Name)
            $dsn = "mysql:host=" . $this->host . 
                   ";dbname=" . $this->db_name . 
                   ";charset=" . $this->charset;
            
            // Criar nova conexão PDO
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // Configurar PDO para lançar exceções em caso de erro
            // Isso facilita o debug durante o desenvolvimento
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Configurar para retornar arrays associativos por padrão
            // Exemplo: ['id' => 1, 'name' => 'João'] ao invés de [0 => 1, 1 => 'João']
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Desabilitar emulação de prepared statements para maior segurança
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
        } catch(PDOException $e) {
            // A API envia uma resposta JSON em routes.php; não imprimir texto aqui.
            error_log("Database connection error: " . $e->getMessage());
        }
        
        return $this->conn;
    }
    
    /**
     * Fecha a conexão com o banco (opcional, PHP fecha automaticamente)
     */
    public function closeConnection() {
        $this->conn = null;
    }
}
?>
