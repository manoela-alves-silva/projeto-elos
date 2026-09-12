<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\LocalRepository;

final class LocalController
{
    public function __construct(
        private readonly LocalRepository $repository
    ) {
    }

    /**
     * Lista todos os locais ativos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(): array
    {
        return $this->repository->findAllActive();
    }

    /**
     * Busca um local pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function show(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Cria um novo local.
     *
     * @return array<string, mixed>|null
     */
    public function create(
        string $nome,
        ?string $descricao
    ): ?array {
        $nome = trim($nome);

        if ($nome === '') {
            return null;
        }

        if ($descricao !== null) {
            $descricao = trim($descricao);

            if ($descricao === '') {
                $descricao = null;
            }
        }

        return $this->repository->create(
            $nome,
            $descricao
        );
    }

    /**
     * Atualiza os dados de um local.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        string $nome,
        ?string $descricao
    ): ?array {
        $nome = trim($nome);

        if ($nome === '') {
            return null;
        }

        if ($descricao !== null) {
            $descricao = trim($descricao);

            if ($descricao === '') {
                $descricao = null;
            }
        }

        return $this->repository->update(
            $id,
            $nome,
            $descricao
        );
    }

    /**
     * Ativa ou desativa um local.
     *
     * @return array<string, mixed>|null
     */
    public function setActive(
        int $id,
        bool $ativo
    ): ?array {
        return $this->repository->setActive(
            $id,
            $ativo
        );
    }
}