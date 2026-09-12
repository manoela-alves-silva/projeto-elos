<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class HistoricoRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByEventoId(int $eventoId): array
    {
        $sql = '
            SELECT
                h.id,
                h.evento_id,
                h.usuario_id,
                u.nome AS usuario_nome,
                h.acao,
                h.descricao,
                h.created_at
            FROM historico h
            LEFT JOIN usuarios u
                ON u.id = h.usuario_id
            WHERE h.evento_id = :evento_id
            ORDER BY h.created_at DESC, h.id DESC
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'evento_id' => $eventoId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(
        int $eventoId,
        ?int $usuarioId,
        string $acao,
        ?string $descricao
    ): ?array {
        $sql = '
            INSERT INTO historico (
                evento_id,
                usuario_id,
                acao,
                descricao
            ) VALUES (
                :evento_id,
                :usuario_id,
                :acao,
                :descricao
            )
        ';

        $statement = $this->pdo->prepare($sql);

        $success = $statement->execute([
            'evento_id' => $eventoId,
            'usuario_id' => $usuarioId,
            'acao' => $acao,
            'descricao' => $descricao,
        ]);

        if (!$success) {
            return null;
        }

        $id = (int) $this->pdo->lastInsertId();

        $selectSql = '
            SELECT
                h.id,
                h.evento_id,
                h.usuario_id,
                u.nome AS usuario_nome,
                h.acao,
                h.descricao,
                h.created_at
            FROM historico h
            LEFT JOIN usuarios u
                ON u.id = h.usuario_id
            WHERE h.id = :id
            LIMIT 1
        ';

        $selectStatement = $this->pdo->prepare($selectSql);
        $selectStatement->execute([
            'id' => $id,
        ]);

        $historico = $selectStatement->fetch(PDO::FETCH_ASSOC);

        return $historico !== false ? $historico : null;
    }
}
