<?php

declare(strict_types=1);

namespace Elos\Services;

use DomainException;
use Elos\Repositories\UsuarioRepository;

/**
 * Aplica as regras de autenticação sem controlar sessão ou resposta HTTP.
 */
final class AuthService
{
    public function __construct(private readonly UsuarioRepository $usuarioRepository)
    {
    }

    /**
     * @throws DomainException (403) quando a senha confere mas a conta
     *                         ainda não foi aprovada
     */
    public function authenticate(string $email, string $senha): ?array
    {
        $usuario = $this->usuarioRepository->findByEmail($email);

        if ($usuario === null) {
            return null;
        }

        $senhaHash = $usuario['senha'] ?? null;

        if (!is_string($senhaHash) || !password_verify($senha, $senhaHash)) {
            return null;
        }

        // Só depois de conferir a senha: quem não sabe a senha não
        // descobre que a conta existe.
        if ((int) ($usuario['ativo'] ?? 0) !== 1) {
            throw new DomainException(
                'Sua conta ainda aguarda a aprovação de um gestor.',
                403
            );
        }

        unset($usuario['senha']);

        return $usuario;
    }
}
