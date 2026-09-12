<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class ResponsavelRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Retorna todos os responsáveis ativos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllActive(): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, tipo, email, telefone, observacoes, ativo
             FROM responsaveis
             WHERE ativo = 1
             ORDER BY nome ASC'
        );

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um responsável pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, tipo, email, telefone, observacoes, ativo
             FROM responsaveis
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $id,
        ]);

        $responsavel = $statement->fetch(PDO::FETCH_ASSOC);

        return $responsavel !== false ? $responsavel : null;
    }

    /**
     * Cria um novo responsável.
     *
     * @return array<string, mixed>|null
     */
    public function create(
        string $nome,
        string $tipo,
        ?string $email,
        ?string $telefone,
        ?string $observacoes
    ): ?array {
        $statement = $this->connection->prepare(
            'INSERT INTO responsaveis
                (nome, tipo, email, telefone, observacoes, ativo)
             VALUES
                (:nome, :tipo, :email, :telefone, :observacoes, 1)'
        );

        $statement->execute([
            'nome' => $nome,
            'tipo' => $tipo,
            'email' => $email,
            'telefone' => $telefone,
            'observacoes' => $observacoes,
        ]);

        return $this->findById(
            (int) $this->connection->lastInsertId()
        );
    }

    /**
     * Atualiza os dados de um responsável.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        string $nome,
        string $tipo,
        ?string $email,
        ?string $telefone,
        ?string $observacoes
    ): ?array {
        $statement = $this->connection->prepare(
            'UPDATE responsaveis
             SET nome = :nome,
                 tipo = :tipo,
                 email = :email,
                 telefone = :telefone,
                 observacoes = :observacoes
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'nome' => $nome,
            'tipo' => $tipo,
            'email' => $email,
            'telefone' => $telefone,
            'observacoes' => $observacoes,
        ]);

        if (
            $statement->rowCount() === 0
            && $this->findById($id) === null
        ) {
            return null;
        }

        return $this->findById($id);
    }

    /**
     * Ativa ou desativa um responsável.
     *
     * @return array<string, mixed>|null
     */
    public function setActive(int $id, bool $ativo): ?array
    {
        $statement = $this->connection->prepare(
            'UPDATE responsaveis
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