<?php

declare(strict_types=1);

namespace Elos\Frontend;

// "Hoje", atrasos e contagens de dias no horário de Brasília, não no
// fuso padrão do PHP (UTC, que já "vira o dia" às 21h).
date_default_timezone_set('America/Sao_Paulo');

/**
 * Abre a sessão do frontend com o cookie protegido: invisível para
 * JavaScript (HttpOnly), não enviado por outros sites em POST
 * (SameSite=Lax) e só por HTTPS quando o site estiver em HTTPS.
 * Todas as páginas usam esta função em vez de session_start().
 */
function iniciarSessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');

    session_start();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        exigirCsrf();
    }
}

/**
 * Chave secreta desta sessão, que vai escondida em todo formulário.
 * Um site de fora não tem como saber o valor, então não consegue
 * enviar formulários do ELOS em nome de quem está logado.
 */
function tokenCsrf(): string
{
    iniciarSessao();

    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

/**
 * Campo escondido para colocar dentro de todo formulário POST.
 */
function campoCsrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . tokenCsrf() . '">';
}

/**
 * Todo POST precisa trazer a chave da sessão. Sem ela, a requisição
 * para aqui (acontece também quando a sessão expirou com a página aberta).
 */
function exigirCsrf(): void
{
    $enviado = $_POST['_csrf'] ?? '';
    $esperado = $_SESSION['_csrf'] ?? '';

    if (
        is_string($enviado)
        && is_string($esperado)
        && $esperado !== ''
        && hash_equals($esperado, $enviado)
    ) {
        return;
    }

    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');

    $voltar = htmlspecialchars(
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        ENT_QUOTES,
        'UTF-8'
    );

    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Página expirada | ELOS</title></head>'
        . '<body style="font-family: sans-serif; padding: 32px;">'
        . '<h1>Esta página expirou</h1>'
        . '<p>Por segurança, o formulário não foi enviado. '
        . '<a href="' . $voltar . '">Volte para a página</a> e tente de novo.</p>'
        . '</body></html>';
    exit();
}

/**
 * Exceção lançada quando a API do backend responde com erro (status >= 400)
 * ou quando a conexão falha.
 */
final class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly array $body = []
    ) {
        parent::__construct($message);
    }
}

/**
 * Cliente central para chamadas ao backend do ELOS.
 *
 * Resolve o problema de a API usar sessão PHP nativa: como o frontend
 * chama o backend servidor-a-servidor (e não o navegador diretamente),
 * o cookie de sessão devolvido pelo backend no login precisa ser
 * capturado manualmente aqui e reenviado em toda chamada seguinte.
 *
 * Uso:
 *   $usuario = Api::post('/api/login', ['email' => $email, 'senha' => $senha]);
 *   $eventos = Api::get('/api/eventos');
 */
final class Api
{
    // Endereço do backend. Em produção, definir ELOS_API_URL no
    // ambiente do servidor do frontend.
    private const BASE_URL_PADRAO = 'http://127.0.0.1:8000';

    // Chave usada na sessão do FRONTEND para guardar o cookie do BACKEND.
    // Não é o mesmo cookie do usuário no frontend — são sessões distintas.
    private const SESSION_COOKIE_KEY = '_elos_backend_cookie';

    public static function get(string $path): array
    {
        return self::request('GET', $path);
    }

    public static function post(string $path, array $body = []): array
    {
        return self::request('POST', $path, $body);
    }

    public static function put(string $path, array $body = []): array
    {
        return self::request('PUT', $path, $body);
    }

    public static function delete(string $path): array
    {
        return self::request('DELETE', $path);
    }

    /**
     * Limpa o cookie de sessão do backend guardado no frontend.
     * Chamar isso junto do logout local (destruir $_SESSION do frontend).
     */
    public static function limparSessaoBackend(): void
    {
        self::garantirSessao();
        unset($_SESSION[self::SESSION_COOKIE_KEY]);
    }

    private static function request(string $method, string $path, ?array $body = null): array
    {
        self::garantirSessao();

        $headers = ['Accept: application/json'];

        // O backend usa o endereço de quem acessa para frear tentativas
        // de adivinhar senha (ele só vê o servidor do frontend).
        $ipCliente = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        if (filter_var($ipCliente, FILTER_VALIDATE_IP) !== false) {
            $headers[] = 'X-Elos-Cliente-IP: ' . $ipCliente;
        }

        $payload = null;

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $payload = json_encode(
                $body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        if (!empty($_SESSION[self::SESSION_COOKIE_KEY])) {
            $headers[] = 'Cookie: ' . $_SESSION[self::SESSION_COOKIE_KEY];
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);

        $resposta = @file_get_contents(
            self::baseUrl() . $path,
            false,
            $context
        );

        if ($resposta === false) {
            throw new ApiException(
                'Não foi possível conectar ao servidor do ELOS.',
                0
            );
        }

        // $http_response_header é preenchido automaticamente pelo PHP
        // no escopo onde file_get_contents foi chamado.
        $headersResposta = $http_response_header ?? [];

        self::capturarCookie($headersResposta);

        $statusCode = self::extrairStatusCode($headersResposta);
        $dados = json_decode($resposta, true);
        $dados = is_array($dados) ? $dados : [];

        if ($statusCode >= 400) {
            $mensagem = is_string($dados['erro'] ?? null)
                ? $dados['erro']
                : 'Erro ao comunicar com a API (HTTP ' . $statusCode . ').';

            throw new ApiException($mensagem, $statusCode, $dados);
        }

        return $dados;
    }

    private static function baseUrl(): string
    {
        $url = getenv('ELOS_API_URL');

        return rtrim(
            is_string($url) && trim($url) !== '' ? trim($url) : self::BASE_URL_PADRAO,
            '/'
        );
    }

    private static function garantirSessao(): void
    {
        iniciarSessao();
    }

    private static function capturarCookie(array $headers): void
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Set-Cookie:') !== 0) {
                continue;
            }

            $cookie = trim(substr($header, strlen('Set-Cookie:')));

            // Guarda só o par nome=valor (ex: PHPSESSID=abc123),
            // descartando atributos como Path, HttpOnly, SameSite.
            $parNomeValor = explode(';', $cookie, 2)[0];

            $_SESSION[self::SESSION_COOKIE_KEY] = $parNomeValor;
        }
    }

    private static function extrairStatusCode(array $headers): int
    {
        if (
            isset($headers[0])
            && preg_match('#HTTP/\S+\s+(\d{3})#', $headers[0], $match)
        ) {
            return (int) $match[1];
        }

        return 200;
    }
}