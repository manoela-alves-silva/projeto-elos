<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class CursoRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Retorna todos os cursos ativos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllActive(): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, ativo
             FROM cursos
             WHERE ativo = 1
             ORDER BY nome ASC'
        );

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um curso pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, ativo
             FROM cursos
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $id,
        ]);

        $curso = $statement->fetch(PDO::FETCH_ASSOC);

        return $curso !== false ? $curso : null;
    }

    /**
     * Cria um novo curso.
     *
     * @return array<string, mixed>|null
     */
    public function create(string $nome): ?array
    {
        $statement = $this->connection->prepare(
            'INSERT INTO cursos (nome, ativo)
             VALUES (:nome, 1)'
        );

        try {
            $statement->execute([
                'nome' => $nome,
            ]);
        } catch (\PDOException $exception) {
            if ((int) $exception->errorInfo[1] === 1062) {
                return null;
            }

            throw $exception;
        }

        return $this->findById(
            (int) $this->connection->lastInsertId()
        );
    }

    /**
     * Atualiza o nome de um curso.
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, string $nome): ?array
    {
        $statement = $this->connection->prepare(
            'UPDATE cursos
             SET nome = :nome
             WHERE id = :id'
        );

        try {
            $statement->execute([
                'id' => $id,
                'nome' => $nome,
            ]);
        } catch (\PDOException $exception) {
            if ((int) $exception->errorInfo[1] === 1062) {
                return null;
            }

            throw $exception;
        }

        if (
            $statement->rowCount() === 0
            && $this->findById($id) === null
        ) {
            return null;
        }

        return $this->findById($id);
    }

    /**
     * Ativa ou desativa um curso.
     *
     * @return array<string, mixed>|null
     */
    public function setActive(int $id, bool $ativo): ?array
    {
        $statement = $this->connection->prepare(
            'UPDATE cursos
             SET ativo = :ativo
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'ativo' => $ativo ? 1 : 0,
        ]);

        return $this->findById($id);
    }
}
