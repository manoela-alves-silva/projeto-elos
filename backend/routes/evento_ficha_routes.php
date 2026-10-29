<?php

declare(strict_types=1);

use Elos\Controllers\EventoController;
use Elos\Controllers\EventoFichaController;
use Elos\Services\AuthorizationService;
use Elos\Services\HistoricoService;

/**
 * /api/eventos/{id}/ficha — ficha de um evento de projeto (o filme da
 * sessão do Cine Clube, o autor e a obra do Café Cultural...).
 *
 * GET: qualquer pessoa logada (404 se o evento ainda não tem ficha).
 * PUT: gestor; corpo {"dados": {...}}; cria na primeira vez, depois substitui.
 *
 * @param callable(): EventoFichaController $eventoFichaControllerFactory
 * @param callable(): EventoController $eventoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleEventoFichaRequest(
    string $method,
    int $eventoId,
    callable $eventoFichaControllerFactory,
    callable $eventoControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($eventoControllerFactory()->show($eventoId) === null) {
        sendJsonResponse(404, [
            'erro' => 'Evento não encontrado.',
        ]);
    }

    if ($method === 'GET') {
        $ficha = $eventoFichaControllerFactory()->show($eventoId);

        if ($ficha === null) {
            sendJsonResponse(404, [
                'erro' => 'Este evento ainda não tem ficha.',
            ]);
        }

        sendJsonResponse(200, [
            'ficha' => $ficha,
        ]);
    }

    if ($method === 'PUT') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();
        [$ficha, $erro] = $eventoFichaControllerFactory()->save($eventoId, $payload['dados'] ?? null);

        if ($ficha === null) {
            sendJsonResponse(400, [
                'erro' => $erro,
            ]);
        }

        HistoricoService::registrar($eventoId, 'Ficha atualizada');

        sendJsonResponse(200, [
            'ficha' => $ficha,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * GET /api/fichas — as fichas de todos os eventos de projeto, numa
 * resposta só (as listas dos projetos não fazem um pedido por evento).
 *
 * @param callable(): EventoFichaController $eventoFichaControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleFichasRequest(
    string $method,
    callable $eventoFichaControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method !== 'GET') {
        sendJsonResponse(405, [
            'erro' => 'Método não permitido.',
        ]);
    }

    sendJsonResponse(200, [
        'fichas' => $eventoFichaControllerFactory()->index(),
    ]);
}
