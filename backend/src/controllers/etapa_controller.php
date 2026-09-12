<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\EtapaRepository;

final class EtapaController
{
    public function __construct(
        private readonly EtapaRepository $repository
    ) {
    }

    /**
     * Lista todas as etapas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Busca uma etapa pelo ID.
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
     * Cria uma etapa.
     *
     * @return array<string, mixed>|null
     */
    public function create(
        string $nome,
        int $ordem
    ): ?array {
        if ($nome === '' || $ordem <= 0) {
            return null;
        }

        return $this->repository->create(
            $nome,
            $ordem
        );
    }

    /**
     * Atualiza uma etapa.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        string $nome,
        int $ordem
    ): ?array {
        if ($id <= 0 || $nome === '' || $ordem <= 0) {
            return null;
        }

        return $this->repository->update(
            $id,
            $nome,
            $ordem
        );
    }

    /**
     * Ativa ou desativa uma etapa.
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