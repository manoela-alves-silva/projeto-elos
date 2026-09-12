<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\AnexoRepository;

final class AnexoController
{
    public function __construct(
        private readonly AnexoRepository $repository
    ) {
    }

    public function indexByEvento(int $eventoId): array
    {
        return $this->repository->findByEventoId($eventoId);
    }

    public function show(int $id, int $eventoId): ?array
    {
        $anexo = $this->repository->findById($id);

        if ($anexo === null || (int) $anexo['evento_id'] !== $eventoId) {
            return null;
        }

        return $anexo;
    }

    public function create(
        int $eventoId,
        string $nome,
        string $nomeOriginal,
        string $caminho,
        string $tipo,
        int $tamanho
    ): ?array {
        return $this->repository->create(
            $eventoId,
            $nome,
            $nomeOriginal,
            $caminho,
            $tipo,
            $tamanho
        );
    }

    public function delete(int $id, int $eventoId): bool
    {
        return $this->repository->delete(
            $id,
            $eventoId
        );
    }
}
