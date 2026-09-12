<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class TransporteRepository
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
                origem,
                destino,
                data_solicitacao,
                data_transporte,
                horario,
                status,
                observacoes,
                created_at,
                updated_at
            FROM transportes
            WHERE evento_id = :evento_id
            ORDER BY data_transporte IS NULL, data_transporte ASC, id ASC
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
                origem,
                destino,
                data_solicitacao,
                data_transporte,
                horario,
                status,
                observacoes,
                created_at,
                updated_at
            FROM transportes
            WHERE id = :id
            LIMIT 1
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'id' => $id,
        ]);

        $transporte = $statement->fetch(PDO::FETCH_ASSOC);

        return $transporte !== false ? $transporte : null;
    }

    public function create(
        int $eventoId,
        string $tipo,
        ?string $origem,
        ?string $destino,
        ?string $dataSolicitacao,
        ?string $dataTransporte,
        ?string $horario,
        string $status,
        ?string $observacoes
    ): ?array {
        $sql = '
            INSERT INTO transportes (
                evento_id,
                tipo,
                origem,
                destino,
                data_solicitacao,
                data_transporte,
                horario,
                status,
                observacoes
            ) VALUES (
                :evento_id,
                :tipo,
                :origem,
                :destino,
                :data_solicitacao,
                :data_transporte,
                :horario,
                :status,
                :observacoes
            )
        ';

        $statement = $this->pdo->prepare($sql);

        $success = $statement->execute([
            'evento_id' => $eventoId,
            'tipo' => $tipo,
            'origem' => $origem,
            'destino' => $destino,
            'data_solicitacao' => $dataSolicitacao,
            'data_transporte' => $dataTransporte,
            'horario' => $horario,
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
        ?string $origem,
        ?string $destino,
        ?string $dataSolicitacao,
        ?string $dataTransporte,
        ?string $horario,
        string $status,
        ?string $observacoes
    ): ?array {
        $sql = '
            UPDATE transportes
            SET
                tipo = :tipo,
                origem = :origem,
                destino = :destino,
                data_solicitacao = :data_solicitacao,
                data_transporte = :data_transporte,
                horario = :horario,
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
            'origem' => $origem,
            'destino' => $destino,
            'data_solicitacao' => $dataSolicitacao,
            'data_transporte' => $dataTransporte,
            'horario' => $horario,
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
            DELETE FROM transportes
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
