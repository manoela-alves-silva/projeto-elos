<?php

declare(strict_types=1);

use Elos\Controllers\TipoEventoController;
use Elos\Services\AuthorizationService;

/**
 * Processa as requisições de tipos de evento.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * POST: somente gestor ou administrador pode cadastrar.
 *
 * @param callable(): TipoEventoController $tipoEventoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleTipoEventoRequest(
    string $method,
    callable $tipoEventoControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $tiposEvento = $tipoEventoControllerFactory()->index();

        sendJsonResponse(200, [
            'tipos_evento' => $tiposEvento,
        ]);
    }

    if ($method === 'POST') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();
        $nome = $payload['nome'] ?? null;

        if (!is_string($nome) || trim($nome) === '') {
            sendJsonResponse(400, [
                'erro' => 'Nome é obrigatório.',
            ]);
        }

        $tipoEvento = $tipoEventoControllerFactory()->create($nome);

        if ($tipoEvento === null) {
            sendJsonResponse(409, [
                'erro' => 'Tipo de evento já cadastrado ou inválido.',
            ]);
        }

        sendJsonResponse(201, [
            'tipo_evento' => $tipoEvento,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * Busca ou edita um tipo de evento específico pelo ID.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * PUT: somente gestor ou administrador pode editar.
 *
 * @param callable(): TipoEventoController $tipoEventoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleTipoEventoByIdRequest(
    string $method,
    int $id,
    callable $tipoEventoControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $tipoEvento = $tipoEventoControllerFactory()->show($id);

        if ($tipoEvento === null) {
            sendJsonResponse(404, [
                'erro' => 'Tipo de evento não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'tipo_evento' => $tipoEvento,
        ]);
    }

    if ($method === 'PUT') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();
        $nome = $payload['nome'] ?? null;
        $ativo = $payload['ativo'] ?? null;

        if ($nome !== null && !is_string($nome)) {
            sendJsonResponse(400, [
                'erro' => 'Nome inválido.',
            ]);
        }

        if ($ativo !== null && !is_bool($ativo)) {
            sendJsonResponse(400, [
                'erro' => 'O campo ativo deve ser booleano.',
            ]);
        }

        if ($nome === null && $ativo === null) {
            sendJsonResponse(400, [
                'erro' => 'Informe pelo menos um campo para atualização.',
            ]);
        }

        $tipoEventoAtual = $tipoEventoControllerFactory()->show($id);

        if ($tipoEventoAtual === null) {
            sendJsonResponse(404, [
                'erro' => 'Tipo de evento não encontrado.',
            ]);
        }

        $nomeAtualizado = $nome !== null
            ? trim($nome)
            : (string) $tipoEventoAtual['nome'];

        if ($nomeAtualizado === '') {
            sendJsonResponse(400, [
                'erro' => 'Nome não pode ser vazio.',
            ]);
        }

        $tipoEvento = $tipoEventoControllerFactory()->update(
            $id,
            $nomeAtualizado
        );

        if ($tipoEvento === null) {
            sendJsonResponse(409, [
                'erro' => 'Não foi possível atualizar o tipo de evento.',
            ]);
        }

        if ($ativo !== null) {
            $tipoEvento = $tipoEventoControllerFactory()->setActive(
                $id,
                $ativo
            );
        }

        if ($tipoEvento === null) {
            sendJsonResponse(404, [
                'erro' => 'Tipo de evento não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'tipo_evento' => $tipoEvento,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

