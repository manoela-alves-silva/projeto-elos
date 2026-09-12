-- ELOS v1: estrutura inicial do banco de dados.
-- Este arquivo não deve ser executado contra um banco de produção sem revisão.

CREATE DATABASE IF NOT EXISTS elos_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE elos_db;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('ADMIN', 'GESTOR', 'COLABORADOR') NOT NULL DEFAULT 'COLABORADOR',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_evento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS responsaveis (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    tipo ENUM('PESSOA', 'SETOR', 'CURSO', 'COLETIVO', 'INSTITUICAO', 'OUTRO') NOT NULL,
    email VARCHAR(150) NULL,
    telefone VARCHAR(30) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS locais (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL UNIQUE,
    descricao TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cursos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL UNIQUE,
    ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eventos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_evento_id INT UNSIGNED NOT NULL,
    responsavel_id INT UNSIGNED NULL,
    local_id INT UNSIGNED NULL,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    prioridade ENUM('BAIXA', 'MEDIA', 'ALTA') NOT NULL DEFAULT 'MEDIA',
    status ENUM('PLANEJAMENTO', 'EM_ANDAMENTO', 'CONCLUIDO', 'CANCELADO') NOT NULL DEFAULT 'PLANEJAMENTO',
    observacoes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_eventos_tipo_evento (tipo_evento_id),
    INDEX idx_eventos_responsavel (responsavel_id),
    INDEX idx_eventos_local (local_id),
    INDEX idx_eventos_status (status),
    CONSTRAINT fk_eventos_tipo_evento FOREIGN KEY (tipo_evento_id)
        REFERENCES tipos_evento (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_eventos_responsavel FOREIGN KEY (responsavel_id)
        REFERENCES responsaveis (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_eventos_local FOREIGN KEY (local_id)
        REFERENCES locais (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS evento_cursos (
    evento_id INT UNSIGNED NOT NULL,
    curso_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (evento_id, curso_id),
    INDEX idx_evento_cursos_curso (curso_id),
    CONSTRAINT fk_evento_cursos_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_evento_cursos_curso FOREIGN KEY (curso_id)
        REFERENCES cursos (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agenda_eventos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evento_id INT UNSIGNED NOT NULL UNIQUE,
    montagem_inicio DATE NULL,
    montagem_fim DATE NULL,
    abertura DATE NULL,
    horario TIME NULL,
    permanencia_inicio DATE NULL,
    permanencia_fim DATE NULL,
    desmontagem_inicio DATE NULL,
    desmontagem_fim DATE NULL,
    tipo_horario ENUM('HORARIO_ESPECIFICO', 'DIA_TODO', 'TURNO_MANHA', 'TURNO_NOITE', 'MANHA_E_NOITE') NULL,
    observacoes TEXT NULL,
    CONSTRAINT fk_agenda_eventos_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS etapas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    ordem TINYINT UNSIGNED NOT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_etapas_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categorias_tarefa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    ativa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tarefas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evento_id INT UNSIGNED NOT NULL,
    usuario_responsavel_id INT UNSIGNED NULL,
    etapa_id INT UNSIGNED NULL,
    categoria_id INT UNSIGNED NULL,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    prazo DATE NULL,
    prioridade ENUM('BAIXA', 'MEDIA', 'ALTA') NOT NULL DEFAULT 'MEDIA',
    status ENUM('PENDENTE', 'EM_ANDAMENTO', 'CONCLUIDA', 'BLOQUEADA', 'CANCELADA') NOT NULL DEFAULT 'PENDENTE',
    observacoes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tarefas_evento (evento_id),
    INDEX idx_tarefas_usuario_responsavel (usuario_responsavel_id),
    INDEX idx_tarefas_etapa (etapa_id),
    INDEX idx_tarefas_categoria (categoria_id),
    INDEX idx_tarefas_status_prazo (status, prazo),
    CONSTRAINT fk_tarefas_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tarefas_usuario FOREIGN KEY (usuario_responsavel_id)
        REFERENCES usuarios (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tarefas_etapa FOREIGN KEY (etapa_id)
        REFERENCES etapas (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tarefas_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias_tarefa (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS formularios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evento_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    data_previsao DATE NULL,
    data_envio DATE NULL,
    status ENUM('PENDENTE', 'EM_PREPARACAO', 'ENVIADO', 'ATRASADO', 'CANCELADO') NOT NULL DEFAULT 'PENDENTE',
    observacoes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_formularios_evento (evento_id),
    INDEX idx_formularios_status (status),
    CONSTRAINT fk_formularios_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transportes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evento_id INT UNSIGNED NOT NULL,
    tipo ENUM('OBRAS', 'MATERIAIS', 'EQUIPAMENTOS', 'DEVOLUCAO', 'OUTRO') NOT NULL,
    origem VARCHAR(200) NOT NULL,
    destino VARCHAR(200) NOT NULL,
    data_solicitacao DATE NULL,
    data_transporte DATE NULL,
    horario TIME NULL,
    status ENUM('NAO_SOLICITADO', 'SOLICITADO', 'AGENDADO', 'REALIZADO', 'CANCELADO') NOT NULL DEFAULT 'NAO_SOLICITADO',
    observacoes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transportes_evento (evento_id),
    INDEX idx_transportes_status_data (status, data_transporte),
    CONSTRAINT fk_transportes_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS visitas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evento_id INT UNSIGNED NOT NULL,
    instituicao VARCHAR(200) NOT NULL,
    responsavel VARCHAR(150) NULL,
    quantidade_pessoas INT UNSIGNED NULL,
    data DATE NULL,
    horario TIME NULL,
    status VARCHAR(50) NOT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_visitas_evento (evento_id),
    INDEX idx_visitas_data (data),
    CONSTRAINT fk_visitas_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anexos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evento_id INT UNSIGNED NOT NULL,
    nome VARCHAR(200) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    tamanho BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_anexos_evento (evento_id),
    CONSTRAINT fk_anexos_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS historico (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evento_id INT UNSIGNED NULL,
    usuario_id INT UNSIGNED NULL,
    acao VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_historico_evento (evento_id),
    INDEX idx_historico_usuario (usuario_id),
    INDEX idx_historico_created_at (created_at),
    CONSTRAINT fk_historico_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_historico_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Etapas padrão do ciclo de um evento.
INSERT INTO etapas (nome, ordem, ativa) VALUES
    ('Contato inicial', 1, 1),
    ('Pré-produção', 2, 1),
    ('Produção', 3, 1),
    ('Infraestrutura', 4, 1),
    ('Divulgação', 5, 1),
    ('Montagem', 6, 1),
    ('Inauguração', 7, 1),
    ('Pós-produção', 8, 1),
    ('Desmontagem', 9, 1),
    ('Devolução', 10, 1)
ON DUPLICATE KEY UPDATE
    ordem = VALUES(ordem),
    ativa = VALUES(ativa);
