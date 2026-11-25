<?php
/**
 * ============================================
 * ROTEADOR DA API REST
 * ============================================
 */

// ============================================
// HEADERS PARA API REST (antes de qualquer output)
// ============================================
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Tratar requisições OPTIONS (preflight do CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Iniciar sessão
session_start();

// Incluir configurações
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/services/PresenceService.php';

// ============================================
// OBTER INFORMAÇÕES DA REQUISIÇÃO
// ============================================

// Método HTTP (GET, POST, PUT, DELETE)
$method = $_SERVER['REQUEST_METHOD'];

// Caminho da URL (ex: /auth/login)
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Extrair o caminho depois de /api, incluindo instalações em subdiretórios
// (por exemplo, /proximo-passo/api/ping).
if (preg_match('#(?:^|/)api(?:/|$)(.*)$#', $requestPath, $matches)) {
    $path = trim($matches[1], '/');
} else {
    $path = trim($requestPath, '/');
}

// Dividir caminho em partes
$segments = explode('/', $path);

// Obter dados do body (para POST/PUT)
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE && $method !== 'GET' && $method !== 'DELETE') {
    // Se não conseguiu decodificar JSON mas tem conteúdo, pode ser problema
    if (!empty(file_get_contents('php://input'))) {
        errorResponse('JSON inválido no body da requisição', 400);
    }
    $input = [];
}

// Log da requisição (debug)
if (DEBUG_MODE) {
    error_log("=== API Request ===");
    error_log("Method: $method");
    error_log("Path: $path");
    error_log("Full URI: " . $_SERVER['REQUEST_URI']);
    logMessage("[$method] /$path", "INFO");
}

// ============================================
// CONEXÃO COM BANCO DE DADOS
// ============================================
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    errorResponse('Erro ao conectar com o banco de dados', 500);
}

// Criar instância do middleware de autenticação
$authMiddleware = new AuthMiddleware($db);

// ============================================
// ROTAS PÚBLICAS (SEM AUTENTICAÇÃO)
// ============================================

// POST /api/auth/register
if ($path === 'auth/register' && $method === 'POST') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $controller = new AuthController($db);
    $controller->register($input);
    exit; // Importante: parar execução aqui
}

// POST /api/auth/login
if ($path === 'auth/login' && $method === 'POST') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $controller = new AuthController($db);
    $controller->login($input);
    exit; // Importante: parar execução aqui
}

// POST /api/auth/validate-token
if ($path === 'auth/validate-token' && $method === 'POST') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $controller = new AuthController($db);
    $controller->validateToken($input);
    exit; // Importante: parar execução aqui
}

// GET /api/ping
if ($path === 'ping' && $method === 'GET') {
    jsonResponse([
        'success' => true,
        'message' => 'API está funcionando!',
        'timestamp' => date('Y-m-d H:i:s'),
        'authenticated' => false
    ], 200);
    exit;
}

// ============================================
// VERIFICAR AUTENTICAÇÃO PARA ROTAS PROTEGIDAS
// ============================================
$userId = $authMiddleware->authenticate();
PresenceService::touch($userId, $authMiddleware->getBearerToken());

if ($path === 'admin/dashboard' && $method === 'GET') {
    require_once __DIR__ . '/controllers/AdminController.php';
    (new AdminController($db))->dashboard($userId);
    exit;
}

// ============================================
// ROTAS PROTEGIDAS - AUTH
// ============================================

// GET /api/auth/me
if ($path === 'auth/me' && $method === 'GET') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $controller = new AuthController($db);
    $controller->me($userId);
}

// PUT /api/auth/profile
if ($path === 'auth/profile' && $method === 'PUT') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $controller = new AuthController($db);
    $controller->updateProfile($userId, $input);
}

// PUT /api/auth/theme  (altera somente o tema)
if ($path === 'auth/theme' && $method === 'PUT') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $controller = new AuthController($db);
    $controller->updateTheme($userId, $input);
}

// PUT /api/auth/name  (altera somente o nome de exibição)
if ($path === 'auth/name' && $method === 'PUT') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $controller = new AuthController($db);
    $controller->updateName($userId, $input);
}

// POST /api/auth/logout  (revoga o token atual)
if ($path === 'auth/logout' && $method === 'POST') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $controller = new AuthController($db);
    $controller->logout();
}

// PUT /api/auth/password
if ($path === 'auth/password' && $method === 'PUT') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $controller = new AuthController($db);
    $controller->changePassword($userId, $input);
}

// ============================================
// ROTAS PROTEGIDAS - TASKS
// ============================================

// GET /api/tasks
if ($path === 'tasks' && $method === 'GET') {
    require_once __DIR__ . '/controllers/TaskController.php';
    $controller = new TaskController($db);
    $controller->getAll($userId);
}

// POST /api/tasks
if ($path === 'tasks' && $method === 'POST') {
    require_once __DIR__ . '/controllers/TaskController.php';
    $controller = new TaskController($db);
    $controller->create($userId, $input);
}

// GET /api/tasks/overdue
if ($path === 'tasks/overdue' && $method === 'GET') {
    require_once __DIR__ . '/controllers/TaskController.php';
    $controller = new TaskController($db);
    $controller->getOverdue($userId);
}

// GET /api/tasks/:id
if (preg_match('/^tasks\/(\d+)$/', $path, $matches) && $method === 'GET') {
    require_once __DIR__ . '/controllers/TaskController.php';
    $controller = new TaskController($db);
    $controller->getById($userId, $matches[1]);
}

// PUT /api/tasks/:id
if (preg_match('/^tasks\/(\d+)$/', $path, $matches) && $method === 'PUT') {
    require_once __DIR__ . '/controllers/TaskController.php';
    $controller = new TaskController($db);
    $controller->update($userId, $matches[1], $input);
}

// PUT /api/tasks/:id/toggle
if (preg_match('/^tasks\/(\d+)\/toggle$/', $path, $matches) && $method === 'PUT') {
    require_once __DIR__ . '/controllers/TaskController.php';
    $controller = new TaskController($db);
    $controller->toggleStatus($userId, $matches[1]);
}

// DELETE /api/tasks/:id
if (preg_match('/^tasks\/(\d+)\/complete$/', $path, $matches) && $method === 'PUT') {
    require_once __DIR__ . '/controllers/TaskController.php';
    (new TaskController($db))->complete($userId, $matches[1]);
}

if (preg_match('/^tasks\/(\d+)\/cancel$/', $path, $matches) && $method === 'PUT') {
    require_once __DIR__ . '/controllers/TaskController.php';
    (new TaskController($db))->cancel($userId, $matches[1]);
}

if (preg_match('/^tasks\/(\d+)$/', $path, $matches) && $method === 'DELETE') {
    require_once __DIR__ . '/controllers/TaskController.php';
    $controller = new TaskController($db);
    $controller->delete($userId, $matches[1]);
}

// ============================================
// ROTAS PROTEGIDAS - GOALS
// ============================================

// GET /api/goals
if ($path === 'goals' && $method === 'GET') {
    require_once __DIR__ . '/controllers/GoalController.php';
    $controller = new GoalController($db);
    $controller->getAll($userId);
}

// GET /api/goals/active
if ($path === 'goals/active' && $method === 'GET') {
    require_once __DIR__ . '/controllers/GoalController.php';
    $controller = new GoalController($db);
    $controller->getActive($userId);
}

// GET /api/goals/upcoming-deadlines
if ($path === 'goals/upcoming-deadlines' && $method === 'GET') {
    require_once __DIR__ . '/controllers/GoalController.php';
    $controller = new GoalController($db);
    $controller->getUpcomingDeadlines($userId);
}

// POST /api/goals
if ($path === 'goals' && $method === 'POST') {
    require_once __DIR__ . '/controllers/GoalController.php';
    $controller = new GoalController($db);
    $controller->create($userId, $input);
}

// GET /api/goals/:id
if (preg_match('/^goals\/(\d+)$/', $path, $matches) && $method === 'GET') {
    require_once __DIR__ . '/controllers/GoalController.php';
    $controller = new GoalController($db);
    $controller->getById($userId, $matches[1]);
}

// PUT /api/goals/:id
if (preg_match('/^goals\/(\d+)$/', $path, $matches) && $method === 'PUT') {
    require_once __DIR__ . '/controllers/GoalController.php';
    $controller = new GoalController($db);
    $controller->update($userId, $matches[1], $input);
}

// PUT /api/goals/:id/progress
if (preg_match('/^goals\/(\d+)\/progress$/', $path, $matches) && $method === 'PUT') {
    require_once __DIR__ . '/controllers/GoalController.php';
    $controller = new GoalController($db);
    $controller->updateProgress($userId, $matches[1], $input);
}

// DELETE /api/goals/:id
if (preg_match('/^goals\/(\d+)\/cancel$/', $path, $matches) && $method === 'PUT') {
    require_once __DIR__ . '/controllers/GoalController.php';
    (new GoalController($db))->cancel($userId, $matches[1]);
}

if (preg_match('/^goals\/(\d+)$/', $path, $matches) && $method === 'DELETE') {
    require_once __DIR__ . '/controllers/GoalController.php';
    $controller = new GoalController($db);
    $controller->delete($userId, $matches[1]);
}

// ============================================
// ROTAS PROTEGIDAS - DECISIONS (IA)
// ============================================

// POST /api/decisions/analyze
if ($path === 'decisions/analyze' && $method === 'POST') {
    require_once __DIR__ . '/controllers/DecisionController.php';
    $controller = new DecisionController($db);
    $controller->analyze($userId, $input);
}

// GET /api/decisions/history
if ($path === 'decisions/history' && $method === 'GET') {
    require_once __DIR__ . '/controllers/DecisionController.php';
    $controller = new DecisionController($db);
    $controller->history($userId);
}

// POST /api/decisions/quick-suggest
if ($path === 'decisions/quick-suggest' && $method === 'POST') {
    require_once __DIR__ . '/controllers/DecisionController.php';
    $controller = new DecisionController($db);
    $controller->quickSuggest($userId, $input);
}

// GET /api/decisions/:id
if (preg_match('/^decisions\/(\d+)$/', $path, $matches) && $method === 'GET') {
    require_once __DIR__ . '/controllers/DecisionController.php';
    $controller = new DecisionController($db);
    $controller->getById($userId, $matches[1]);
}

// DELETE /api/decisions/:id
if (preg_match('/^decisions\/(\d+)$/', $path, $matches) && $method === 'DELETE') {
    require_once __DIR__ . '/controllers/DecisionController.php';
    $controller = new DecisionController($db);
    $controller->delete($userId, $matches[1]);
}

// ============================================
// ROTAS PROTEGIDAS - CONVERSATIONS (histórico do chat)
// ============================================
// O dono da conversa é sempre $userId (vindo do token JWT).

// GET /api/conversations
if ($path === 'conversations' && $method === 'GET') {
    require_once __DIR__ . '/controllers/ConversationController.php';
    $controller = new ConversationController($db);
    $controller->index($userId);
}

// POST /api/conversations
if ($path === 'conversations' && $method === 'POST') {
    require_once __DIR__ . '/controllers/ConversationController.php';
    $controller = new ConversationController($db);
    $controller->create($userId, $input);
}

// GET /api/conversations/:id
if (preg_match('/^conversations\/(\d+)$/', $path, $matches) && $method === 'GET') {
    require_once __DIR__ . '/controllers/ConversationController.php';
    $controller = new ConversationController($db);
    $controller->show($userId, $matches[1]);
}

// PUT /api/conversations/:id  (renomear)
if (preg_match('/^conversations\/(\d+)$/', $path, $matches) && $method === 'PUT') {
    require_once __DIR__ . '/controllers/ConversationController.php';
    $controller = new ConversationController($db);
    $controller->rename($userId, $matches[1], $input);
}

// DELETE /api/conversations/:id
if (preg_match('/^conversations\/(\d+)$/', $path, $matches) && $method === 'DELETE') {
    require_once __DIR__ . '/controllers/ConversationController.php';
    $controller = new ConversationController($db);
    $controller->destroy($userId, $matches[1]);
}

// POST /api/conversations/:id/messages  (mensagem + resposta da IA)
if (preg_match('/^conversations\/(\d+)\/messages$/', $path, $matches) && $method === 'POST') {
    require_once __DIR__ . '/controllers/ConversationController.php';
    $controller = new ConversationController($db);
    $controller->sendMessage($userId, $matches[1], $input);
}

// ============================================
// ROTA NÃO ENCONTRADA (404)
// ============================================
errorResponse('Rota não encontrada: ' . $method . ' /' . $path, 404);
?>
