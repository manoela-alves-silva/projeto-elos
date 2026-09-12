<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class TarefaRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Lista todas as tarefas de um evento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByEventoId(int $eventoId): array
    {
        $sql = '
            SELECT
                t.id,
                t.evento_id,
                t.usuario_responsavel_id,
                t.etapa_id,
                t.categoria_id,
                t.titulo,
                t.descricao,
                t.prazo,
                t.prioridade,
                t.status,
                t.observacoes,
                t.created_at,
                t.updated_at,
                u.nome AS usuario_responsavel_nome,
                e.nome AS etapa_nome,
                c.nome AS categoria_nome
            FROM tarefas t
            LEFT JOIN usuarios u
                ON u.id = t.usuario_responsavel_id
            LEFT JOIN etapas e
                ON e.id = t.etapa_id
            LEFT JOIN categorias_tarefa c
                ON c.id = t.categoria_id
            WHERE t.evento_id = :evento_id
            ORDER BY
                t.prazo IS NULL,
                t.prazo ASC,
                t.id ASC
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'evento_id' => $eventoId,
        ]);

        return $statement->fetchAll();
    }

    /**
     * Busca uma tarefa pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = '
            SELECT
                t.id,
                t.evento_id,
                t.usuario_responsavel_id,
                t.etapa_id,
                t.categoria_id,
                t.titulo,
                t.descricao,
                t.prazo,
                t.prioridade,
                t.status,
                t.observacoes,
                t.created_at,
                t.updated_at,
                u.nome AS usuario_responsavel_nome,
                e.nome AS etapa_nome,
                c.nome AS categoria_nome
            FROM tarefas t
            LEFT JOIN usuarios u
                ON u.id = t.usuario_responsavel_id
            LEFT JOIN etapas e
                ON e.id = t.etapa_id
            LEFT JOIN categorias_tarefa c
                ON c.id = t.categoria_id
            WHERE t.id = :id
            LIMIT 1
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'id' => $id,
        ]);

        $tarefa = $statement->fetch();

        return $tarefa !== false ? $tarefa : null;
    }

    /**
     * Cria uma tarefa.
     */
    public function create(
        int $eventoId,
        ?int $usuarioResponsavelId,
        int $etapaId,
        ?int $categoriaId,
        string $titulo,
        ?string $descricao,
        ?string $prazo,
        string $prioridade,
        string $status,
        ?string $observacoes
    ): ?array {
        $sql = '
            INSERT INTO tarefas (
                evento_id,
                usuario_responsavel_id,
                etapa_id,
                categoria_id,
                titulo,
                descricao,
                prazo,
                prioridade,
                status,
                observacoes
            ) VALUES (
                :evento_id,
                :usuario_responsavel_id,
                :etapa_id,
                :categoria_id,
                :titulo,
                :descricao,
                :prazo,
                :prioridade,
                :status,
                :observacoes
            )
        ';

        $statement = $this->pdo->prepare($sql);

        $success = $statement->execute([
            'evento_id' => $eventoId,
            'usuario_responsavel_id' => $usuarioResponsavelId,
            'etapa_id' => $etapaId,
            'categoria_id' => $categoriaId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'prazo' => $prazo,
            'prioridade' => $prioridade,
            'status' => $status,
            'observacoes' => $observacoes,
        ]);

        if (!$success) {
            return null;
        }

        return $this->findById((int) $this->pdo->lastInsertId());
    }

    /**
     * Atualiza uma tarefa.
     */
    public function update(
        int $id,
        int $eventoId,
        ?int $usuarioResponsavelId,
        int $etapaId,
        ?int $categoriaId,
        string $titulo,
        ?string $descricao,
        ?string $prazo,
        string $prioridade,
        string $status,
        ?string $observacoes
    ): ?array {
        $sql = '
            UPDATE tarefas
            SET
                usuario_responsavel_id = :usuario_responsavel_id,
                etapa_id = :etapa_id,
                categoria_id = :categoria_id,
                titulo = :titulo,
                descricao = :descricao,
                prazo = :prazo,
                prioridade = :prioridade,
                status = :status,
                observacoes = :observacoes
            WHERE id = :id
              AND evento_id = :evento_id
        ';

        $statement = $this->pdo->prepare($sql);

        $success = $statement->execute([
            'id' => $id,
            'evento_id' => $eventoId,
            'usuario_responsavel_id' => $usuarioResponsavelId,
            'etapa_id' => $etapaId,
            'categoria_id' => $categoriaId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'prazo' => $prazo,
            'prioridade' => $prioridade,
            'status' => $status,
            'observacoes' => $observacoes,
        ]);

        if (!$success) {
            return null;
        }

        return $this->findById($id);
    }

    /**
     * Exclui uma tarefa.
     */
    public function delete(int $id, int $eventoId): bool
    {
        $sql = '
            DELETE FROM tarefas
            WHERE id = :id
              AND evento_id = :evento_id
        ';

        $statement = $this->pdo->prepare($sql);

        return $statement->execute([
            'id' => $id,
            'evento_id' => $eventoId,
        ]);
    }
}
