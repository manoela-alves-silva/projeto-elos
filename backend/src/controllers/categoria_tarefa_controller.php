<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\CategoriaTarefaRepository;

final class CategoriaTarefaController
{
    public function __construct(
        private readonly CategoriaTarefaRepository $repository
    ) {
    }

    /**
     * Lista todas as categorias de tarefa.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Busca uma categoria pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function show(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->repository->findById($id);
    }

    /**
     * Cria uma categoria de tarefa.
     *
     * @return array<string, mixed>|null
     */
    public function create(string $nome): ?array
    {
        if ($nome === '') {
            return null;
        }

        return $this->repository->create($nome);
    }

    /**
     * Atualiza uma categoria de tarefa.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        string $nome
    ): ?array {
        if ($id <= 0 || $nome === '') {
            return null;
        }

        return $this->repository->update(
            $id,
            $nome
        );
    }

    /**
     * Ativa ou desativa uma categoria de tarefa.
     *
     * @return array<string, mixed>|null
     */
    public function setActive(
        int $id,
        bool $ativa
    ): ?array {
        if ($id <= 0) {
            return null;
        }

        return $this->repository->setActive(
            $id,
            $ativa
        );
    }
}