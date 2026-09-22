<?php

declare(strict_types=1);

require_once __DIR__ . '/etapa_routes.php';
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
require_once __DIR__ . '/formulario_routes.php';
require_once __DIR__ . '/transporte_routes.php';
require_once __DIR__ . '/visita_routes.php';
require_once __DIR__ . '/anexo_routes.php';
require_once __DIR__ . '/historico_routes.php';
require_once __DIR__ . '/eventos_dispatcher_routes.php';

use Elos\Controllers\UsuarioController;
use Elos\Services\AuthorizationService;

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
    callable $etapaControllerFactory,
    callable $categoriaTarefaControllerFactory,
    callable $tarefaControllerFactory,
    callable $formularioControllerFactory,
    callable $transporteControllerFactory,
    callable $visitaControllerFactory,
    callable $anexoControllerFactory,
    callable $historicoControllerFactory
): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if ($path === '/api/login') {
        handleLoginRequest($method, $controllerFactory);
    }

    if ($path === '/api/logout') {
        handleLogoutRequest($method, $controllerFactory);
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
            $formularioControllerFactory,
            $transporteControllerFactory,
            $visitaControllerFactory,
            $anexoControllerFactory,
            $historicoControllerFactory,
            $authorizationFactory
        );
    }

    if ($path === '/api/etapas') {
        handleEtapaRequest(
            $method,
            $etapaControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/etapas/(\d+)$#', $path, $matches)) {
        handleEtapaByIdRequest(
            $method,
            (int) $matches[1],
            $etapaControllerFactory,
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
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}