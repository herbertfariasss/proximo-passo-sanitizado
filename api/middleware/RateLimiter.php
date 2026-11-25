<?php
/**
 * ============================================
 * RateLimiter: limite de análises da IA por usuário
 * ============================================
 *
 * Cada chamada à IA custa cota da API (Groq). Para ninguém esgotar a cota
 * com pedidos em sequência, cada usuário tem um limite por minuto e por hora.
 * Os limites vêm do .env (AI_RATE_LIMIT_PER_MINUTE / AI_RATE_LIMIT_PER_HOUR).
 *
 * Tabela: uso_ia (database/migrations/002_uso_ia.sql)
 */

require_once __DIR__ . '/../config/Migrator.php';

class RateLimiter {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
        try {
            Migrator::ensure($db, 'uso_ia', '002_uso_ia.sql');
        } catch (Exception $e) {
            // Sem permissão para criar a tabela: o limitador libera (ver hit())
            logMessage('Não foi possível criar a tabela uso_ia: ' . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Registra uma tentativa de usar a IA, se o usuário ainda tiver limite.
     *
     * @return int 0 = permitido (e registrado); >0 = segundos de espera
     */
    public function hit($userId, $perMinute = null, $perHour = null) {
        $perMinute = $perMinute ?? AI_RATE_LIMIT_PER_MINUTE;
        $perHour = $perHour ?? AI_RATE_LIMIT_PER_HOUR;
        if ($perMinute <= 0 && $perHour <= 0) return 0; // 0 = sem limite
        
        $now = time();
        
        try {
            // Limpa o que já saiu da janela de 1 hora
            $this->conn->prepare("DELETE FROM uso_ia WHERE usuario_id = :uid AND momento < :corte")
                       ->execute([':uid' => $userId, ':corte' => $now - 3600]);
            
            // Registra primeiro e confere depois: assim pedidos simultâneos
            // não passam todos pelo mesmo "ainda tem limite"
            $this->conn->prepare("INSERT INTO uso_ia (usuario_id, momento) VALUES (:uid, :agora)")
                       ->execute([':uid' => $userId, ':agora' => $now]);
            $myId = $this->conn->lastInsertId();
            
            $wait = 0;
            if ($perMinute > 0) {
                $wait = max($wait, $this->waitFor($userId, $now - 60, $perMinute, 60, $now));
            }
            if ($perHour > 0) {
                $wait = max($wait, $this->waitFor($userId, $now - 3600, $perHour, 3600, $now));
            }
            
            if ($wait > 0) {
                // Estourou: esta tentativa não conta
                $this->conn->prepare("DELETE FROM uso_ia WHERE id = :id")->execute([':id' => $myId]);
            }
            return $wait;
        } catch (Exception $e) {
            // Falha do contador não deve derrubar o app: libera e registra
            logMessage('RateLimiter indisponível: ' . $e->getMessage(), 'WARNING');
            return 0;
        }
    }
    
    /** Segundos até a janela liberar uma vaga (0 se estiver dentro do limite). */
    private function waitFor($userId, $since, $limit, $window, $now) {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total, MIN(momento) AS primeiro
             FROM uso_ia WHERE usuario_id = :uid AND momento >= :desde"
        );
        $stmt->execute([':uid' => $userId, ':desde' => $since]);
        $row = $stmt->fetch();
        
        if ((int) $row['total'] <= $limit) return 0;
        return max(1, (int) $row['primeiro'] + $window - $now);
    }
    
    /** Texto amigável para o usuário. */
    public static function message($waitSeconds) {
        if ($waitSeconds <= 90) {
            return "Muitas análises em pouco tempo. Aguarde {$waitSeconds} segundos e tente novamente.";
        }
        $minutes = (int) ceil($waitSeconds / 60);
        return "Você atingiu o limite de análises por hora. Tente novamente em cerca de {$minutes} minutos.";
    }
}
?>
