-- ELOS — endereço de cada evento gravado (2026-11-02)
--
-- O endereço (/exposicao/memorias-do-rio-antigo) vem do nome. Antes ele
-- era calculado na hora: mudar o nome quebrava os links já salvos ou
-- compartilhados. Agora o endereço atual fica em eventos.slug e todos os
-- anteriores em eventos_enderecos: um link antigo leva ao evento certo.
-- Os eventos que já existem ganham o endereço sozinhos, na primeira vez
-- que a lista de eventos é pedida.
--
-- Rodar uma vez no banco que já existe:
--   mysql -u USUARIO -p elos_db < backend/database/migracoes/2026-11-02_enderecos.sql

ALTER TABLE eventos
    ADD COLUMN slug VARCHAR(220) NULL AFTER titulo,
    ADD UNIQUE KEY uq_eventos_slug (slug);

CREATE TABLE IF NOT EXISTS eventos_enderecos (
    slug VARCHAR(220) NOT NULL,
    evento_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (slug),
    INDEX idx_eventos_enderecos_evento (evento_id),
    CONSTRAINT fk_eventos_enderecos_evento FOREIGN KEY (evento_id)
        REFERENCES eventos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
