<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\HistoricoRepository;

final class HistoricoController
{
    public function __construct(
        private readonly HistoricoRepository $repository
    ) {
    }

    public function indexByEvento(int $eventoId): array
    {
        return $this->repository->findByEventoId($eventoId);
    }

    public function create(
        int $eventoId,
        ?int $usuarioId,
        string $acao,
        ?string $descricao
    ): ?array {
        return $this->repository->create(
            $eventoId,
            $usuarioId,
            $acao,
            $descricao
        );
    }
}
