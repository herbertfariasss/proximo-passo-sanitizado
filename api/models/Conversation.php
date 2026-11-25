<?php
/**
 * ============================================
 * Model: Conversation (Conversas e mensagens)
 * ============================================
 *
 * Tabelas: conversas, mensagens
 * (ver database/migrations/001_conversas_mensagens.sql)
 *
 * SEGURANÇA: todo método que lê ou altera uma conversa recebe o
 * $userId e o inclui na consulta (WHERE usuario_id = :uid). Assim, uma
 * conversa de outro usuário simplesmente "não existe" para quem pede.
 */

require_once __DIR__ . '/../config/Migrator.php';

class Conversation {
    private $conn;
    const DEFAULT_TITLE = 'Nova conversa';
    const MAX_MESSAGES = 1000;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Aplica a migração se as tabelas ainda não existirem.
     * Idempotente e não destrutiva (somente CREATE TABLE IF NOT EXISTS).
     */
    public function ensureSchema() {
        Migrator::ensure($this->conn, 'conversas', '001_conversas_mensagens.sql');
        Migrator::ensure($this->conn, 'mensagens', '001_conversas_mensagens.sql');
    }
    
    private function now() {
        return date('Y-m-d H:i:s');
    }
    
    // ------------------------------------------------
    // CONVERSAS
    // ------------------------------------------------
    
    public function listByUser($userId) {
        $stmt = $this->conn->prepare(
            "SELECT id, titulo, criado_em, atualizado_em FROM conversas
             WHERE usuario_id = :uid
             ORDER BY atualizado_em DESC, id DESC
             LIMIT 300"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }
    
    public function create($userId, $title = null) {
        $now = $this->now();
        $stmt = $this->conn->prepare(
            "INSERT INTO conversas (usuario_id, titulo, criado_em, atualizado_em)
             VALUES (:uid, :titulo, :criado, :atualizado)"
        );
        $stmt->execute([
            ':uid' => $userId,
            ':titulo' => $title ?: self::DEFAULT_TITLE,
            ':criado' => $now,
            ':atualizado' => $now
        ]);
        return (int) $this->conn->lastInsertId();
    }
    
    /** Retorna a conversa SOMENTE se pertencer ao usuário. */
    public function findOwned($id, $userId) {
        $stmt = $this->conn->prepare(
            "SELECT id, usuario_id, titulo, criado_em, atualizado_em FROM conversas
             WHERE id = :id AND usuario_id = :uid LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->fetch();
    }
    
    public function rename($id, $userId, $title) {
        $stmt = $this->conn->prepare(
            "UPDATE conversas SET titulo = :titulo, atualizado_em = :agora
             WHERE id = :id AND usuario_id = :uid"
        );
        $stmt->execute([':titulo' => $title, ':agora' => $this->now(), ':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }
    
    /** Exclui a conversa e todas as mensagens dela (em transação). */
    public function delete($id, $userId) {
        if (!$this->findOwned($id, $userId)) return false;
        
        $this->conn->beginTransaction();
        try {
            $this->conn->prepare("DELETE FROM mensagens WHERE conversa_id = :id")
                       ->execute([':id' => $id]);
            $stmt = $this->conn->prepare("DELETE FROM conversas WHERE id = :id AND usuario_id = :uid");
            $stmt->execute([':id' => $id, ':uid' => $userId]);
            $this->conn->commit();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }
    
    /** Atualiza data de modificação (conversa sobe para o topo da lista). */
    public function touch($id) {
        $this->conn->prepare("UPDATE conversas SET atualizado_em = :agora WHERE id = :id")
                   ->execute([':agora' => $this->now(), ':id' => $id]);
    }
    
    // ------------------------------------------------
    // MENSAGENS
    // ------------------------------------------------
    
    /** Mensagens em ordem cronológica; o JOIN reforça a checagem de dono. */
    public function messages($conversationId, $userId) {
        $stmt = $this->conn->prepare(
            "SELECT m.id, m.conversa_id, m.remetente, m.formato, m.conteudo,
                    m.client_id, m.resposta_de, m.criado_em
             FROM mensagens m
             INNER JOIN conversas c ON c.id = m.conversa_id
             WHERE m.conversa_id = :cid AND c.usuario_id = :uid
             ORDER BY m.id ASC
             LIMIT " . self::MAX_MESSAGES
        );
        $stmt->execute([':cid' => $conversationId, ':uid' => $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Últimas mensagens ANTES de uma mensagem (memória da conversa para a IA).
     * Em ordem cronológica; o JOIN garante que só vem conversa do dono.
     */
    public function recentBefore($conversationId, $userId, $beforeId, $limit = 8) {
        $limit = max(1, (int) $limit);
        $stmt = $this->conn->prepare(
            "SELECT m.id, m.remetente, m.formato, m.conteudo
             FROM mensagens m
             INNER JOIN conversas c ON c.id = m.conversa_id
             WHERE m.conversa_id = :cid AND c.usuario_id = :uid AND m.id < :antes
             ORDER BY m.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([':cid' => $conversationId, ':uid' => $userId, ':antes' => $beforeId]);
        return array_reverse($stmt->fetchAll());
    }
    
    public function findMessageById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM mensagens WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    /** Mensagem do usuário já gravada com este client_id (idempotência). */
    public function findByClientId($conversationId, $clientId) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM mensagens WHERE conversa_id = :cid AND client_id = :client LIMIT 1"
        );
        $stmt->execute([':cid' => $conversationId, ':client' => $clientId]);
        return $stmt->fetch();
    }
    
    /** Resposta da IA já gravada para uma mensagem do usuário. */
    public function findReply($userMessageId) {
        $stmt = $this->conn->prepare("SELECT * FROM mensagens WHERE resposta_de = :id LIMIT 1");
        $stmt->execute([':id' => $userMessageId]);
        return $stmt->fetch();
    }
    
    /**
     * Insere uma mensagem. Retorna o registro gravado.
     * Em corrida de duplicidade (UNIQUE), retorna o registro que já existia.
     */
    public function addMessage($conversationId, $sender, $format, $content, $clientId = null, $replyTo = null) {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO mensagens (conversa_id, remetente, formato, conteudo, client_id, resposta_de, criado_em)
                 VALUES (:cid, :remetente, :formato, :conteudo, :client, :resposta, :criado)"
            );
            $stmt->execute([
                ':cid' => $conversationId,
                ':remetente' => $sender,
                ':formato' => $format,
                ':conteudo' => $content,
                ':client' => $clientId,
                ':resposta' => $replyTo,
                ':criado' => $this->now()
            ]);
            return $this->findMessageById($this->conn->lastInsertId());
        } catch (PDOException $e) {
            // 23000 = violação de UNIQUE: outra requisição gravou primeiro
            if ($e->getCode() === '23000') {
                if ($replyTo !== null) return $this->findReply($replyTo);
                if ($clientId !== null) return $this->findByClientId($conversationId, $clientId);
            }
            throw $e;
        }
    }
    
    /** Define o título automático só se ainda for o padrão. */
    public function setAutoTitle($id, $userId, $title) {
        $stmt = $this->conn->prepare(
            "UPDATE conversas SET titulo = :titulo
             WHERE id = :id AND usuario_id = :uid AND titulo = :padrao"
        );
        $stmt->execute([':titulo' => $title, ':id' => $id, ':uid' => $userId, ':padrao' => self::DEFAULT_TITLE]);
    }
}
?>
