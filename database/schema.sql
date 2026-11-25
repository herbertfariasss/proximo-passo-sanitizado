-- ============================================
-- Schema do Banco de Dados - Próximo Passo
-- ============================================
-- Execute este script no phpMyAdmin para criar o banco

CREATE DATABASE IF NOT EXISTS proximo_passo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE proximo_passo;

-- ============================================
-- Tabela: usuarios
-- ============================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    renda_mensal DECIMAL(10,2) DEFAULT 0,
    gastos_mensais DECIMAL(10,2) DEFAULT 0,
    reserva DECIMAL(10,2) DEFAULT 0,
    horas_livres_semana INT DEFAULT 0,
    perfil_risco ENUM('conservador', 'moderado', 'arrojado') DEFAULT 'moderado',
    tema VARCHAR(20) DEFAULT 'auto',
    estilo_resposta_ia VARCHAR(20) NOT NULL DEFAULT 'normal',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ============================================
-- Tabela: objetivos
-- ============================================
CREATE TABLE objetivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT,
    progresso INT DEFAULT 0,
    prazo DATE,
    cancelado_em DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_prazo (prazo)
) ENGINE=InnoDB;

-- ============================================
-- Tabela: tarefas
-- ============================================
CREATE TABLE tarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    objetivo_id INT NULL,
    titulo VARCHAR(200) NOT NULL,
    prioridade ENUM('alta', 'media', 'baixa') DEFAULT 'media',
    data_vencimento DATE NOT NULL,
    tempo_estimado INT DEFAULT 30,
    status ENUM('pendente', 'concluida') DEFAULT 'pendente',
    concluida_em DATETIME NULL,
    cancelado_em DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (objetivo_id) REFERENCES objetivos(id) ON DELETE SET NULL,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_status (status),
    INDEX idx_data_vencimento (data_vencimento),
    INDEX idx_prioridade (prioridade)
) ENGINE=InnoDB;

-- ============================================
-- Tabela: planos mensais de aporte
-- ============================================
CREATE TABLE IF NOT EXISTS planos_mensais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    objetivo_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    valor_alvo DECIMAL(10,2) NULL,
    origem_recursos VARCHAR(20) NOT NULL DEFAULT 'indefinida',
    dia_mes TINYINT UNSIGNED NOT NULL,
    inicio_mes DATE NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plano_objetivo (usuario_id, objetivo_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (objetivo_id) REFERENCES objetivos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Tabela: decisoes
-- ============================================
CREATE TABLE decisoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    descricao TEXT NOT NULL,
    valor DECIMAL(10,2) NULL,
    resultado_analise JSON,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB;

-- ============================================
-- Tabela: logs_ia (opcional)
-- ============================================
CREATE TABLE logs_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_requisicao VARCHAR(50),
    prompt TEXT,
    resposta TEXT,
    provedor VARCHAR(50),
    tokens_usados INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_criado_em (criado_em),
    INDEX idx_provedor (provedor)
) ENGINE=InnoDB;

-- ============================================
-- Tabela: tokens_revogados (logout seguro)
-- ============================================
-- Guarda o hash dos tokens JWT encerrados pelo usuário.
-- (A API também cria esta tabela automaticamente no primeiro logout.)
CREATE TABLE IF NOT EXISTS tokens_revogados (
    token_hash CHAR(64) PRIMARY KEY,
    expira_em INT UNSIGNED NOT NULL,
    revogado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expira_em (expira_em)
) ENGINE=InnoDB;

-- ============================================
-- Tabelas: conversas e mensagens (histórico do chat)
-- ============================================
-- Mantidas em database/migrations/001_conversas_mensagens.sql
-- (a API também aplica essa migração automaticamente).
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

-- ============================================
-- Tabela: uso_ia (limite de análises da IA por usuário)
-- ============================================
-- Mantida em database/migrations/002_uso_ia.sql
CREATE TABLE IF NOT EXISTS uso_ia (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    momento INT UNSIGNED NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_uso_ia_usuario_momento (usuario_id, momento)
) ENGINE=InnoDB;

-- ============================================
-- FIM DO SCRIPT
-- ============================================
