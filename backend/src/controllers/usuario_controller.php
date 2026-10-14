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
     * Entrada com Google (e-mail já confirmado pelo Google). Conta
     * existente e aprovada: entra. Conta pendente: continua esperando.
     * Sem conta: cria uma pendente, como no cadastro normal.
     *
     * @return array{usuario: array<string, mixed>, pendente: bool, nova: bool}
     */
    public function loginGoogle(string $email, string $nome): array
    {
        $usuario = $this->usuarioRepository->findByEmail($email);
        $nova = false;

        if ($usuario === null) {
            // Senha sorteada que ninguém conhece: a conta entra pelo Google.
            $criado = $this->register($nome, $email, bin2hex(random_bytes(24)));
            $usuario = $criado ?? $this->usuarioRepository->findByEmail($email);
            $nova = true;
        }

        if ($usuario === null) {
            throw new RuntimeException('Não foi possível criar a conta.');
        }

        unset($usuario['senha'], $usuario['codigo_recuperacao']);

        if ((int) $usuario['ativo'] !== 1) {
            return ['usuario' => $usuario, 'pendente' => true, 'nova' => $nova];
        }

        if (
            $usuario['perfil'] === 'COLABORADOR'
            && self::emailEhGestorInicial($email)
            && $this->usuarioRepository->countGestoresAtivos() === 0
        ) {
            $this->usuarioRepository->updatePerfil((int) $usuario['id'], 'GESTOR');
            $usuario['perfil'] = 'GESTOR';
        }

        $this->sessionService->login($usuario);

        return ['usuario' => $usuario, 'pendente' => false, 'nova' => $nova];
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
     * Um gestor exclui a conta de outra pessoa (colaborador ou gestor).
     * Excluir um gestor é como rebaixar e excluir, então vale a mesma
     * regra: o sistema não pode ficar sem gestor.
     *
     * @throws DomainException com o status HTTP como código
     */
    public function excluir(int $id): void
    {
        $usuario = $this->usuarioRepository->findById($id);

        if ($usuario === null || (int) $usuario['ativo'] !== 1) {
            throw new DomainException('Usuário não encontrado.', 404);
        }

        $atual = $this->sessionService->user();

        if ($atual !== null && (int) ($atual['id'] ?? 0) === $id) {
            throw new DomainException('Você não pode excluir a própria conta.', 409);
        }

        if ($usuario['perfil'] === 'ADMIN') {
            throw new DomainException('Um administrador não pode ser excluído por aqui.', 409);
        }

        if (
            $usuario['perfil'] === 'GESTOR'
            && $this->usuarioRepository->countGestoresAtivos($id) === 0
        ) {
            throw new DomainException(
                'O sistema precisa de pelo menos um gestor. Torne outra pessoa gestora antes.',
                409
            );
        }

        if (!$this->usuarioRepository->deleteAtivo($id)) {
            throw new DomainException('Usuário não encontrado.', 404);
        }
    }

    /**
     * A pessoa logada troca a própria senha, confirmando a atual.
     *
     * @throws DomainException com o status HTTP como código
     */
    public function trocarSenha(string $senhaAtual, string $novaSenha): void
    {
        $usuario = $this->usuarioLogadoConferindoSenha($senhaAtual);

        $this->usuarioRepository->updateSenha((int) $usuario['id'], self::protegerSenha($novaSenha));
    }

    /**
     * A pessoa logada gera um código de recuperação novo (o anterior
     * deixa de valer). Devolve o código, que só aparece desta vez.
     *
     * @throws DomainException com o status HTTP como código
     */
    public function gerarCodigoRecuperacao(string $senhaAtual): string
    {
        $usuario = $this->usuarioLogadoConferindoSenha($senhaAtual);

        return $this->novoCodigoRecuperacao((int) $usuario['id']);
    }

    /**
     * Quem esqueceu a senha cria uma nova com o e-mail e o código de
     * recuperação. O código usado deixa de valer e um novo é devolvido.
     * A mensagem de erro é sempre a mesma, para não revelar se o e-mail
     * tem conta.
     *
     * @throws DomainException (400) se o e-mail ou o código não conferem
     */
    public function recuperarSenha(string $email, string $codigo, string $novaSenha): string
    {
        $usuario = $this->usuarioRepository->findByEmail($email);
        $codigoHash = $usuario['codigo_recuperacao'] ?? null;

        if (
            !is_string($codigoHash)
            || !password_verify(self::normalizarCodigo($codigo), $codigoHash)
        ) {
            throw new DomainException('E-mail ou código de recuperação inválidos.', 400);
        }

        $id = (int) $usuario['id'];
        $this->usuarioRepository->updateSenha($id, self::protegerSenha($novaSenha));

        return $this->novoCodigoRecuperacao($id);
    }

    /**
     * Um gestor gera uma senha provisória para quem esqueceu a sua.
     * Devolve a senha, que só aparece uma vez, para o gestor repassar.
     *
     * @throws DomainException com o status HTTP como código
     */
    public function redefinirSenha(int $id): string
    {
        $usuario = $this->usuarioRepository->findById($id);

        if ($usuario === null || (int) $usuario['ativo'] !== 1) {
            throw new DomainException('Usuário não encontrado.', 404);
        }

        // Senão um gestor poderia tomar a conta de um administrador.
        if ($usuario['perfil'] === 'ADMIN') {
            throw new DomainException('A senha de um administrador não pode ser redefinida por aqui.', 409);
        }

        $senha = self::sortear(10);

        $this->usuarioRepository->updateSenha($id, self::protegerSenha($senha));

        return $senha;
    }

    /**
     * @throws DomainException (401) sem sessão; (400) senha atual errada
     */
    private function usuarioLogadoConferindoSenha(string $senhaAtual): array
    {
        $atual = $this->sessionService->user();
        $usuario = $atual === null
            ? null
            : $this->usuarioRepository->findById((int) ($atual['id'] ?? 0));

        if ($usuario === null) {
            throw new DomainException('Sessão expirada. Entre de novo.', 401);
        }

        if (!password_verify($senhaAtual, (string) $usuario['senha'])) {
            throw new DomainException('A senha atual não confere.', 400);
        }

        return $usuario;
    }

    /**
     * Sorteia um código no formato abcd-efgh-jkmn, grava o hash e devolve
     * o código em texto (a única vez em que ele existe fora do banco).
     */
    private function novoCodigoRecuperacao(int $id): string
    {
        $codigo = implode('-', str_split(self::sortear(12), 4));

        $this->usuarioRepository->updateCodigoRecuperacao(
            $id,
            self::protegerSenha(self::normalizarCodigo($codigo))
        );

        return $codigo;
    }

    /**
     * Aceita o código digitado com ou sem hífens, espaços e maiúsculas.
     */
    private static function normalizarCodigo(string $codigo): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($codigo));
    }

    /**
     * Letras e números sorteados, sem os que se confundem (l, 1, o, 0, i).
     */
    private static function sortear(int $tamanho): string
    {
        $caracteres = 'abcdefghjkmnpqrstuvwxyz23456789';
        $resultado = '';

        for ($i = 0; $i < $tamanho; $i++) {
            $resultado .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }

        return $resultado;
    }

    private static function protegerSenha(string $senha): string
    {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        if ($senhaHash === false) {
            throw new RuntimeException('Não foi possível proteger a senha.');
        }

        return $senhaHash;
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
            'codigo_recuperacao' => $this->novoCodigoRecuperacao($id),
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
