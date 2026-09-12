<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class VisitaRepository
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
                instituicao,
                responsavel,
                quantidade_pessoas,
                data,
                horario,
                status,
                observacoes,
                created_at,
                updated_at
            FROM visitas
            WHERE evento_id = :evento_id
            ORDER BY data IS NULL, data ASC, horario IS NULL, horario ASC, id ASC
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
                instituicao,
                responsavel,
                quantidade_pessoas,
                data,
                horario,
                status,
                observacoes,
                created_at,
                updated_at
            FROM visitas
            WHERE id = :id
            LIMIT 1
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'id' => $id,
        ]);

        $visita = $statement->fetch(PDO::FETCH_ASSOC);

        return $visita !== false ? $visita : null;
    }

    public function create(
        int $eventoId,
        string $instituicao,
        string $responsavel,
        int $quantidadePessoas,
        ?string $data,
        ?string $horario,
        string $status,
        ?string $observacoes
    ): ?array {
        $sql = '
            INSERT INTO visitas (
                evento_id,
                instituicao,
                responsavel,
                quantidade_pessoas,
                data,
                horario,
                status,
                observacoes
            ) VALUES (
                :evento_id,
                :instituicao,
                :responsavel,
                :quantidade_pessoas,
                :data,
                :horario,
                :status,
                :observacoes
            )
        ';

        $statement = $this->pdo->prepare($sql);

        $success = $statement->execute([
            'evento_id' => $eventoId,
            'instituicao' => $instituicao,
            'responsavel' => $responsavel,
            'quantidade_pessoas' => $quantidadePessoas,
            'data' => $data,
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
        string $instituicao,
        string $responsavel,
        int $quantidadePessoas,
        ?string $data,
        ?string $horario,
        string $status,
        ?string $observacoes
    ): ?array {
        $sql = '
            UPDATE visitas
            SET
                instituicao = :instituicao,
                responsavel = :responsavel,
                quantidade_pessoas = :quantidade_pessoas,
                data = :data,
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
            'instituicao' => $instituicao,
            'responsavel' => $responsavel,
            'quantidade_pessoas' => $quantidadePessoas,
            'data' => $data,
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
            DELETE FROM visitas
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
