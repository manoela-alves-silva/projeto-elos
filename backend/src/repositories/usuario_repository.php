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

    /**
     * Retorna todos os usuários ativos, sem o hash de senha.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllActive(): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, email, perfil, ativo, created_at, updated_at
             FROM usuarios
             WHERE ativo = 1
             ORDER BY nome ASC'
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, email, senha, codigo_recuperacao, perfil, ativo, created_at, updated_at
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

    /**
     * Quantos usuários ativos podem gerenciar o sistema (GESTOR ou ADMIN),
     * opcionalmente sem contar um usuário.
     */
    public function countGestoresAtivos(?int $excetoId = null): int
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
             FROM usuarios
             WHERE ativo = 1
               AND perfil IN ('GESTOR', 'ADMIN')
               AND id <> :exceto_id"
        );
        $statement->execute(['exceto_id' => $excetoId ?? 0]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Contas criadas no cadastro que ainda esperam um gestor aprovar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findPendentes(): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, email, perfil, ativo, created_at, updated_at
             FROM usuarios
             WHERE ativo = 0
             ORDER BY created_at ASC'
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    public function aprovar(int $id): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE usuarios SET ativo = 1 WHERE id = :id AND ativo = 0'
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * Apaga uma conta pendente (recusada). Contas já aprovadas não são
     * tocadas: elas podem ter tarefas e histórico ligados a elas.
     */
    public function deletePendente(int $id): bool
    {
        $statement = $this->connection->prepare(
            'DELETE FROM usuarios WHERE id = :id AND ativo = 0'
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * Apaga uma conta aprovada. Antes, o nome da pessoa vai para
     * tarefas.responsavel_nome, para as tarefas dela continuarem
     * mostrando quem era o responsável (o vínculo vira NULL pela FK).
     */
    public function deleteAtivo(int $id): bool
    {
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                'UPDATE tarefas t
                 INNER JOIN usuarios u ON u.id = t.usuario_responsavel_id
                 SET t.responsavel_nome = COALESCE(NULLIF(t.responsavel_nome, \'\'), u.nome)
                 WHERE t.usuario_responsavel_id = :id'
            );
            $statement->execute(['id' => $id]);

            $statement = $this->connection->prepare(
                'DELETE FROM usuarios WHERE id = :id AND ativo = 1'
            );
            $statement->execute(['id' => $id]);
            $apagou = $statement->rowCount() > 0;

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }

        return $apagou;
    }

    public function updatePerfil(int $id, string $perfil): void
    {
        $statement = $this->connection->prepare(
            'UPDATE usuarios SET perfil = :perfil WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'perfil' => $perfil,
        ]);
    }

    public function updateSenha(int $id, string $senhaHash): void
    {
        $statement = $this->connection->prepare(
            'UPDATE usuarios SET senha = :senha WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'senha' => $senhaHash,
        ]);
    }

    /**
     * Grava o hash do código de recuperação (null apaga o código).
     */
    public function updateCodigoRecuperacao(int $id, ?string $codigoHash): void
    {
        $statement = $this->connection->prepare(
            'UPDATE usuarios SET codigo_recuperacao = :codigo WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'codigo' => $codigoHash,
        ]);
    }

    public function create(
        string $nome,
        string $email,
        string $senhaHash,
        string $perfil = 'COLABORADOR',
        bool $ativo = true
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO usuarios (nome, email, senha, perfil, ativo)
             VALUES (:nome, :email, :senha, :perfil, :ativo)'
        );
        $statement->execute([
            'nome' => $nome,
            'email' => $email,
            'senha' => $senhaHash,
            'perfil' => $perfil,
            'ativo' => $ativo ? 1 : 0,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
