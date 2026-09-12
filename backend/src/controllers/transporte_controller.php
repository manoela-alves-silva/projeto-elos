<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\TransporteRepository;

final class TransporteController
{
    public function __construct(
        private readonly TransporteRepository $repository
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
        ?string $origem,
        ?string $destino,
        ?string $dataSolicitacao,
        ?string $dataTransporte,
        ?string $horario,
        string $status,
        ?string $observacoes
    ): ?array {
        return $this->repository->create(
            $eventoId,
            $tipo,
            $origem,
            $destino,
            $dataSolicitacao,
            $dataTransporte,
            $horario,
            $status,
            $observacoes
        );
    }

    public function update(
        int $id,
        int $eventoId,
        string $tipo,
        ?string $origem,
        ?string $destino,
        ?string $dataSolicitacao,
        ?string $dataTransporte,
        ?string $horario,
        string $status,
        ?string $observacoes
    ): ?array {
        return $this->repository->update(
            $id,
            $eventoId,
            $tipo,
            $origem,
            $destino,
            $dataSolicitacao,
            $dataTransporte,
            $horario,
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
                'NAO_SOLICITADO',
                'SOLICITADO',
                'AGENDADO',
                'REALIZADO',
                'CANCELADO',
            ],
            true
        );
    }
}
