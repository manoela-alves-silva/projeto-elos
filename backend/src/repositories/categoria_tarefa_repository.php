<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class CategoriaTarefaRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Lista todas as categorias de tarefa.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $statement = $this->connection->query(
            'SELECT
                id,
                nome,
                ativa
             FROM categorias_tarefa
             ORDER BY nome ASC, id ASC'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca uma categoria pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT
                id,
                nome,
                ativa
             FROM categorias_tarefa
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $id,
        ]);

        $categoria = $statement->fetch(PDO::FETCH_ASSOC);

        return $categoria !== false ? $categoria : null;
    }

    /**
     * Cria uma categoria de tarefa.
     *
     * @return array<string, mixed>|null
     */
    public function create(string $nome): ?array
    {
        $statement = $this->connection->prepare(
            'INSERT INTO categorias_tarefa (
                nome
             ) VALUES (
                :nome
             )'
        );

        $statement->execute([
            'nome' => $nome,
        ]);

        return $this->findById(
            (int) $this->connection->lastInsertId()
        );
    }

    /**
     * Atualiza uma categoria de tarefa.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        string $nome
    ): ?array {
        $statement = $this->connection->prepare(
            'UPDATE categorias_tarefa
             SET nome = :nome
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'nome' => $nome,
        ]);

        return $this->findById($id);
    }

    /**
     * Ativa ou desativa uma categoria de tarefa.
     *
     * @return array<string, mixed>|null
     */
    public function setActive(
        int $id,
        bool $ativa
    ): ?array {
        $statement = $this->connection->prepare(
            'UPDATE categorias_tarefa
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