-- ELOS — checklist único (2026-09-23)
--
-- Transportes, visitas e formulários passam a ser itens do checklist da
-- exposição, com a categoria dizendo o tipo. Para isso o item ganha um
-- horário opcional (ex.: visita às 14h, transporte às 8h) e entra a
-- categoria "Documentação" (formulários, termos, autorizações).
--
-- Rodar uma vez no banco que já existe:
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-09-23_checklist_unico.sql

ALTER TABLE tarefas
    ADD COLUMN horario TIME NULL AFTER prazo;

INSERT IGNORE INTO categorias_tarefa (nome) VALUES ('Documentação');
