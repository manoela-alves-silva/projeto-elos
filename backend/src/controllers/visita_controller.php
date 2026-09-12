<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\VisitaRepository;

final class VisitaController
{
    public function __construct(
        private readonly VisitaRepository $repository
    ) {
    }

    public function indexByEvento(int $eventoId): array
    {
        return $this->repository->findByEventoId($eventoId);
    }

    public function show(int $id, int $eventoId): ?array
    {
        $visita = $this->repository->findById($id);

        if ($visita === null || (int) $visita['evento_id'] !== $eventoId) {
            return null;
        }

        return $visita;
    }

    public function create(
        int $eventoId,
        string $instituicao,
        string $responsavel,
        int $quantidadePessoas,
        ?string $data,
        ?string $horario,
        string $status,
        ?string $observacoes
    ): ?array {
        if (!$this->isValidStatus($status)) {
            return null;
        }

        return $this->repository->create(
            $eventoId,
            $instituicao,
            $responsavel,
            $quantidadePessoas,
            $data,
            $horario,
            $status,
            $observacoes
        );
    }

    public function update(
        int $id,
        int $eventoId,
        string $instituicao,
        string $responsavel,
        int $quantidadePessoas,
        ?string $data,
        ?string $horario,
        string $status,
        ?string $observacoes
    ): ?array {
        if (!$this->isValidStatus($status)) {
            return null;
        }

        return $this->repository->update(
            $id,
            $eventoId,
            $instituicao,
            $responsavel,
            $quantidadePessoas,
            $data,
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
                'AGENDADA',
                'REALIZADA',
                'CANCELADA',
            ],
            true
        );
    }
}
