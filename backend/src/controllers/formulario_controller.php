<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\FormularioRepository;

final class FormularioController
{
    public function __construct(
        private readonly FormularioRepository $repository
    ) {
    }

    public function indexByEvento(int $eventoId): array
    {
        return $this->repository->findByEventoId($eventoId);
    }

    public function show(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function create(
        int $eventoId,
        string $tipo,
        ?string $dataPrevisao,
        ?string $dataEnvio,
        string $status,
        ?string $observacoes
    ): ?array {
        return $this->repository->create(
            $eventoId,
            $tipo,
            $dataPrevisao,
            $dataEnvio,
            $status,
            $observacoes
        );
    }

    public function update(
        int $id,
        int $eventoId,
        string $tipo,
        ?string $dataPrevisao,
        ?string $dataEnvio,
        string $status,
        ?string $observacoes
    ): ?array {
        return $this->repository->update(
            $id,
            $eventoId,
            $tipo,
            $dataPrevisao,
            $dataEnvio,
            $status,
            $observacoes
        );
    }

    public function delete(int $id, int $eventoId): bool
    {
        return $this->repository->delete($id, $eventoId);
    }

    public function isValidStatus(string $status): bool
    {
        return in_array(
            $status,
            [
                'PENDENTE',
                'EM_PREPARACAO',
                'ENVIADO',
                'ATRASADO',
                'CANCELADO',
            ],
            true
        );
    }
}