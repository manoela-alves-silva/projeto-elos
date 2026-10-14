<?php

declare(strict_types=1);

namespace Elos\Services;

use DomainException;

/**
 * "Entrar com Google" (OAuth 2.0 / OpenID Connect, fluxo com código).
 *
 * 1. montarUrl(): gera o endereço da tela do Google, com um "state" e um
 *    "nonce" sorteados que ficam guardados na sessão.
 * 2. O Google devolve a pessoa para o ELOS com um código.
 * 3. confirmar(): confere o state, troca o código pelos dados da pessoa
 *    (direto com o Google, usando o segredo que só o backend conhece) e
 *    devolve o e-mail e o nome confirmados pelo Google.
 *
 * Configuração (variáveis de ambiente do backend): GOOGLE_CLIENT_ID e
 * GOOGLE_CLIENT_SECRET, criados no Google Cloud Console.
 */
final class GoogleLoginService
{
    private const URL_AUTORIZACAO = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const URL_TOKEN = 'https://oauth2.googleapis.com/token';
    private const EMISSORES = ['https://accounts.google.com', 'accounts.google.com'];
    private const CHAVE_SESSAO = 'google_login';
    private const VALIDADE_SEGUNDOS = 10 * 60;

    public function __construct(
        private readonly SessionService $sessionService
    ) {
    }

    public static function configurado(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }

    /**
     * @throws DomainException (400) endereço de retorno inválido
     */
    public function montarUrl(string $redirectUri): string
    {
        if (!preg_match('#^https?://[^\s]+$#', $redirectUri)) {
            throw new DomainException('Endereço de retorno inválido.', 400);
        }

        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $this->sessionService->start();
        $_SESSION[self::CHAVE_SESSAO] = [
            'state' => $state,
            'nonce' => $nonce,
            'redirect_uri' => $redirectUri,
            'criado_em' => time(),
        ];

        return self::URL_AUTORIZACAO . '?' . http_build_query([
            'client_id' => self::clientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'prompt' => 'select_account',
        ]);
    }

    /**
     * @return array{email: string, nome: string}
     * @throws DomainException (400) quando algo não confere
     */
    public function confirmar(string $codigo, string $state): array
    {
        $this->sessionService->start();
        $pedido = $_SESSION[self::CHAVE_SESSAO] ?? null;
        // Cada pedido vale uma vez só.
        unset($_SESSION[self::CHAVE_SESSAO]);

        if (
            !is_array($pedido)
            || !hash_equals((string) $pedido['state'], $state)
            || time() - (int) $pedido['criado_em'] > self::VALIDADE_SEGUNDOS
        ) {
            throw new DomainException('O pedido de entrada com Google expirou. Tente de novo.', 400);
        }

        $resposta = $this->trocarCodigo($codigo, (string) $pedido['redirect_uri']);
        $dados = self::lerIdToken((string) ($resposta['id_token'] ?? ''));

        $emissorOk = in_array($dados['iss'] ?? null, self::EMISSORES, true);
        $audienciaOk = ($dados['aud'] ?? null) === self::clientId();
        $dentroDoPrazo = (int) ($dados['exp'] ?? 0) > time();
        $nonceOk = is_string($dados['nonce'] ?? null)
            && hash_equals((string) $pedido['nonce'], $dados['nonce']);

        if (!$emissorOk || !$audienciaOk || !$dentroDoPrazo || !$nonceOk) {
            throw new DomainException('Não foi possível confirmar sua conta Google.', 400);
        }

        $email = mb_strtolower(trim((string) ($dados['email'] ?? '')));

        if ($email === '' || ($dados['email_verified'] ?? false) !== true) {
            throw new DomainException('Sua conta Google não tem um e-mail confirmado.', 400);
        }

        $nome = trim((string) ($dados['name'] ?? ''));

        return [
            'email' => $email,
            'nome' => $nome !== '' ? mb_substr($nome, 0, 150) : explode('@', $email)[0],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trocarCodigo(string $codigo, string $redirectUri): array
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json",
                'content' => http_build_query([
                    'code' => $codigo,
                    'client_id' => self::clientId(),
                    'client_secret' => self::clientSecret(),
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                ]),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $corpo = @file_get_contents(self::URL_TOKEN, false, $contexto);
        $dados = is_string($corpo) ? json_decode($corpo, true) : null;

        if (!is_array($dados) || !isset($dados['id_token'])) {
            error_log('Google OAuth: falha ao trocar o código: ' . (is_string($corpo) ? $corpo : 'sem resposta'));

            throw new DomainException('Não foi possível confirmar sua conta Google. Tente de novo.', 400);
        }

        return $dados;
    }

    /**
     * Lê o conteúdo do id_token. A assinatura não precisa ser conferida
     * aqui: o token veio direto do Google, por HTTPS, em troca do código
     * (é o que a especificação do OpenID Connect permite neste fluxo).
     *
     * @return array<string, mixed>
     */
    private static function lerIdToken(string $idToken): array
    {
        $partes = explode('.', $idToken);

        if (count($partes) !== 3) {
            return [];
        }

        $json = base64_decode(strtr($partes[1], '-_', '+/'), true);
        $dados = is_string($json) ? json_decode($json, true) : null;

        return is_array($dados) ? $dados : [];
    }

    private static function clientId(): string
    {
        return trim((string) getenv('GOOGLE_CLIENT_ID'));
    }

    private static function clientSecret(): string
    {
        return trim((string) getenv('GOOGLE_CLIENT_SECRET'));
    }
}
