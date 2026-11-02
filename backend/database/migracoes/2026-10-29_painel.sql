-- ELOS — painel ao vivo (2026-10-29)
--
-- O painel fica aberto num monitor da produção o dia todo. O monitor
-- entra por um link secreto, só de leitura, sem a conta de ninguém.
-- Aqui fica só o "resumo" (hash SHA-256) do código do link: quem lê o
-- banco não descobre o link. Gerar um link novo apaga o anterior.
--
-- Só cria uma tabela nova; nenhum dado é alterado.
--
-- Rodar uma vez no banco que já existe, ANTES de enviar o código novo:
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-10-29_painel.sql

CREATE TABLE IF NOT EXISTS painel_acesso (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_hash CHAR(64) NOT NULL UNIQUE,
    criado_por INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_painel_acesso_usuario FOREIGN KEY (criado_por)
        REFERENCES usuarios (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
