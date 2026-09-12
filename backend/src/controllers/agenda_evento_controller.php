<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\AgendaEventoRepository;

final class AgendaEventoController
{
    public function __construct(
        private readonly AgendaEventoRepository $repository
    ) {
    }

    /**
     * Busca a agenda de um evento.
     *
     * @return array<string, mixed>|null
     */
    public function show(int $eventoId): ?array
    {
        if ($eventoId <= 0) {
            return null;
        }

        return $this->repository->findByEventId($eventoId);
    }

    /**
     * Cria a agenda de um evento.
     *
     * @return array<string, mixed>|null
     */
    public function create(
        int $eventoId,
        ?string $montagemInicio,
        ?string $montagemFim,
        ?string $abertura,
        ?string $horario,
        ?string $permanenciaInicio,
        ?string $permanenciaFim,
        ?string $desmontagemInicio,
        ?string $desmontagemFim,
        ?string $tipoHorario,
        ?string $observacoes
    ): ?array {
        if ($eventoId <= 0) {
            return null;
        }

        return $this->repository->create(
            $eventoId,
            $montagemInicio,
            $montagemFim,
            $abertura,
            $horario,
            $permanenciaInicio,
            $permanenciaFim,
            $desmontagemInicio,
            $desmontagemFim,
            $tipoHorario,
            $observacoes
        );
    }

    /**
     * Atualiza a agenda de um evento.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $eventoId,
        ?string $montagemInicio,
        ?string $montagemFim,
        ?string $abertura,
        ?string $horario,
        ?string $permanenciaInicio,
        ?string $permanenciaFim,
        ?string $desmontagemInicio,
        ?string $desmontagemFim,
        ?string $tipoHorario,
        ?string $observacoes
    ): ?array {
        if ($eventoId <= 0) {
            return null;
        }

        return $this->repository->update(
            $eventoId,
            $montagemInicio,
            $montagemFim,
            $abertura,
            $horario,
            $permanenciaInicio,
            $permanenciaFim,
            $desmontagemInicio,
            $desmontagemFim,
            $tipoHorario,
            $observacoes
        );
    }
}