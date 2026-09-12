<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class AnexoRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByEventoId(int $eventoId): array
    {
        $sql = '
            SELECT
                id,
                evento_id,
                nome,
                nome_original,
                caminho,
                tipo,
                tamanho,
                created_at
            FROM anexos
            WHERE evento_id = :evento_id
            ORDER BY created_at DESC, id DESC
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'evento_id' => $eventoId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $sql = '
            SELECT
                id,
                evento_id,
                nome,
                nome_original,
                caminho,
                tipo,
                tamanho,
                created_at
            FROM anexos
            WHERE id = :id
            LIMIT 1
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'id' => $id,
        ]);

        $anexo = $statement->fetch(PDO::FETCH_ASSOC);

        return $anexo !== false ? $anexo : null;
    }

    public function create(
        int $eventoId,
        string $nome,
        string $nomeOriginal,
        string $caminho,
        string $tipo,
        int $tamanho
    ): ?array {
        $sql = '
            INSERT INTO anexos (
                evento_id,
                nome,
                nome_original,
                caminho,
                tipo,
                tamanho
            ) VALUES (
                :evento_id,
                :nome,
                :nome_original,
                :caminho,
                :tipo,
                :tamanho
            )
        ';

        $statement = $this->pdo->prepare($sql);

        $success = $statement->execute([
            'evento_id' => $eventoId,
            'nome' => $nome,
            'nome_original' => $nomeOriginal,
            'caminho' => $caminho,
            'tipo' => $tipo,
            'tamanho' => $tamanho,
        ]);

        if (!$success) {
            return null;
        }

        return $this->findById((int) $this->pdo->lastInsertId());
    }

    public function delete(int $id, int $eventoId): bool
    {
        $sql = '
            DELETE FROM anexos
            WHERE id = :id
              AND evento_id = :evento_id
        ';

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            'id' => $id,
            'evento_id' => $eventoId,
        ]);

        return $statement->rowCount() > 0;
    }
}
