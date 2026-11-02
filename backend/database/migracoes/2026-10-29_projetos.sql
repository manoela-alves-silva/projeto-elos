-- ELOS — projetos da galeria, identidade visual e painel ao vivo (2026-10-29)
--
-- 1. Projetos: além das exposições, a galeria organiza o Cine Clube, o
--    Café Cultural, a Arte ao Ar Livre e a Amostra de Talentos. Cada
--    projeto é um tipo de evento; o evento de projeto tem uma "ficha"
--    (o filme, o convidado, a programação...) guardada em eventos_fichas.
-- 2. Identidade visual: cada evento pode ter imagem de capa e cor; cada
--    tipo pode ter cor.
-- 3. Checklist: item pode ser marcado "não precisa de prazo".
-- 4. Painel ao vivo: link secreto para o monitor (só o hash fica no banco).
--
-- Rodar uma vez no banco que já existe:
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-10-29_projetos.sql

ALTER TABLE tipos_evento
    ADD COLUMN cor CHAR(7) NULL AFTER nome;

ALTER TABLE eventos
    ADD COLUMN cor CHAR(7) NULL AFTER observacoes,
    ADD COLUMN imagem_capa VARCHAR(64) NULL AFTER cor;

ALTER TABLE tarefas
    ADD COLUMN prazo_dispensado TINYINT(1) NOT NULL DEFAULT 0 AFTER horario;

CREATE TABLE IF NOT EXISTS eventos_fichas (
    evento_id INT UNSIGNED NOT NULL,
    dados LONGTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (evento_id),
    CONSTRAINT fk_eventos_fichas_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS painel_acesso (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo_hash CHAR(64) NOT NULL,
    criado_por INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_painel_acesso_codigo (codigo_hash),
    CONSTRAINT fk_painel_acesso_usuario FOREIGN KEY (criado_por)
        REFERENCES usuarios (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Um tipo de evento por projeto (a ligação fica na coluna projeto,
-- criada pela 2026-11-02_projeto_do_tipo.sql).
INSERT IGNORE INTO tipos_evento (nome, cor) VALUES
    ('Cine Clube', '#b5462b'),
    ('Café Cultural', '#7a5230'),
    ('Arte ao Ar Livre', '#2f7d4f'),
    ('Amostra de Talentos', '#be185d');

-- Categoria usada pelos checklists padrão dos projetos.
INSERT IGNORE INTO categorias_tarefa (nome) VALUES ('Divulgação');
