<?php

declare(strict_types=1);

namespace Elos\Services;

/**
 * Controla somente a sessão HTTP do usuário autenticado.
 */
final class SessionService
{
    private const USER_SESSION_KEY = 'usuario';
    private const VALIDADE_SEGUNDOS = 30 * 24 * 60 * 60;

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
            $this->guardarSessoesNoProjeto();

            session_start();
        }
    }

    /**
     * Arquivos de sessão em storage/sessoes, valendo 30 dias: na pasta
     * padrão do sistema eles somem depois de 24 minutos parados, e o
     * "Lembrar de mim" do frontend perderia o login aqui no backend.
     * Quem não marcou "Lembrar de mim" sai antes, pelo frontend.
     */
    private function guardarSessoesNoProjeto(): void
    {
        $diretorio = dirname(__DIR__, 2) . '/storage/sessoes';

        if (!is_dir($diretorio) && !mkdir($diretorio, 0700, true) && !is_dir($diretorio)) {
            return; // sem a pasta, fica a padrão do PHP
        }

        session_save_path($diretorio);
        ini_set('session.gc_maxlifetime', (string) self::VALIDADE_SEGUNDOS);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
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
