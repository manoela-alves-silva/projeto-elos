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
    codigo_recuperacao VARCHAR(255) NULL,
    perfil ENUM('ADMIN', 'GESTOR', 'COLABORADOR') NOT NULL DEFAULT 'COLABORADOR',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_evento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    -- Cor do tipo no painel ao vivo (#rrggbb); vazia = automática.
    cor CHAR(7) NULL,
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
    status ENUM('PLANEJAMENTO', 'EM_ANDAMENTO', 'CONCLUIDO', 'CANCELADO') NOT NULL DEFAULT 'PLANEJAMENTO',
    observacoes TEXT NULL,
    -- Identidade visual: arquivo da capa (guardado pelo frontend) e cor (#rrggbb).
    imagem_capa VARCHAR(100) NULL,
    cor CHAR(7) NULL,
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

CREATE TABLE IF NOT EXISTS categorias_tarefa (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    ativa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tarefas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evento_id INT UNSIGNED NOT NULL,
    usuario_responsavel_id INT UNSIGNED NULL,
    -- Responsável em texto livre: qualquer pessoa, mesmo sem conta no sistema.
    responsavel_nome VARCHAR(150) NULL,
    categoria_id INT UNSIGNED NULL,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    prazo DATE NULL,
    -- 1 = o item não precisa de prazo (fica fora do lembrete do painel).
    prazo_dispensado TINYINT(1) NOT NULL DEFAULT 0,
    -- Horário opcional do item (visita, transporte, entrega...).
    horario TIME NULL,
    prioridade ENUM('BAIXA', 'MEDIA', 'ALTA') NOT NULL DEFAULT 'MEDIA',
    status ENUM('PENDENTE', 'EM_ANDAMENTO', 'CONCLUIDA', 'BLOQUEADA', 'CANCELADA') NOT NULL DEFAULT 'PENDENTE',
    observacoes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tarefas_evento (evento_id),
    INDEX idx_tarefas_usuario_responsavel (usuario_responsavel_id),
    INDEX idx_tarefas_categoria (categoria_id),
    INDEX idx_tarefas_status_prazo (status, prazo),
    CONSTRAINT fk_tarefas_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tarefas_usuario FOREIGN KEY (usuario_responsavel_id)
        REFERENCES usuarios (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tarefas_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias_tarefa (id) ON DELETE SET NULL ON UPDATE CASCADE
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

-- Ficha de um evento de projeto (Cine Clube, Café Cultural...): os
-- campos próprios do projeto, em JSON. Os campos de cada projeto são
-- definidos em frontend/src/Projetos.php.
CREATE TABLE IF NOT EXISTS evento_ficha (
    evento_id INT UNSIGNED NOT NULL PRIMARY KEY,
    dados TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_evento_ficha_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tipos de evento com área própria no ELOS.
INSERT IGNORE INTO tipos_evento (nome, cor) VALUES
    ('Exposição de Arte', '#2b6cb0'),
    ('Cine Clube', '#b5462b'),
    ('Café Cultural', '#7a5230'),
    ('Arte ao Ar Livre', '#2f7d4f'),
    ('Amostra de Talentos', '#be185d');

-- Link secreto do painel ao vivo (monitor da produção). Só o hash
-- SHA-256 do código fica guardado; gerar um link novo apaga o anterior.
CREATE TABLE IF NOT EXISTS painel_acesso (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_hash CHAR(64) NOT NULL UNIQUE,
    criado_por INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_painel_acesso_usuario FOREIGN KEY (criado_por)
        REFERENCES usuarios (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categorias sugeridas para o checklist de necessidades do evento.
-- O frontend agrupa os itens em fases a partir do nome da categoria
-- (frontend/src/Fases.php). São só um ponto de partida: novas categorias são criadas pela API
-- (POST /api/categorias-tarefa) e qualquer uma pode ser desativada
-- (ativa = 0). INSERT IGNORE não reativa nem altera as já existentes.
INSERT IGNORE INTO categorias_tarefa (nome) VALUES
    ('Planejamento e cronograma'),
    ('Curadoria'),
    ('Identidade visual'),
    ('Divulgação'),
    ('Montagem'),
    ('Inauguração (vernissage)'),
    ('Desmontagem e devolução'),
    ('Elaboração do relatório'),
    ('Agradecimentos'),
    ('Transporte'),
    ('Infraestrutura'),
    ('Visita'),
    ('Alimentação'),
    ('Equipamentos'),
    ('Documentação'),
    ('Escolha do filme'),
    ('Direitos de exibição'),
    ('Projeção e som'),
    ('Mediação e debate'),
    ('Recepção do público'),
    ('Convidados'),
    ('Livros e autógrafos'),
    ('Espaço e ambientação'),
    ('Proposta da atividade'),
    ('Materiais'),
    ('Inscrições'),
    ('Som e palco'),
    ('Outro');
