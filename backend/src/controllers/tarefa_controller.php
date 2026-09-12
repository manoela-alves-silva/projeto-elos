<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\TarefaRepository;

final class TarefaController
{
    public function __construct(
        private readonly TarefaRepository $repository
    ) {
    }

    /**
     * Lista as tarefas de um evento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function indexByEvento(int $eventoId): array
    {
        return $this->repository->findByEventoId($eventoId);
    }

    /**
     * Busca uma tarefa pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function show(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Cria uma tarefa.
     *
     * @return array<string, mixed>|null
     */
    public function create(
        int $eventoId,
        ?int $usuarioResponsavelId,
        int $etapaId,
        ?int $categoriaId,
        string $titulo,
        ?string $descricao,
        ?string $prazo,
        string $prioridade,
        string $status,
        ?string $observacoes
    ): ?array {
        return $this->repository->create(
            $eventoId,
            $usuarioResponsavelId,
            $etapaId,
            $categoriaId,
            $titulo,
            $descricao,
            $prazo,
            $prioridade,
            $status,
            $observacoes
        );
    }

    /**
     * Atualiza uma tarefa.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        int $eventoId,
        ?int $usuarioResponsavelId,
        int $etapaId,
        ?int $categoriaId,
        string $titulo,
        ?string $descricao,
        ?string $prazo,
        string $prioridade,
        string $status,
        ?string $observacoes
    ): ?array {
        return $this->repository->update(
            $id,
            $eventoId,
            $usuarioResponsavelId,
            $etapaId,
            $categoriaId,
            $titulo,
            $descricao,
            $prazo,
            $prioridade,
            $status,
            $observacoes
        );
    }

    /**
     * Exclui uma tarefa.
     */
    public function delete(int $id, int $eventoId): bool
    {
        return $this->repository->delete(
            $id,
            $eventoId
        );
    }

    /**
     * Verifica se a prioridade é válida.
     */
    public function isValidPriority(string $prioridade): bool
    {
        return in_array(
            $prioridade,
            ['BAIXA', 'MEDIA', 'ALTA'],
            true
        );
    }

    /**
     * Verifica se o status é válido.
     */
    public function isValidStatus(string $status): bool
    {
        return in_array(
            $status,
            [
                'PENDENTE',
                'EM_ANDAMENTO',
                'CONCLUIDA',
                'BLOQUEADA',
                'CANCELADA',
            ],
            true
        );
    }
}