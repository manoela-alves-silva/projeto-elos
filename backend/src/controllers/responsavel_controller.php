<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\ResponsavelRepository;

final class ResponsavelController
{
    public function __construct(
        private readonly ResponsavelRepository $repository
    ) {
    }

    /**
     * Lista todos os responsáveis ativos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(): array
    {
        return $this->repository->findAllActive();
    }

    /**
     * Busca um responsável pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function show(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Cria um novo responsável.
     *
     * @return array<string, mixed>|null
     */
    public function create(
        string $nome,
        string $tipo,
        ?string $email,
        ?string $telefone,
        ?string $observacoes
    ): ?array {
        $dados = $this->validateAndNormalize(
            $nome,
            $tipo,
            $email,
            $telefone,
            $observacoes
        );

        if ($dados === null) {
            return null;
        }

        return $this->repository->create(
            $dados['nome'],
            $dados['tipo'],
            $dados['email'],
            $dados['telefone'],
            $dados['observacoes']
        );
    }

    /**
     * Atualiza os dados de um responsável.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        string $nome,
        string $tipo,
        ?string $email,
        ?string $telefone,
        ?string $observacoes
    ): ?array {
        $dados = $this->validateAndNormalize(
            $nome,
            $tipo,
            $email,
            $telefone,
            $observacoes
        );

        if ($dados === null) {
            return null;
        }

        return $this->repository->update(
            $id,
            $dados['nome'],
            $dados['tipo'],
            $dados['email'],
            $dados['telefone'],
            $dados['observacoes']
        );
    }

    /**
     * Ativa ou desativa um responsável.
     *
     * @return array<string, mixed>|null
     */
    public function setActive(int $id, bool $ativo): ?array
    {
        return $this->repository->setActive($id, $ativo);
    }

    /**
     * Valida e normaliza os dados do responsável.
     *
     * @return array{
     *     nome: string,
     *     tipo: string,
     *     email: ?string,
     *     telefone: ?string,
     *     observacoes: ?string
     * }|null
     */
    private function validateAndNormalize(
        string $nome,
        string $tipo,
        ?string $email,
        ?string $telefone,
        ?string $observacoes
    ): ?array {
        $nome = trim($nome);
        $tipo = strtoupper(trim($tipo));

        $tiposPermitidos = [
            'PESSOA',
            'SETOR',
            'CURSO',
            'COLETIVO',
            'INSTITUICAO',
            'OUTRO',
        ];

        if (
            $nome === ''
            || !in_array($tipo, $tiposPermitidos, true)
        ) {
            return null;
        }

        $email = $email !== null ? trim($email) : null;
        $telefone = $telefone !== null ? trim($telefone) : null;
        $observacoes = $observacoes !== null
            ? trim($observacoes)
            : null;

        if ($email === '') {
            $email = null;
        }

        if ($telefone === '') {
            $telefone = null;
        }

        if ($observacoes === '') {
            $observacoes = null;
        }

        if (
            $email !== null
            && filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            return null;
        }

        return [
            'nome' => $nome,
            'tipo' => $tipo,
            'email' => $email,
            'telefone' => $telefone,
            'observacoes' => $observacoes,
        ];
    }
}