<?php
/**
 * ============================================
 * Arquivo de Configuração Geral do Sistema
 * ============================================
 * 
 * Este arquivo contém:
 * - Configurações de segurança
 * - Headers para API REST
 * - Chaves secretas
 * - Configurações da IA
 * - Funções auxiliares
 */

// ============================================
// CONFIGURAÇÕES GERAIS
// ============================================

// Fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

// Segredos e opções vêm do arquivo .env (veja .env.example)
require_once __DIR__ . '/env.php';

// Ambiente: "development" (seu computador) ou "production" (servidor real).
// Sem .env o padrão é production (o mais seguro).
define('APP_ENV', strtolower((string) env('APP_ENV', 'production')));

// Modo debug: só pode ficar ligado fora de produção
define('DEBUG_MODE', APP_ENV !== 'production' && env_bool('DEBUG_MODE', false));

// Erros: aparecem na tela apenas em debug; sempre vão para o log do servidor
error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? '1' : '0');
ini_set('log_errors', '1');

// ============================================
// CONFIGURAÇÕES DE SESSÃO (Segurança)
// ============================================
// Movidas para routes.php para evitar conflitos

// ============================================
// AUTENTICAÇÃO JWT
// ============================================
// JWT = JSON Web Token (usado para autenticar usuários)

// Chave secreta para assinar tokens: vem do .env (JWT_SECRET).
// Gere uma com:  php -r "echo bin2hex(random_bytes(32));"
$jwtSecret = (string) env('JWT_SECRET', '');
if (strlen($jwtSecret) < 32 || stripos($jwtSecret, 'mude_esta_chave') !== false) {
    error_log('JWT_SECRET ausente, curto (<32) ou ainda com o valor de exemplo no .env');
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'error' => true,
        'message' => 'Servidor mal configurado: defina JWT_SECRET (32+ caracteres) no arquivo .env'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
define('JWT_SECRET_KEY', $jwtSecret);

// Tempo de validade do token (em segundos)
define('JWT_EXPIRATION', env_int('JWT_EXPIRATION', 86400)); // 86400 segundos = 24 horas

// Algoritmo de assinatura
define('JWT_ALGORITHM', 'HS256');

// ============================================
// CONFIGURAÇÕES DA IA (GROQ)
// ============================================

// Provedor de IA (groq, openai, cohere, etc)
define('AI_PROVIDER', 'groq');

// Chave da API do Groq (vem do .env: GROQ_API_KEY)
// Obtenha em: https://console.groq.com/keys
define('GROQ_API_KEY', (string) env('GROQ_API_KEY', ''));

// Modelo a ser usado (.env: GROQ_MODEL)
// Opções: llama-3.1-70b-versatile, llama-3.1-8b-instant, mixtral-8x7b-32768, openai/gpt-oss-120b
define('GROQ_MODEL', (string) env('GROQ_MODEL', 'openai/gpt-oss-120b'));

// URL da API
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');

// Timeout para requisições (segundos)
define('AI_TIMEOUT', env_int('AI_TIMEOUT', 30));

// Limite de análises da IA por usuário (protege a cota da API contra abuso)
define('AI_RATE_LIMIT_PER_MINUTE', env_int('AI_RATE_LIMIT_PER_MINUTE', 6));
define('AI_RATE_LIMIT_PER_HOUR', env_int('AI_RATE_LIMIT_PER_HOUR', 60));

// ============================================
// MODO DEBUG
// ============================================
// DEBUG_MODE é definido no topo deste arquivo (a partir do .env).

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

/**
 * Retorna resposta em formato JSON
 * 
 * @param mixed $data Dados a serem retornados
 * @param int $statusCode Código HTTP (200, 201, 400, 404, 500, etc)
 * @return void
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Retorna erro em formato JSON
 * 
 * @param string $message Mensagem de erro
 * @param int $statusCode Código HTTP de erro
 * @return void
 */
function errorResponse($message, $statusCode = 400) {
    jsonResponse([
        'error' => true,
        'message' => $message
    ], $statusCode);
}

/**
 * Retorna sucesso em formato JSON
 * 
 * @param mixed $data Dados de sucesso
 * @param string $message Mensagem opcional
 * @return void
 */
function successResponse($data, $message = null) {
    $response = [
        'success' => true,
        'data' => $data
    ];
    
    if ($message) {
        $response['message'] = $message;
    }
    
    jsonResponse($response, 200);
}

/**
 * Valida se campos obrigatórios existem
 * 
 * @param array $data Dados recebidos
 * @param array $required Campos obrigatórios
 * @return bool
 */
function validateRequired($data, $required) {
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            errorResponse("Campo obrigatório ausente: {$field}", 400);
        }
    }
    return true;
}

/**
 * Sanitiza string (remove tags HTML, espaços extras)
 * 
 * @param string $string String a ser sanitizada
 * @return string
 */
function sanitizeString($string) {
    return trim(strip_tags($string));
}

/**
 * Valida email
 * 
 * @param string $email Email a ser validado
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Loga mensagem (útil para debug)
 * 
 * @param mixed $message Mensagem ou variável a logar
 * @param string $type Tipo do log (INFO, ERROR, DEBUG)
 * @return void
 */
function logMessage($message, $type = 'INFO') {
    // ERROR e WARNING sempre são registrados (também em produção);
    // as demais mensagens só aparecem com DEBUG_MODE ligado.
    if (DEBUG_MODE || in_array($type, ['ERROR', 'WARNING'], true)) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$type}] ";
        
        if (is_array($message) || is_object($message)) {
            $logMessage .= print_r($message, true);
        } else {
            $logMessage .= $message;
        }
        
        error_log($logMessage);
    }
}

// ============================================
// CONSTANTES ÚTEIS
// ============================================

// Prioridades de tarefas
define('PRIORITY_HIGH', 'high');
define('PRIORITY_MED', 'med');
define('PRIORITY_LOW', 'low');

// Status de tarefas
define('STATUS_PENDING', 'pending');
define('STATUS_DONE', 'done');

// Perfis de risco
define('RISK_CONSERVATIVE', 'conservador');
define('RISK_MODERATE', 'moderado');
define('RISK_BOLD', 'arrojado');

?>
