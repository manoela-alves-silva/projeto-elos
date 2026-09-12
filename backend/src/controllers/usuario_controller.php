<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\UsuarioRepository;
use Elos\Services\AuthService;
use Elos\Services\SessionService;
use RuntimeException;

/**
 * Camada de entrada para consultas e operações de usuários no novo backend.
 */
final class UsuarioController
{
    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
        private readonly AuthService $authService,
        private readonly SessionService $sessionService
    ) {
    }

    public function findByEmail(string $email): ?array
    {
        return $this->usuarioRepository->findByEmail($email);
    }

    public function login(string $email, string $senha): ?array
    {
        $usuario = $this->authService->authenticate($email, $senha);

        if ($usuario === null) {
            return null;
        }

        $this->sessionService->login($usuario);

        return $usuario;
    }

    public function logout(): void
    {
        $this->sessionService->logout();
    }

    public function isAuthenticated(): bool
    {
        return $this->sessionService->isAuthenticated();
    }

    public function register(string $nome, string $email, string $senha): ?array
    {
        if ($this->usuarioRepository->findByEmail($email) !== null) {
            return null;
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        if ($senhaHash === false) {
            throw new RuntimeException('Não foi possível proteger a senha.');
        }

        $id = $this->usuarioRepository->create(
            $nome,
            $email,
            $senhaHash,
            'COLABORADOR'
        );

        return [
            'id' => $id,
            'nome' => $nome,
            'email' => $email,
            'perfil' => 'COLABORADOR',
        ];
    }
}
