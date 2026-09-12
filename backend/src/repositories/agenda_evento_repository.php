<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class AgendaEventoRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Busca a agenda de um evento.
     *
     * @return array<string, mixed>|null
     */
    public function findByEventId(int $eventoId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT
                id,
                evento_id,
                montagem_inicio,
                montagem_fim,
                abertura,
                horario,
                permanencia_inicio,
                permanencia_fim,
                desmontagem_inicio,
                desmontagem_fim,
                tipo_horario,
                observacoes
             FROM agenda_eventos
             WHERE evento_id = :evento_id
             LIMIT 1'
        );

        $statement->execute([
            'evento_id' => $eventoId,
        ]);

        $agenda = $statement->fetch(PDO::FETCH_ASSOC);

        return $agenda !== false ? $agenda : null;
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
        $statement = $this->connection->prepare(
            'INSERT INTO agenda_eventos (
                evento_id,
                montagem_inicio,
                montagem_fim,
                abertura,
                horario,
                permanencia_inicio,
                permanencia_fim,
                desmontagem_inicio,
                desmontagem_fim,
                tipo_horario,
                observacoes
             ) VALUES (
                :evento_id,
                :montagem_inicio,
                :montagem_fim,
                :abertura,
                :horario,
                :permanencia_inicio,
                :permanencia_fim,
                :desmontagem_inicio,
                :desmontagem_fim,
                :tipo_horario,
                :observacoes
             )'
        );

        $statement->execute([
            'evento_id' => $eventoId,
            'montagem_inicio' => $montagemInicio,
            'montagem_fim' => $montagemFim,
            'abertura' => $abertura,
            'horario' => $horario,
            'permanencia_inicio' => $permanenciaInicio,
            'permanencia_fim' => $permanenciaFim,
            'desmontagem_inicio' => $desmontagemInicio,
            'desmontagem_fim' => $desmontagemFim,
            'tipo_horario' => $tipoHorario,
            'observacoes' => $observacoes,
        ]);

        return $this->findByEventId($eventoId);
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
        $statement = $this->connection->prepare(
            'UPDATE agenda_eventos
             SET
                montagem_inicio = :montagem_inicio,
                montagem_fim = :montagem_fim,
                abertura = :abertura,
                horario = :horario,
                permanencia_inicio = :permanencia_inicio,
                permanencia_fim = :permanencia_fim,
                desmontagem_inicio = :desmontagem_inicio,
                desmontagem_fim = :desmontagem_fim,
                tipo_horario = :tipo_horario,
                observacoes = :observacoes
             WHERE evento_id = :evento_id'
        );

        $statement->execute([
            'evento_id' => $eventoId,
            'montagem_inicio' => $montagemInicio,
            'montagem_fim' => $montagemFim,
            'abertura' => $abertura,
            'horario' => $horario,
            'permanencia_inicio' => $permanenciaInicio,
            'permanencia_fim' => $permanenciaFim,
            'desmontagem_inicio' => $desmontagemInicio,
            'desmontagem_fim' => $desmontagemFim,
            'tipo_horario' => $tipoHorario,
            'observacoes' => $observacoes,
        ]);

        return $this->findByEventId($eventoId);
    }
}