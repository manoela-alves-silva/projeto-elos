<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\CursoRepository;

final class CursoController
{
    public function __construct(
        private readonly CursoRepository $repository
    ) {
    }

    /**
     * Lista todos os cursos ativos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(): array
    {
        return $this->repository->findAllActive();
    }

    /**
     * Busca um curso pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function show(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Cria um novo curso.
     *
     * @return array<string, mixed>|null
     */
    public function create(string $nome): ?array
    {
        $nome = trim($nome);

        if ($nome === '') {
            return null;
        }

        return $this->repository->create($nome);
    }

    /**
     * Atualiza o nome de um curso.
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, string $nome): ?array
    {
        $nome = trim($nome);

        if ($nome === '') {
            return null;
        }

        return $this->repository->update($id, $nome);
    }

    /**
     * Ativa ou desativa um curso.
     *
     * @return array<string, mixed>|null
     */
    public function setActive(int $id, bool $ativo): ?array
    {
        return $this->repository->setActive($id, $ativo);
    }
}
