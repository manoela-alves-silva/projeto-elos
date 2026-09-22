<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\UsuarioRepository;
use Elos\Services\AuthService;
use Elos\Services\SessionService;
use DomainException;
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

    /**
     * Lista os usuários ativos (sem senha), para uso em seletores
     * como o de responsável por uma tarefa.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(): array
    {
        return $this->usuarioRepository->findAllActive();
    }

    public function login(string $email, string $senha): ?array
    {
        $usuario = $this->authService->authenticate($email, $senha);

        if ($usuario === null) {
            return null;
        }

        // Primeiro acesso de um sistema sem gestor: quem está na lista
        // ELOS_GESTORES assume. Depois que existe um gestor, os perfis
        // passam a ser trocados só pela tela Equipe (a lista não
        // "reverte" quem foi rebaixado de propósito).
        if (
            $usuario['perfil'] === 'COLABORADOR'
            && self::emailEhGestorInicial($email)
            && $this->usuarioRepository->countGestoresAtivos() === 0
        ) {
            $this->usuarioRepository->updatePerfil((int) $usuario['id'], 'GESTOR');
            $usuario['perfil'] = 'GESTOR';
        }

        $this->sessionService->login($usuario);

        return $usuario;
    }

    /**
     * Relê o usuário da sessão no banco, para que uma troca de perfil
     * (ou uma conta desativada) valha já na próxima requisição.
     */
    public function sincronizarSessao(): void
    {
        $atual = $this->sessionService->user();

        if ($atual === null || empty($atual['id'])) {
            return;
        }

        $usuario = $this->usuarioRepository->findById((int) $atual['id']);

        if ($usuario === null || (int) $usuario['ativo'] !== 1) {
            $this->sessionService->logout();

            return;
        }

        if ($usuario['perfil'] !== $atual['perfil']) {
            $this->sessionService->atualizarPerfil($usuario['perfil']);
        }
    }

    /**
     * Troca o perfil de um usuário entre COLABORADOR e GESTOR.
     *
     * @throws DomainException com o status HTTP como código
     */
    public function alterarPerfil(int $id, string $perfil): array
    {
        if (!in_array($perfil, ['COLABORADOR', 'GESTOR'], true)) {
            throw new DomainException('Perfil inválido.', 400);
        }

        $usuario = $this->usuarioRepository->findById($id);

        if ($usuario === null || (int) $usuario['ativo'] !== 1) {
            throw new DomainException('Usuário não encontrado.', 404);
        }

        if ($usuario['perfil'] === 'ADMIN') {
            throw new DomainException('O perfil de um administrador não pode ser alterado por aqui.', 409);
        }

        if (
            $perfil === 'COLABORADOR'
            && $usuario['perfil'] === 'GESTOR'
            && $this->usuarioRepository->countGestoresAtivos($id) === 0
        ) {
            throw new DomainException(
                'O sistema precisa de pelo menos um gestor. Torne outra pessoa gestora antes.',
                409
            );
        }

        if ($usuario['perfil'] !== $perfil) {
            $this->usuarioRepository->updatePerfil($id, $perfil);
        }

        $atual = $this->sessionService->user();

        if ($atual !== null && (int) ($atual['id'] ?? 0) === $id) {
            $this->sessionService->atualizarPerfil($perfil);
        }

        unset($usuario['senha']);
        $usuario['perfil'] = $perfil;

        return $usuario;
    }

    /**
     * E-mails em ELOS_GESTORES (variável de ambiente, separados por
     * vírgula) entram como gestores ao se cadastrar.
     */
    private static function emailEhGestorInicial(string $email): bool
    {
        $lista = getenv('ELOS_GESTORES');

        if ($lista === false || trim($lista) === '') {
            return false;
        }

        $emails = array_map(
            static fn (string $item): string => mb_strtolower(trim($item)),
            explode(',', $lista)
        );

        return in_array(mb_strtolower(trim($email)), $emails, true);
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

        // Conta nova entra pendente e só acessa depois que um gestor
        // aprovar (tela Equipe). Exceções: quem está em ELOS_GESTORES e
        // a primeira conta de um sistema ainda sem nenhum gestor.
        $ehGestor = self::emailEhGestorInicial($email)
            || $this->usuarioRepository->countGestoresAtivos() === 0;
        $perfil = $ehGestor ? 'GESTOR' : 'COLABORADOR';

        $id = $this->usuarioRepository->create(
            $nome,
            $email,
            $senhaHash,
            $perfil,
            $ehGestor
        );

        return [
            'id' => $id,
            'nome' => $nome,
            'email' => $email,
            'perfil' => $perfil,
            'ativo' => $ehGestor ? 1 : 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendentes(): array
    {
        return $this->usuarioRepository->findPendentes();
    }

    /**
     * @throws DomainException (404) se não houver conta pendente com esse id
     */
    public function aprovar(int $id): void
    {
        if (!$this->usuarioRepository->aprovar($id)) {
            throw new DomainException('Não há cadastro pendente com esse número.', 404);
        }
    }

    /**
     * @throws DomainException (404) se não houver conta pendente com esse id
     */
    public function recusar(int $id): void
    {
        if (!$this->usuarioRepository->deletePendente($id)) {
            throw new DomainException('Não há cadastro pendente com esse número.', 404);
        }
    }
}
