-- ELOS — projetos da galeria: Cine Clube, Café Cultural, Arte ao Ar Livre e
-- Amostra de Talentos (2026-10-29)
--
-- Os projetos da galeria (Cine Clube, Café Cultural...) passam a ter
-- área própria no ELOS. Cada encontro ou sessão é um evento do tipo do
-- projeto, com checklist, anexos e histórico, como as exposições.
--
-- O que é próprio de cada projeto (o filme da sessão, o autor e a obra
-- do Café Cultural...) fica na "ficha" do evento: uma linha por evento,
-- com os campos em JSON. Quais campos cada projeto tem é definido no
-- frontend (frontend/src/Projetos.php); projeto novo não pede tabela nova.
--
-- Cria os tipos, a tabela evento_ficha e categorias de checklist dos
-- projetos. Nenhum dado existente é alterado (INSERT IGNORE não mexe no
-- que já existe).
--
-- Rodar uma vez no banco que já existe, ANTES de enviar o código novo
-- (depois de 2026-10-29_cor_tipo_evento.sql):
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-10-29_projetos.sql

INSERT IGNORE INTO tipos_evento (nome, cor) VALUES
    ('Cine Clube', '#b5462b'),
    ('Café Cultural', '#7a5230'),
    ('Arte ao Ar Livre', '#2f7d4f'),
    ('Amostra de Talentos', '#be185d');

CREATE TABLE IF NOT EXISTS evento_ficha (
    evento_id INT UNSIGNED NOT NULL PRIMARY KEY,
    -- Campos do projeto, em JSON (ex.: {"filme": "...", "ano": 1998}).
    dados TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_evento_ficha_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO categorias_tarefa (nome) VALUES
    -- Cine Clube
    ('Escolha do filme'),
    ('Direitos de exibição'),
    ('Projeção e som'),
    ('Mediação e debate'),
    ('Recepção do público'),
    -- Café Cultural
    ('Convidados'),
    ('Livros e autógrafos'),
    ('Espaço e ambientação'),
    -- Arte ao Ar Livre
    ('Proposta da atividade'),
    ('Materiais'),
    -- Amostra de Talentos
    ('Inscrições'),
    ('Som e palco');
