-- ELOS — cor de cada tipo de evento (2026-10-29)
--
-- Cada tipo de evento (Exposição de Arte, Cine Clube, Café Cultural...)
-- ganha uma cor própria, para a equipe não confundir um com o outro no
-- painel ao vivo. Tipo sem cor usa uma cor automática.
--
-- Só acrescenta uma coluna e preenche a cor do tipo que já existe.
--
-- Rodar uma vez no banco que já existe, ANTES de enviar o código novo:
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-10-29_cor_tipo_evento.sql

ALTER TABLE tipos_evento
    ADD COLUMN cor CHAR(7) NULL AFTER nome;

UPDATE tipos_evento
SET cor = '#2b6cb0'
WHERE cor IS NULL AND nome LIKE 'Exposi%';
