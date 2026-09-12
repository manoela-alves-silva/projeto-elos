<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class EtapaRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Lista todas as etapas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $statement = $this->connection->query(
            'SELECT
                id,
                nome,
                ordem,
                ativa
             FROM etapas
             ORDER BY ordem ASC, id ASC'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca uma etapa pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT
                id,
                nome,
                ordem,
                ativa
             FROM etapas
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $id,
        ]);

        $etapa = $statement->fetch(PDO::FETCH_ASSOC);

        return $etapa !== false ? $etapa : null;
    }

    /**
     * Cria uma etapa.
     *
     * @return array<string, mixed>|null
     */
    public function create(
        string $nome,
        int $ordem
    ): ?array {
        $statement = $this->connection->prepare(
            'INSERT INTO etapas (
                nome,
                ordem
             ) VALUES (
                :nome,
                :ordem
             )'
        );

        $statement->execute([
            'nome' => $nome,
            'ordem' => $ordem,
        ]);

        return $this->findById((int) $this->connection->lastInsertId());
    }

    /**
     * Atualiza uma etapa.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        string $nome,
        int $ordem
    ): ?array {
        $statement = $this->connection->prepare(
            'UPDATE etapas
             SET
                nome = :nome,
                ordem = :ordem
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'nome' => $nome,
            'ordem' => $ordem,
        ]);

        return $this->findById($id);
    }

    /**
     * Ativa ou desativa uma etapa.
     *
     * @return array<string, mixed>|null
     */
    public function setActive(
        int $id,
        bool $ativa
    ): ?array {
        $statement = $this->connection->prepare(
            'UPDATE etapas
             SET ativa = :ativa
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'ativa' => $ativa ? 1 : 0,
        ]);

        return $this->findById($id);
    }
}