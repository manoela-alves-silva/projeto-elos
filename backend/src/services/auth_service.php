<?php

declare(strict_types=1);

namespace Elos\Services;

use Elos\Repositories\UsuarioRepository;

/**
 * Aplica as regras de autenticação sem controlar sessão ou resposta HTTP.
 */
final class AuthService
{
    public function __construct(private readonly UsuarioRepository $usuarioRepository)
    {
    }

    public function authenticate(string $email, string $senha): ?array
    {
        $usuario = $this->usuarioRepository->findByEmail($email);

        if ($usuario === null || (int) ($usuario['ativo'] ?? 0) !== 1) {
            return null;
        }

        $senhaHash = $usuario['senha'] ?? null;

        if (!is_string($senhaHash) || !password_verify($senha, $senhaHash)) {
            return null;
        }

        unset($usuario['senha']);

        return $usuario;
    }
}
