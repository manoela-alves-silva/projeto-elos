<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class FormularioRepository
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
                tipo,
                data_previsao,
                data_envio,
                status,
                observacoes,
                created_at,
                updated_at
            FROM formularios
            WHERE evento_id = :evento_id
            ORDER BY data_previsao IS NULL, data_previsao ASC, id ASC
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
                tipo,
                data_previsao,
                data_envio,
                status,
                observacoes,
                created_at,
                updated_at
            FROM formularios
            WHERE id = :id
            LIMIT 1
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'id' => $id,
        ]);

        $formulario = $statement->fetch(PDO::FETCH_ASSOC);

        return $formulario !== false ? $formulario : null;
    }

    public function create(
        int $eventoId,
        string $tipo,
        ?string $dataPrevisao,
        ?string $dataEnvio,
        string $status,
        ?string $observacoes
    ): ?array {
        $sql = '
            INSERT INTO formularios (
                evento_id,
                tipo,
                data_previsao,
                data_envio,
                status,
                observacoes
            ) VALUES (
                :evento_id,
                :tipo,
                :data_previsao,
                :data_envio,
                :status,
                :observacoes
            )
        ';

        $statement = $this->pdo->prepare($sql);

        $success = $statement->execute([
            'evento_id' => $eventoId,
            'tipo' => $tipo,
            'data_previsao' => $dataPrevisao,
            'data_envio' => $dataEnvio,
            'status' => $status,
            'observacoes' => $observacoes,
        ]);

        if (!$success) {
            return null;
        }

        return $this->findById((int) $this->pdo->lastInsertId());
    }

    public function update(
        int $id,
        int $eventoId,
        string $tipo,
        ?string $dataPrevisao,
        ?string $dataEnvio,
        string $status,
        ?string $observacoes
    ): ?array {
        $sql = '
            UPDATE formularios
            SET
                tipo = :tipo,
                data_previsao = :data_previsao,
                data_envio = :data_envio,
                status = :status,
                observacoes = :observacoes
            WHERE id = :id
              AND evento_id = :evento_id
        ';

        $statement = $this->pdo->prepare($sql);

        $success = $statement->execute([
            'id' => $id,
            'evento_id' => $eventoId,
            'tipo' => $tipo,
            'data_previsao' => $dataPrevisao,
            'data_envio' => $dataEnvio,
            'status' => $status,
            'observacoes' => $observacoes,
        ]);

        if (!$success) {
            return null;
        }

        return $this->findById($id);
    }

    public function delete(int $id, int $eventoId): bool
    {
        $sql = '
            DELETE FROM formularios
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
