<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\EventoCursoRepository;

final class EventoCursoController
{
    public function __construct(
        private readonly EventoCursoRepository $repository
    ) {
    }

    /**
     * Lista os cursos associados a um evento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(int $eventoId): array
    {
        return $this->repository->findCoursesByEventId($eventoId);
    }

    /**
     * Associa um curso a um evento.
     */
    public function attach(
        int $eventoId,
        int $cursoId
    ): bool {
        if ($eventoId <= 0 || $cursoId <= 0) {
            return false;
        }

        return $this->repository->attach(
            $eventoId,
            $cursoId
        );
    }

    /**
     * Remove um curso de um evento.
     */
    public function detach(
        int $eventoId,
        int $cursoId
    ): bool {
        if ($eventoId <= 0 || $cursoId <= 0) {
            return false;
        }

        return $this->repository->detach(
            $eventoId,
            $cursoId
        );
    }
}