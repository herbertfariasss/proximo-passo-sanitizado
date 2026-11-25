<?php
/**
 * ============================================
 * Middleware de Autenticação
 * ============================================
 * 
 * Este middleware é responsável por:
 * - Validar tokens JWT
 * - Proteger rotas da API
 * - Extrair ID do usuário autenticado
 * 
 * JWT (JSON Web Token):
 * - Token assinado que contém dados do usuário
 * - Enviado no header: Authorization: Bearer {token}
 * - Não precisa consultar banco a cada requisição
 */

class AuthMiddleware {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Valida se o usuário está autenticado
     * 
     * @return int ID do usuário autenticado
     * @throws Exception se não autenticado
     */
    public function authenticate() {
        // Pegar o token do header Authorization
        $headers = getallheaders();
        
        if (!isset($headers['Authorization'])) {
            errorResponse('Token não fornecido', 401);
        }
        
        // O formato é: "Bearer {token}"
        $authHeader = $headers['Authorization'];
        $token = str_replace('Bearer ', '', $authHeader);
        
        if (empty($token)) {
            errorResponse('Token inválido', 401);
        }
        
        // Validar e decodificar o token
        $userId = $this->validateToken($token);
        
        if (!$userId) {
            errorResponse('Token inválido ou expirado', 401);
        }
        
        return $userId;
    }
    
    /**
     * Retorna o token Bearer da requisição atual (ou null)
     */
    public function getBearerToken() {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                return $this->extractToken($value);
            }
        }
        return null;
    }
    
    /**
     * Revoga um token (logout no servidor).
     * Guarda apenas o hash SHA-256 do token até a data de expiração dele.
     */
    public function revokeToken($token) {
        // Garante a tabela (já existe se o schema.sql foi aplicado).
        // Falha aqui não impede a tentativa de registrar a revogação.
        try {
            $this->conn->exec(
                "CREATE TABLE IF NOT EXISTS tokens_revogados (
                    token_hash CHAR(64) PRIMARY KEY,
                    expira_em INT UNSIGNED NOT NULL,
                    revogado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_expira_em (expira_em)
                ) ENGINE=InnoDB"
            );
        } catch (Exception $e) {
            // tabela já existe ou o banco não aceitou o DDL
        }
        
        try {
            $payload = $this->decodeToken($token);
            $exp = isset($payload['exp']) ? (int) $payload['exp'] : time() + JWT_EXPIRATION;
            
            // Limpa registros que já expiraram naturalmente
            $this->conn->prepare("DELETE FROM tokens_revogados WHERE expira_em < :agora")
                       ->execute([':agora' => time()]);
            
            $stmt = $this->conn->prepare(
                "INSERT INTO tokens_revogados (token_hash, expira_em) VALUES (:hash, :exp)"
            );
            try {
                $stmt->execute([':hash' => hash('sha256', $token), ':exp' => $exp]);
            } catch (PDOException $e) {
                // 23000 = já estava revogado: tudo certo
                if ($e->getCode() !== '23000') throw $e;
            }
            return true;
        } catch (Exception $e) {
            logMessage('Falha ao revogar token: ' . $e->getMessage(), 'WARNING');
            return false;
        }
    }
    
    /**
     * Verifica se o token foi revogado por logout
     */
    private function isRevoked($token) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT 1 FROM tokens_revogados WHERE token_hash = :hash LIMIT 1"
            );
            $stmt->execute([':hash' => hash('sha256', $token)]);
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            // Tabela ainda não existe (nenhum logout feito): nada revogado
            return false;
        }
    }
    
    /**
     * Gera um token JWT para um usuário
     * 
     * @param int $userId ID do usuário
     * @param array $extraData Dados adicionais opcionais
     * @return string Token JWT
     */
    public function generateToken($userId, $extraData = []) {
        // Header do JWT
        $header = [
            'typ' => 'JWT',
            'alg' => JWT_ALGORITHM
        ];
        
        // Payload (dados do token)
        $payload = [
            'user_id' => $userId,
            'iat' => time(),                           // Issued At (quando foi criado)
            'exp' => time() + JWT_EXPIRATION           // Expiration (quando expira)
        ];
        
        // Adicionar dados extras se houver
        $payload = array_merge($payload, $extraData);
        
        // Codificar em Base64
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        // Criar assinatura
        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            JWT_SECRET_KEY,
            true
        );
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        // Montar o token: header.payload.signature
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    /**
     * Valida um token JWT
     * 
     * @param string $token Token a validar
     * @return int|false ID do usuário ou false se inválido
     */
    public function validateToken($token) {
        // Dividir o token em suas partes
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            logMessage('Token malformado: não tem 3 partes', 'WARNING');
            return false;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        // Verificar assinatura
        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            JWT_SECRET_KEY,
            true
        );
        $signatureExpected = $this->base64UrlEncode($signature);
        
        if ($signatureEncoded !== $signatureExpected) {
            logMessage('Assinatura do token inválida', 'WARNING');
            return false;
        }
        
        // Decodificar payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
        
        if (!$payload) {
            logMessage('Payload do token inválido', 'WARNING');
            return false;
        }
        
        // Verificar expiração
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            logMessage('Token expirado', 'WARNING');
            return false;
        }
        
        // Token revogado por logout?
        if ($this->isRevoked($token)) {
            logMessage('Token revogado (logout)', 'WARNING');
            return false;
        }
        
        // Retornar ID do usuário
        return isset($payload['user_id']) ? $payload['user_id'] : false;
    }
    
    /**
     * Decodifica payload de um token (SEM validar assinatura)
     * Use apenas para debug/informação
     * 
     * @param string $token Token
     * @return array|null Payload decodificado
     */
    public function decodeToken($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        return $payload;
    }
    
    /**
     * Codifica string em Base64 URL-safe
     * 
     * @param string $data Dados a codificar
     * @return string Dados codificados
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Decodifica string Base64 URL-safe
     * 
     * @param string $data Dados codificados
     * @return string Dados decodificados
     */
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Verifica se token está no formato correto (Bearer {token})
     * 
     * @param string $authHeader Header de autorização
     * @return bool
     */
    public function isValidAuthHeader($authHeader) {
        return preg_match('/^Bearer\s+(.+)$/', $authHeader);
    }
    
    /**
     * Extrai o token do header Authorization
     * 
     * @param string $authHeader Header completo
     * @return string|null Token ou null
     */
    public function extractToken($authHeader) {
        if (preg_match('/^Bearer\s+(.+)$/', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Verifica se requisição tem autenticação (sem bloquear)
     * Útil para rotas opcionais
     * 
     * @return int|null ID do usuário ou null se não autenticado
     */
    public function getOptionalUserId() {
        $headers = getallheaders();
        
        if (!isset($headers['Authorization'])) {
            return null;
        }
        
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        
        if (empty($token)) {
            return null;
        }
        
        return $this->validateToken($token);
    }
}
?>
