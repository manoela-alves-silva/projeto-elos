<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class LocalRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Retorna todos os locais ativos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllActive(): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, descricao, ativo
             FROM locais
             WHERE ativo = 1
             ORDER BY nome ASC'
        );

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um local pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, descricao, ativo
             FROM locais
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $id,
        ]);

        $local = $statement->fetch(PDO::FETCH_ASSOC);

        return $local !== false ? $local : null;
    }

    /**
     * Cria um novo local.
     *
     * @return array<string, mixed>|null
     */
    public function create(
        string $nome,
        ?string $descricao
    ): ?array {
        $statement = $this->connection->prepare(
            'INSERT INTO locais (nome, descricao, ativo)
             VALUES (:nome, :descricao, 1)'
        );

        try {
            $statement->execute([
                'nome' => $nome,
                'descricao' => $descricao,
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
     * Atualiza os dados de um local.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        string $nome,
        ?string $descricao
    ): ?array {
        $statement = $this->connection->prepare(
            'UPDATE locais
             SET nome = :nome,
                 descricao = :descricao
             WHERE id = :id'
        );

        try {
            $statement->execute([
                'id' => $id,
                'nome' => $nome,
                'descricao' => $descricao,
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
     * Ativa ou desativa um local.
     *
     * @return array<string, mixed>|null
     */
    public function setActive(int $id, bool $ativo): ?array
    {
        $statement = $this->connection->prepare(
            'UPDATE locais
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