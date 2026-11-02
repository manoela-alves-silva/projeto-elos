-- ELOS — item do checklist que não precisa de prazo (2026-10-29)
--
-- Alguns itens não têm data para acontecer (ex.: "manter contato com o
-- artista"). Marcados como "Não precisa de prazo", eles deixam de
-- aparecer no lembrete de itens sem prazo do painel ao vivo.
-- Começa desmarcado em todos os itens.
--
-- Só acrescenta uma coluna; nenhum dado é alterado.
--
-- Rodar uma vez no banco que já existe, ANTES de enviar o código novo:
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-10-29_prazo_dispensado.sql

ALTER TABLE tarefas
    ADD COLUMN prazo_dispensado TINYINT(1) NOT NULL DEFAULT 0 AFTER prazo;
