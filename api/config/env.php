<?php
/**
 * ============================================
 * Leitor do arquivo .env
 * ============================================
 *
 * Lê o arquivo `.env` da raiz do projeto (formato CHAVE=valor) e expõe:
 *
 *   env('CHAVE', 'padrão')        -> texto
 *   env_bool('CHAVE', false)      -> true/false
 *   env_int('CHAVE', 10)          -> número inteiro
 *
 * Variáveis de ambiente reais do servidor têm prioridade sobre o .env.
 * Segredos (chave da IA, segredo do JWT, senha do banco) ficam SOMENTE
 * no .env, que não vai para o Git nem é servido pelo Apache.
 */

if (!function_exists('env')) {
    
    function env_load_file() {
        static $values = null;
        if ($values !== null) return $values;
        
        $values = [];
        $file = dirname(__DIR__, 2) . '/.env';
        if (!is_readable($file)) return $values;
        
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove aspas ao redor do valor: "abc" ou 'abc'
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
                $value = substr($value, 1, -1);
            } else {
                // Comentário no fim da linha: CHAVE=valor # nota
                $value = trim(preg_replace('/\s+#.*$/', '', $value));
            }
            
            if ($key !== '') $values[$key] = $value;
        }
        return $values;
    }
    
    function env($key, $default = null) {
        $real = getenv($key);
        if ($real !== false && $real !== '') return $real;
        
        $file = env_load_file();
        if (array_key_exists($key, $file) && $file[$key] !== '') return $file[$key];
        
        return $default;
    }
    
    function env_bool($key, $default = false) {
        $value = env($key, null);
        if ($value === null) return $default;
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on', 'sim'], true);
    }
    
    function env_int($key, $default = 0) {
        $value = env($key, null);
        return ($value !== null && is_numeric($value)) ? (int) $value : $default;
    }
}
