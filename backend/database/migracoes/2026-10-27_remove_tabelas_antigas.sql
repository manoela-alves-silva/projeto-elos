-- ELOS — remove o que ficou sem uso (2026-10-27)
--
-- Transportes, visitas e formulários viraram itens do checklist em
-- 2026-09-23 (com a categoria dizendo o tipo); as etapas deram lugar às
-- fases, que saem da categoria de cada item; e a exposição deixou de ter
-- prioridade (só os itens do checklist têm). As tabelas e colunas abaixo
-- ficaram sem uso.
--
-- ATENÇÃO: apaga tabelas. Faça backup antes e confira que estão vazias:
--   SELECT (SELECT COUNT(*) FROM formularios), (SELECT COUNT(*) FROM transportes),
--          (SELECT COUNT(*) FROM visitas), (SELECT COUNT(*) FROM tarefas WHERE etapa_id IS NOT NULL);
--
-- Rodar uma vez no banco que já existe, DEPOIS de enviar o código novo
-- (o código antigo ainda lê essas colunas):
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-10-27_remove_tabelas_antigas.sql

DROP TABLE IF EXISTS formularios;
DROP TABLE IF EXISTS transportes;
DROP TABLE IF EXISTS visitas;

ALTER TABLE tarefas
    DROP FOREIGN KEY fk_tarefas_etapa,
    DROP INDEX idx_tarefas_etapa,
    DROP COLUMN etapa_id;

DROP TABLE IF EXISTS etapas;

ALTER TABLE eventos
    DROP COLUMN prioridade;
