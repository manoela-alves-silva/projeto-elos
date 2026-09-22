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
            // Só o frontend (servidor) usa este cookie; nenhum navegador
            // deveria enviá-lo nem lê-lo.
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            ini_set('session.use_strict_mode', '1');

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

    public function atualizarPerfil(string $perfil): void
    {
        if ($this->isAuthenticated()) {
            $_SESSION[self::USER_SESSION_KEY]['perfil'] = $perfil;
        }
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
