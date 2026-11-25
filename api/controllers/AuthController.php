<?php
/**
 * ============================================
 * Controller: Auth (Autenticação)
 * ============================================
 * 
 * Este controller gerencia:
 * - Registro de novos usuários
 * - Login (autenticação)
 * - Informações do usuário logado
 * 
 * CONTROLLER:
 * - Recebe requisições da API
 * - Valida dados de entrada
 * - Usa Models para acessar banco
 * - Retorna respostas JSON
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/AdminAccess.php';
require_once __DIR__ . '/../services/PresenceService.php';

class AuthController {
    private $db;
    private $userModel;
    private $authMiddleware;
    
    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($db);
        $this->authMiddleware = new AuthMiddleware($db);
    }
    
    /**
     * POST /api/auth/register
     * Registrar novo usuário
     * 
     * Body esperado:
     * {
     *   "name": "João Silva",
     *   "email": "usuario@example.com",
     *   "password": "SUBSTITUA_PELA_SENHA_CADASTRADA"
     * }
     */
    public function register($data) {
        // Validar campos obrigatórios
        validateRequired($data, ['name', 'email', 'password']);
        
        // Validar email
        if (!isValidEmail($data['email'])) {
            errorResponse('Email inválido', 400);
        }
        
        // Validar tamanho da senha (mínimo 6 caracteres)
        if (strlen($data['password']) < 6) {
            errorResponse('Senha deve ter no mínimo 6 caracteres', 400);
        }
        
        // Verificar se email já existe
        if ($this->userModel->emailExists($data['email'])) {
            errorResponse('Este email já está cadastrado', 409);
        }
        
        // Criar usuário
        $userId = $this->userModel->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password']
        ]);
        
        if (!$userId) {
            errorResponse('Erro ao criar usuário', 500);
        }
        
        // Buscar dados do usuário criado
        $user = $this->userModel->findById($userId);
        
        // Gerar token JWT
        $token = $this->authMiddleware->generateToken($userId);
        
        // Retornar sucesso com token
        jsonResponse([
            'success' => true,
            'message' => 'Usuário criado com sucesso',
            'token' => $token,
            'user' => $user
        ], 201);
    }
    
    /**
     * POST /api/auth/login
     * Fazer login
     * 
     * Body esperado:
     * {
     *   "email": "usuario@example.com",
     *   "password": "SUBSTITUA_PELA_SENHA_CADASTRADA"
     * }
     */
    public function login($data) {
        // Validar campos obrigatórios
        validateRequired($data, ['email', 'password']);
        
        // Buscar usuário por email
        $user = $this->userModel->findByEmail($data['email']);
        
        if (!$user) {
            errorResponse('Email ou senha incorretos', 401);
        }
        
        // Verificar senha
        if (!$this->userModel->verifyPassword($data['password'], $user['senha_hash'])) {
            errorResponse('Email ou senha incorretos', 401);
        }
        
        // Gerar token JWT
        $token = $this->authMiddleware->generateToken($user['id']);
        
        // Remover o hash antes de retornar
        unset($user['senha_hash']);
        $user['is_admin'] = AdminAccess::isAdmin($user);
        PresenceService::touch($user['id'], $token);
        
        // Retornar sucesso com token
        jsonResponse([
            'success' => true,
            'message' => 'Login realizado com sucesso',
            'token' => $token,
            'user' => $user
        ], 200);
    }
    
    /**
     * GET /api/auth/me
     * Retorna dados do usuário autenticado
     * 
     * Header necessário:
     * Authorization: Bearer {token}
     */
    public function me($userId) {
        // Buscar dados do usuário
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            errorResponse('Usuário não encontrado', 404);
        }
        $user['is_admin'] = AdminAccess::isAdmin($user);
        
        jsonResponse([
            'success' => true,
            'user' => $user
        ], 200);
    }
    
    /**
     * PUT /api/auth/profile
     * Atualiza perfil do usuário autenticado
     * 
     * Body esperado:
     * {
     *   "name": "João Silva",
     *   "monthly_income": 5000,
     *   "monthly_expenses": 3000,
     *   "savings": 10000,
     *   "weekly_free_hours": 10,
     *   "risk_profile": "moderado",
     *   "theme": "dark"
     * }
     */
    public function updateProfile($userId, $data) {
        // Validar campos obrigatórios
        validateRequired($data, ['name']);
        
        // Validar perfil de risco
        $validRiskProfiles = ['conservador', 'moderado', 'arrojado'];
        if (isset($data['risk_profile']) && !in_array($data['risk_profile'], $validRiskProfiles)) {
            errorResponse('Perfil de risco inválido. Use: conservador, moderado ou arrojado', 400);
        }
        
        // Validar tema (se enviado) e preservar o atual quando não vier no corpo.
        // Antes, salvar as Configurações sem "theme" voltava o tema para "auto".
        $validThemes = ['light', 'dark', 'auto'];
        if (isset($data['theme']) && !in_array($data['theme'], $validThemes, true)) {
            errorResponse('Tema inválido. Use: light, dark ou auto', 400);
        }
        if (isset($data['ai_response_style']) && !in_array($data['ai_response_style'], ['short', 'normal', 'technical'], true)) {
            errorResponse('Estilo de resposta inválido', 400);
        }
        $currentUser = $this->userModel->findById($userId);
        $currentTheme = $currentUser['tema'] ?? 'auto';
        
        // Preparar dados para atualização
        $updateData = [
            'name' => $data['name'],
            'monthly_income' => isset($data['monthly_income']) ? floatval($data['monthly_income']) : 0,
            'monthly_expenses' => isset($data['monthly_expenses']) ? floatval($data['monthly_expenses']) : 0,
            'savings' => isset($data['savings']) ? floatval($data['savings']) : 0,
            'weekly_free_hours' => isset($data['weekly_free_hours']) ? intval($data['weekly_free_hours']) : 0,
            'risk_profile' => $data['risk_profile'] ?? 'moderado',
            'theme' => $data['theme'] ?? $currentTheme,
            'ai_response_style' => $data['ai_response_style'] ?? ($currentUser['estilo_resposta_ia'] ?? 'normal')
        ];
        
        // Atualizar no banco
        $success = $this->userModel->update($userId, $updateData);
        
        if (!$success) {
            errorResponse('Erro ao atualizar perfil', 500);
        }
        
        // Buscar dados atualizados
        $user = $this->userModel->findById($userId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Perfil atualizado com sucesso',
            'user' => $user
        ], 200);
    }
    
    /**
     * PUT /api/auth/theme
     * Atualiza SOMENTE o tema do usuário autenticado.
     * 
     * Body esperado: { "theme": "light" | "dark" | "auto" }
     */
    public function updateTheme($userId, $data) {
        validateRequired($data, ['theme']);
        
        $theme = (string) $data['theme'];
        if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
            errorResponse('Tema inválido. Use: light, dark ou auto', 400);
        }
        
        if (!$this->userModel->updateTheme($userId, $theme)) {
            errorResponse('Erro ao atualizar tema', 500);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Tema atualizado',
            'user' => $this->userModel->findById($userId)
        ], 200);
    }
    
    /**
     * PUT /api/auth/name
     * Atualiza SOMENTE o nome de exibição do usuário autenticado.
     * O e-mail nunca é alterado por aqui.
     * 
     * Body esperado: { "name": "Novo Nome" }
     */
    public function updateName($userId, $data) {
        validateRequired($data, ['name']);
        
        // Remove tags/espaços extras; o frontend escapa o texto ao exibir
        $name = trim(strip_tags((string) $data['name']));
        $name = preg_replace('/\s+/u', ' ', $name);
        $length = mb_strlen($name, 'UTF-8');
        
        if ($length < 2) {
            errorResponse('O nome deve ter pelo menos 2 caracteres', 400);
        }
        if ($length > 100) {
            errorResponse('O nome deve ter no máximo 100 caracteres', 400);
        }
        
        if (!$this->userModel->updateName($userId, $name)) {
            errorResponse('Erro ao atualizar nome', 500);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Nome atualizado com sucesso',
            'user' => $this->userModel->findById($userId)
        ], 200);
    }
    
    /**
     * POST /api/auth/logout
     * Encerra a sessão: revoga o token usado nesta requisição,
     * de modo que ele deixa de funcionar mesmo antes de expirar.
     */
    public function logout() {
        $token = $this->authMiddleware->getBearerToken();
        if ($token) {
            PresenceService::remove($token);
            $this->authMiddleware->revokeToken($token);
        }
        jsonResponse([
            'success' => true,
            'message' => 'Sessão encerrada'
        ], 200);
    }
    
    /**
     * PUT /api/auth/password
     * Altera senha do usuário
     * 
     * Body esperado:
     * {
     *   "current_password": "senha_atual",
     *   "new_password": "nova_senha"
     * }
     */
    public function changePassword($userId, $data) {
        // Validar campos
        validateRequired($data, ['current_password', 'new_password']);
        
        // Validar tamanho da nova senha
        if (strlen($data['new_password']) < 6) {
            errorResponse('Nova senha deve ter no mínimo 6 caracteres', 400);
        }
        
        // Buscar usuário completo (com senha)
        $user = $this->userModel->findByEmail(
            $this->userModel->findById($userId)['email']
        );
        
        // Verificar senha atual
        if (!$this->userModel->verifyPassword($data['current_password'], $user['senha_hash'])) {
            errorResponse('Senha atual incorreta', 401);
        }
        
        // Atualizar senha
        $success = $this->userModel->updatePassword($userId, $data['new_password']);
        
        if (!$success) {
            errorResponse('Erro ao alterar senha', 500);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Senha alterada com sucesso'
        ], 200);
    }
    
    /**
     * POST /api/auth/validate-token
     * Valida se um token ainda é válido
     * 
     * Body esperado:
     * {
     *   "token": "jwt_token_aqui"
     * }
     */
    public function validateToken($data) {
        validateRequired($data, ['token']);
        
        $userId = $this->authMiddleware->validateToken($data['token']);
        
        if (!$userId) {
            jsonResponse([
                'valid' => false,
                'message' => 'Token inválido ou expirado'
            ], 200);
        }
        
        jsonResponse([
            'valid' => true,
            'user_id' => $userId
        ], 200);
    }
}
?>
