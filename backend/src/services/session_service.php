<?php

declare(strict_types=1);

namespace Elos\Services;

/**
 * Controla somente a sessão HTTP do usuário autenticado.
 */
final class SessionService
{
    private const USER_SESSION_KEY = 'usuario';

    public function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function login(array $usuario): void
    {
        $this->start();
        session_regenerate_id(true);

        $_SESSION[self::USER_SESSION_KEY] = [
            'id' => $usuario['id'] ?? null,
            'nome' => $usuario['nome'] ?? null,
            'email' => $usuario['email'] ?? null,
            'perfil' => $usuario['perfil'] ?? null,
        ];
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];
        session_destroy();
    }

    public function isAuthenticated(): bool
    {
        $this->start();

        return isset($_SESSION[self::USER_SESSION_KEY])
            && is_array($_SESSION[self::USER_SESSION_KEY]);
    }

    public function user(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $_SESSION[self::USER_SESSION_KEY];
    }
}
