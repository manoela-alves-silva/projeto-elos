<?php

declare(strict_types=1);

namespace Elos\Services;

final class AuthorizationService
{
    public function __construct(
        private readonly SessionService $sessionService
    ) {
    }

    /**
     * Retorna os dados do usuário autenticado na sessão atual,
     * ou null se não houver usuário autenticado.
     *
     * @return array<string, mixed>|null
     */
    public function currentUser(): ?array
    {
        return $this->sessionService->user();
    }

    public function hasRole(string $perfil): bool
    {
        if (!$this->sessionService->isAuthenticated()) {
            return false;
        }

        $usuario = $this->sessionService->user();
        $perfilAtual = $usuario['perfil'] ?? null;

        if (!is_string($perfilAtual)) {
            return false;
        }

        if ($perfilAtual === 'ADMIN') {
            return true;
        }

        if ($perfilAtual === 'GESTOR') {
            return in_array($perfil, ['GESTOR', 'COLABORADOR'], true);
        }

        if ($perfilAtual === 'COLABORADOR') {
            return $perfil === 'COLABORADOR';
        }

        return false;
    }
}