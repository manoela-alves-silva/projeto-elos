-- ELOS — código de recuperação de senha (2026-10-14)
--
-- Quem esquece a senha cria uma nova com o próprio e-mail e um código
-- que recebeu ao criar a conta (ou gerou em Minha conta). O código fica
-- guardado com hash, como a senha, e vale uma vez só.
-- Contas que já existem ficam sem código (NULL) até gerarem um.
--
-- Rodar uma vez no banco que já existe:
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-10-14_codigo_recuperacao.sql

ALTER TABLE usuarios
    ADD COLUMN codigo_recuperacao VARCHAR(255) NULL AFTER senha;
