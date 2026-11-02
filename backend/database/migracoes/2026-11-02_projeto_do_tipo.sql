-- ELOS — o projeto de cada tipo de evento fica gravado (2026-11-02)
--
-- Antes, o projeto de um evento (Cine Clube, Café Cultural...) era
-- adivinhado pelo nome do tipo: renomear "Cine Clube" para "Cinema"
-- fazia as sessões virarem exposições sem aviso. Agora cada tipo diz a
-- que projeto pertence; vazio = Exposições.
--
-- Rodar uma vez no banco que já existe (depois da 2026-10-29_projetos.sql):
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-11-02_projeto_do_tipo.sql

ALTER TABLE tipos_evento
    ADD COLUMN projeto VARCHAR(40) NULL AFTER cor;

-- Os tipos que já existem: o projeto pelo nome, uma última vez.
UPDATE tipos_evento SET projeto = 'cine-clube' WHERE LOWER(nome) LIKE '%cine%';
UPDATE tipos_evento SET projeto = 'cafe-cultural' WHERE LOWER(nome) LIKE '%café%' OR LOWER(nome) LIKE '%cafe%';
UPDATE tipos_evento SET projeto = 'arte-ao-ar-livre' WHERE LOWER(nome) LIKE '%ar livre%';
UPDATE tipos_evento SET projeto = 'amostra-de-talentos' WHERE LOWER(nome) LIKE '%amostra%' OR LOWER(nome) LIKE '%talento%';
