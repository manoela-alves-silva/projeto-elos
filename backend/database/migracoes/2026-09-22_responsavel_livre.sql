-- ELOS — responsável livre nas necessidades/tarefas (2026-09-22)
--
-- O responsável de uma necessidade pode ser qualquer pessoa, mesmo
-- sem conta no sistema (ex.: "João — marcenaria"). O nome fica em
-- responsavel_nome. Quando for alguém com conta, continua também em
-- usuario_responsavel_id (para essa pessoa poder marcar como concluído).
--
-- Rodar uma vez no banco que já existe:
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-09-22_responsavel_livre.sql

ALTER TABLE tarefas
    ADD COLUMN responsavel_nome VARCHAR(150) NULL AFTER usuario_responsavel_id;
