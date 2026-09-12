<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

/**
 * Centraliza exclusivamente o acesso a dados da tabela usuarios.
 */
final class UsuarioRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, email, senha, perfil, ativo, created_at, updated_at
             FROM usuarios
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);

        $usuario = $statement->fetch();

        return $usuario === false ? null : $usuario;
    }

    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, email, senha, perfil, ativo, created_at, updated_at
             FROM usuarios
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $usuario = $statement->fetch();

        return $usuario === false ? null : $usuario;
    }

    public function create(
        string $nome,
        string $email,
        string $senhaHash,
        string $perfil = 'COLABORADOR'
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO usuarios (nome, email, senha, perfil)
             VALUES (:nome, :email, :senha, :perfil)'
        );
        $statement->execute([
            'nome' => $nome,
            'email' => $email,
            'senha' => $senhaHash,
            'perfil' => $perfil,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
