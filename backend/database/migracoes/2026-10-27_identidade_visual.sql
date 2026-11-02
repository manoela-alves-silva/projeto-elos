-- ELOS — identidade visual da exposição (2026-10-27)
--
-- Cada exposição pode ter uma imagem de capa e uma cor. A capa aparece
-- nos cards (Início, Exposições e topo da exposição); a cor é usada nos
-- detalhes e quando não há imagem. As duas colunas começam vazias: as
-- exposições que já existem continuam iguais (ganham uma cor automática
-- a partir do nome até alguém escolher outra).
--
-- Só acrescenta colunas; nenhum dado é alterado.
--
-- Rodar uma vez no banco que já existe, ANTES de enviar o código novo:
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-10-27_identidade_visual.sql

ALTER TABLE eventos
    ADD COLUMN imagem_capa VARCHAR(100) NULL AFTER observacoes,
    ADD COLUMN cor CHAR(7) NULL AFTER imagem_capa;
