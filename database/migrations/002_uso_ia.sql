-- ============================================
-- Migração 002: controle de uso da IA (limite por usuário)
-- ============================================
-- Segura para rodar mais de uma vez e não altera nada existente.
-- A API também aplica esta migração sozinha quando necessário.

USE proximo_passo;

CREATE TABLE IF NOT EXISTS uso_ia (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    momento INT UNSIGNED NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_uso_ia_usuario_momento (usuario_id, momento)
) ENGINE=InnoDB;
