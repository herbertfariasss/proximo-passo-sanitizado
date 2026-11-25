-- ============================================
-- Migração 001: histórico de conversas
-- ============================================
-- Segura para rodar mais de uma vez (CREATE TABLE IF NOT EXISTS) e
-- não altera nem apaga nenhuma tabela ou dado existente.
-- A API também aplica esta migração sozinha na primeira requisição
-- de conversas; rodar manualmente no phpMyAdmin é opcional.

USE proximo_passo;

CREATE TABLE IF NOT EXISTS conversas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL DEFAULT 'Nova conversa',
    criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_conversas_usuario_atualizado (usuario_id, atualizado_em)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mensagens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    remetente ENUM('usuario', 'ia') NOT NULL,
    formato ENUM('texto', 'analise') NOT NULL DEFAULT 'texto',
    conteudo MEDIUMTEXT NOT NULL,
    client_id VARCHAR(64) NULL,
    resposta_de BIGINT NULL,
    criado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES conversas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_mensagens_conversa_client (conversa_id, client_id),
    UNIQUE KEY uq_mensagens_resposta_de (resposta_de),
    INDEX idx_mensagens_conversa_ordem (conversa_id, id)
) ENGINE=InnoDB;
