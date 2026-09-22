<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\EventoRepository;

final class EventoController
{
    private const PRIORIDADES = [
        'BAIXA',
        'MEDIA',
        'ALTA',
    ];

    private const STATUS = [
        'PLANEJAMENTO',
        'EM_ANDAMENTO',
        'CONCLUIDO',
        'CANCELADO',
    ];

    public function __construct(
        private readonly EventoRepository $repository
    ) {
    }

    /**
     * Lista todos os eventos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Busca um evento pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function show(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Cria um novo evento.
     *
     * @return array<string, mixed>|null
     */
    public function create(
        int $tipoEventoId,
        int $responsavelId,
        int $localId,
        string $titulo,
        ?string $descricao,
        string $prioridade,
        string $status,
        ?string $observacoes
    ): ?array {
        $titulo = trim($titulo);
        $prioridade = strtoupper(trim($prioridade));
        $status = strtoupper(trim($status));

        if ($titulo === '') {
            return null;
        }

        if (!in_array($prioridade, self::PRIORIDADES, true)) {
            return null;
        }

        if (!in_array($status, self::STATUS, true)) {
            return null;
        }

        // Só "cancelado" é guardado; o resto o sistema calcula pelas datas.
        $status = $status === 'CANCELADO' ? 'CANCELADO' : 'PLANEJAMENTO';

        if ($descricao !== null) {
            $descricao = trim($descricao);

            if ($descricao === '') {
                $descricao = null;
            }
        }

        if ($observacoes !== null) {
            $observacoes = trim($observacoes);

            if ($observacoes === '') {
                $observacoes = null;
            }
        }

        return $this->repository->create(
            $tipoEventoId,
            $responsavelId,
            $localId,
            $titulo,
            $descricao,
            $prioridade,
            $status,
            $observacoes
        );
    }

    /**
     * Atualiza um evento.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        int $tipoEventoId,
        int $responsavelId,
        int $localId,
        string $titulo,
        ?string $descricao,
        string $prioridade,
        string $status,
        ?string $observacoes
    ): ?array {
        $titulo = trim($titulo);
        $prioridade = strtoupper(trim($prioridade));
        $status = strtoupper(trim($status));

        if ($titulo === '') {
            return null;
        }

        if (!in_array($prioridade, self::PRIORIDADES, true)) {
            return null;
        }

        if (!in_array($status, self::STATUS, true)) {
            return null;
        }

        // Só "cancelado" é guardado; o resto o sistema calcula pelas datas.
        $status = $status === 'CANCELADO' ? 'CANCELADO' : 'PLANEJAMENTO';

        if ($descricao !== null) {
            $descricao = trim($descricao);

            if ($descricao === '') {
                $descricao = null;
            }
        }

        if ($observacoes !== null) {
            $observacoes = trim($observacoes);

            if ($observacoes === '') {
                $observacoes = null;
            }
        }

        return $this->repository->update(
            $id,
            $tipoEventoId,
            $responsavelId,
            $localId,
            $titulo,
            $descricao,
            $prioridade,
            $status,
            $observacoes
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function conflitos(int $localId, string $inicio, string $fim, ?int $excetoId): array
    {
        if ($fim < $inicio) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        return $this->repository->findConflitos($localId, $inicio, $fim, $excetoId);
    }

    /**
     * Verifica se uma prioridade é válida.
     */
    public function isValidPriority(string $prioridade): bool
    {
        return in_array(
            strtoupper(trim($prioridade)),
            self::PRIORIDADES,
            true
        );
    }

    /**
     * Verifica se um status é válido.
     */
    public function isValidStatus(string $status): bool
    {
        return in_array(
            strtoupper(trim($status)),
            self::STATUS,
            true
        );
    }
}