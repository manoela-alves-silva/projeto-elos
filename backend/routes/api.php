<?php

declare(strict_types=1);

require_once __DIR__ . '/categoria_tarefa_routes.php';
require_once __DIR__ . '/auth_routes.php';
require_once __DIR__ . '/tipo_evento_routes.php';
require_once __DIR__ . '/responsavel_routes.php';
require_once __DIR__ . '/curso_routes.php';
require_once __DIR__ . '/local_routes.php';
require_once __DIR__ . '/evento_routes.php';
require_once __DIR__ . '/evento_curso_routes.php';
require_once __DIR__ . '/agenda_evento_routes.php';
require_once __DIR__ . '/tarefa_routes.php';
require_once __DIR__ . '/anexo_routes.php';
require_once __DIR__ . '/historico_routes.php';
require_once __DIR__ . '/eventos_dispatcher_routes.php';

use Elos\Controllers\UsuarioController;
use Elos\Services\AuthorizationService;
use Elos\Services\GoogleLoginService;
use Elos\Services\SessionService;

/**
 * Encaminha as requisições da API para o fluxo correspondente.
 *
 * @param callable(): UsuarioController $controllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleApiRequest(
    callable $controllerFactory,
    callable $authorizationFactory,
    callable $tipoEventoControllerFactory,
    callable $responsavelControllerFactory,
    callable $cursoControllerFactory,
    callable $localControllerFactory,
    callable $eventoControllerFactory,
    callable $eventoCursoControllerFactory,
    callable $agendaEventoControllerFactory,
    callable $categoriaTarefaControllerFactory,
    callable $tarefaControllerFactory,
    callable $anexoControllerFactory,
    callable $historicoControllerFactory
): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if ($path === '/api/lote') {
        handleLoteRequest($method, func_get_args());
    }

    if ($path === '/api/login') {
        handleLoginRequest($method, $controllerFactory);
    }

    if ($path === '/api/logout') {
        handleLogoutRequest($method, $controllerFactory);
    }

    $googleFactory = static fn (): GoogleLoginService => new GoogleLoginService(new SessionService());

    if ($path === '/api/login/google/inicio') {
        handleGoogleLoginStartRequest($method, $googleFactory);
    }

    if ($path === '/api/login/google') {
        handleGoogleLoginRequest($method, $googleFactory, $controllerFactory);
    }

    if ($path === '/api/usuarios') {
        if ($method === 'GET') {
            handleUserIndexRequest($controllerFactory, $authorizationFactory);
        } else {
            handleUserRegistrationRequest($method, $controllerFactory);
        }
    }

    if ($path === '/api/usuarios/pendentes') {
        handleUserPendingIndexRequest($method, $controllerFactory, $authorizationFactory);
    }

    if (preg_match('#^/api/usuarios/pendentes/(\d+)$#', $path, $matches)) {
        handleUserApprovalRequest(
            $method,
            (int) $matches[1],
            $controllerFactory,
            $authorizationFactory
        );
    }

    if ($path === '/api/recuperar-senha') {
        handlePasswordRecoveryRequest($method, $controllerFactory);
    }

    if ($path === '/api/usuarios/codigo-recuperacao') {
        handleRecoveryCodeRequest($method, $controllerFactory, $authorizationFactory);
    }

    if ($path === '/api/usuarios/senha') {
        handleUserPasswordChangeRequest($method, $controllerFactory, $authorizationFactory);
    }

    if (preg_match('#^/api/usuarios/(\d+)/senha$#', $path, $matches)) {
        handleUserPasswordResetRequest(
            $method,
            (int) $matches[1],
            $controllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/usuarios/(\d+)$#', $path, $matches)) {
        handleUserProfileRequest(
            $method,
            (int) $matches[1],
            $controllerFactory,
            $authorizationFactory
        );
    }

    if ($path === '/api/tipos-evento') {
        handleTipoEventoRequest(
            $method,
            $tipoEventoControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/tipos-evento/(\d+)$#', $path, $matches)) {
        handleTipoEventoByIdRequest(
            $method,
            (int) $matches[1],
            $tipoEventoControllerFactory,
            $authorizationFactory
        );
    }

    if ($path === '/api/responsaveis') {
        handleResponsavelRequest(
            $method,
            $responsavelControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/responsaveis/(\d+)$#', $path, $matches)) {
        handleResponsavelByIdRequest(
            $method,
            (int) $matches[1],
            $responsavelControllerFactory,
            $authorizationFactory
        );
    }

    if ($path === '/api/cursos') {
        handleCursoRequest(
            $method,
            $cursoControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/cursos/(\d+)$#', $path, $matches)) {
        handleCursoByIdRequest(
            $method,
            (int) $matches[1],
            $cursoControllerFactory,
            $authorizationFactory
        );
    }

    if ($path === '/api/locais') {
        handleLocalRequest(
            $method,
            $localControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/locais/(\d+)$#', $path, $matches)) {
        handleLocalByIdRequest(
            $method,
            (int) $matches[1],
            $localControllerFactory,
            $authorizationFactory
        );
    }

    if (
        str_starts_with($path, '/api/eventos')
        && (
            $path === '/api/eventos'
            || $path === '/api/eventos/conflitos'
            || $path === '/api/eventos/planejamento'
            || preg_match('#^/api/eventos/(\d+)($|/.*)$#', $path)
        )
    ) {
        handleEventosDispatcherRequest(
            $method,
            $path,
            $eventoControllerFactory,
            $eventoCursoControllerFactory,
            $agendaEventoControllerFactory,
            $tarefaControllerFactory,
            $anexoControllerFactory,
            $historicoControllerFactory,
            $authorizationFactory
        );
    }

    if ($path === '/api/categorias-tarefa') {
        handleCategoriaTarefaRequest(
            $method,
            $categoriaTarefaControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/categorias-tarefa/(\d+)$#', $path, $matches)) {
        handleCategoriaTarefaByIdRequest(
            $method,
            (int) $matches[1],
            $categoriaTarefaControllerFactory,
            $authorizationFactory
        );
    }

    sendJsonResponse(404, [
        'erro' => 'Rota não encontrada.',
    ]);
}

/**
 * Interrompe a requisição quando o usuário não possui o perfil necessário.
 *
 * @param callable(): AuthorizationService $authorizationFactory
 */
function requireRole(
    callable $authorizationFactory,
    string $perfil
): void {
    if (!$authorizationFactory()->hasRole($perfil)) {
        sendJsonResponse(403, [
            'erro' => 'Acesso não autorizado.',
        ]);
    }
}

/**
 * @return array<string, mixed>
 */
function readJsonPayload(): array
{
    $payload = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse(400, [
            'erro' => 'JSON inválido.',
        ]);
    }

    return $payload;
}

/**
 * @param array<string, mixed> $usuario
 * @return array<string, mixed>
 */
function publicUserData(array $usuario): array
{
    return array_intersect_key(
        $usuario,
        array_flip([
            'id',
            'nome',
            'email',
            'perfil',
        ])
    );
}

/**
 * Envia uma resposta JSON e encerra o processamento da requisição.
 */
function sendJsonResponse(
    int $statusCode,
    array $data
): never {
    // Dentro de um lote, a resposta volta para quem montou o lote.
    if (!empty($GLOBALS['ELOS_EM_LOTE'])) {
        throw new RespostaDoLote($statusCode, $data);
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * Resposta de um pedido feito dentro de um lote (ver handleLoteRequest).
 */
final class RespostaDoLote extends RuntimeException
{
    /** @param array<string, mixed> $dados */
    public function __construct(
        public readonly int $status,
        public readonly array $dados
    ) {
        parent::__construct('Resposta do lote');
    }
}

/**
 * Vários GETs numa ida só.
 *
 * GET /api/lote?caminhos[]=/api/eventos/5&caminhos[]=/api/eventos/5/tarefas
 *
 * Na hospedagem, cada pedido à API abre um processo PHP novo; a página
 * de uma exposição fazia uns 12. Aqui cada caminho passa pelas mesmas
 * rotas e permissões de sempre, só que dentro de um pedido.
 * Só aceita leituras de listas e de exposições (nada que grave).
 *
 * @param list<callable> $fabricas  Os mesmos argumentos de handleApiRequest.
 */
function handleLoteRequest(string $method, array $fabricas): never
{
    if ($method !== 'GET') {
        sendJsonResponse(405, [
            'erro' => 'Método não permitido.',
        ]);
    }

    $caminhos = $_GET['caminhos'] ?? null;

    if (!is_array($caminhos) || $caminhos === [] || count($caminhos) > 20) {
        sendJsonResponse(400, [
            'erro' => 'Informe de 1 a 20 caminhos.',
        ]);
    }

    $permitidos = [
        '#^/api/(usuarios|tipos-evento|responsaveis|cursos|locais|categorias-tarefa|eventos)$#',
        '#^/api/eventos/planejamento$#',
        '#^/api/eventos/\d+(/(agenda|tarefas|cursos|anexos|historico))?$#',
    ];

    $uriOriginal = $_SERVER['REQUEST_URI'] ?? '/';
    $getOriginal = $_GET;
    $respostas = [];

    foreach (array_values(array_unique(array_filter($caminhos, 'is_string'))) as $caminho) {
        $rota = (string) parse_url($caminho, PHP_URL_PATH);
        $aceito = false;

        foreach ($permitidos as $padrao) {
            $aceito = $aceito || preg_match($padrao, $rota) === 1;
        }

        if (!$aceito) {
            $respostas[] = ['caminho' => $caminho, 'status' => 400, 'dados' => ['erro' => 'Caminho não permitido no lote.']];
            continue;
        }

        $_SERVER['REQUEST_URI'] = $caminho;
        parse_str((string) parse_url($caminho, PHP_URL_QUERY), $consulta);
        $_GET = $consulta;
        $GLOBALS['ELOS_EM_LOTE'] = true;
        ob_start();

        try {
            handleApiRequest(...$fabricas);
        } catch (RespostaDoLote $resposta) {
            $respostas[] = ['caminho' => $caminho, 'status' => $resposta->status, 'dados' => $resposta->dados];
        } catch (Throwable $erro) {
            error_log('ELOS lote ' . $rota . ': ' . $erro);
            $respostas[] = ['caminho' => $caminho, 'status' => 500, 'dados' => ['erro' => 'Erro interno do servidor.']];
        } finally {
            ob_end_clean();
            $GLOBALS['ELOS_EM_LOTE'] = false;
            $_SERVER['REQUEST_URI'] = $uriOriginal;
            $_GET = $getOriginal;
        }
    }

    sendJsonResponse(200, [
        'respostas' => $respostas,
    ]);
}
