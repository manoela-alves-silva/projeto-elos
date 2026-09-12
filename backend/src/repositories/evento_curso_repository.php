<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class EventoCursoRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Lista os cursos associados a um evento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findCoursesByEventId(int $eventoId): array
    {
        $statement = $this->connection->prepare(
            'SELECT
                c.id,
                c.nome,
                c.ativo
             FROM evento_cursos ec
             INNER JOIN cursos c
                ON c.id = ec.curso_id
             WHERE ec.evento_id = :evento_id
             ORDER BY c.nome ASC'
        );

        $statement->execute([
            'evento_id' => $eventoId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se a associação entre evento e curso existe.
     */
    public function exists(
        int $eventoId,
        int $cursoId
    ): bool {
        $statement = $this->connection->prepare(
            'SELECT 1
             FROM evento_cursos
             WHERE evento_id = :evento_id
               AND curso_id = :curso_id
             LIMIT 1'
        );

        $statement->execute([
            'evento_id' => $eventoId,
            'curso_id' => $cursoId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Cria uma associação entre evento e curso.
     */
    public function attach(
        int $eventoId,
        int $cursoId
    ): bool {
        if ($this->exists($eventoId, $cursoId)) {
            return false;
        }

        $statement = $this->connection->prepare(
            'INSERT INTO evento_cursos (
                evento_id,
                curso_id
             ) VALUES (
                :evento_id,
                :curso_id
             )'
        );

        $statement->execute([
            'evento_id' => $eventoId,
            'curso_id' => $cursoId,
        ]);

        return true;
    }

    /**
     * Remove uma associação entre evento e curso.
     */
    public function detach(
        int $eventoId,
        int $cursoId
    ): bool {
        $statement = $this->connection->prepare(
            'DELETE FROM evento_cursos
             WHERE evento_id = :evento_id
               AND curso_id = :curso_id'
        );

        $statement->execute([
            'evento_id' => $eventoId,
            'curso_id' => $cursoId,
        ]);

        return $statement->rowCount() > 0;
    }
}