<?php

declare(strict_types=1);

use Elos\Controllers\HistoricoController;

/**
 * Lista o histórico de um evento.
 */
function handleHistoricoRequest(
    string $method,
    int $eventoId,
    callable $historicoControllerFactory,
    callable $authorizationFactory
): void {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $controller = $historicoControllerFactory();

        sendJsonResponse(200, [
            'historico' => $controller->indexByEvento($eventoId),
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * Cria um registro no histórico de um evento.
 */
function handleHistoricoCreateRequest(
    string $method,
    int $eventoId,
    callable $historicoControllerFactory,
    callable $authorizationFactory
): void {
    requireRole($authorizationFactory, 'GESTOR');

    if ($method !== 'POST') {
        sendJsonResponse(405, [
            'erro' => 'Método não permitido.',
        ]);
    }

    $payload = readJsonPayload();

    $acao = $payload['acao'] ?? null;
    $descricao = $payload['descricao'] ?? null;
    $usuarioId = $payload['usuario_id'] ?? null;

    if (!is_string($acao) || trim($acao) === '') {
        sendJsonResponse(422, [
            'erro' => 'O campo "acao" é obrigatório.',
        ]);
    }

    if ($descricao !== null && !is_string($descricao)) {
        sendJsonResponse(422, [
            'erro' => 'O campo "descricao" deve ser uma string ou nulo.',
        ]);
    }

    if ($usuarioId !== null && (!is_int($usuarioId) || $usuarioId <= 0)) {
        sendJsonResponse(422, [
            'erro' => 'O campo "usuario_id" deve ser um inteiro positivo ou nulo.',
        ]);
    }

    $controller = $historicoControllerFactory();

    $historico = $controller->create(
        $eventoId,
        $usuarioId,
        trim($acao),
        $descricao
    );

    if ($historico === null) {
        sendJsonResponse(422, [
            'erro' => 'Não foi possível criar o registro no histórico.',
        ]);
    }

    sendJsonResponse(201, [
        'historico' => $historico,
    ]);
}
