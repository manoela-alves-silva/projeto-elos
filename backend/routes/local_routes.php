<?php

declare(strict_types=1);

use Elos\Controllers\LocalController;
use Elos\Services\AuthorizationService;

/**
 * Processa as requisições de locais.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * POST: somente gestor ou administrador pode cadastrar.
 *
 * @param callable(): LocalController $localControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleLocalRequest(
    string $method,
    callable $localControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $locais = $localControllerFactory()->index();

        sendJsonResponse(200, [
            'locais' => $locais,
        ]);
    }

    if ($method === 'POST') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $nome = $payload['nome'] ?? null;
        $descricao = $payload['descricao'] ?? null;

        if (!is_string($nome) || trim($nome) === '') {
            sendJsonResponse(400, [
                'erro' => 'Nome é obrigatório.',
            ]);
        }

        if ($descricao !== null && !is_string($descricao)) {
            sendJsonResponse(400, [
                'erro' => 'Descrição inválida.',
            ]);
        }

        $local = $localControllerFactory()->create(
            $nome,
            $descricao
        );

        if ($local === null) {
            sendJsonResponse(409, [
                'erro' => 'Local já cadastrado ou inválido.',
            ]);
        }

        sendJsonResponse(201, [
            'local' => $local,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * Busca ou edita um local específico pelo ID.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * PUT: somente gestor ou administrador pode editar.
 *
 * @param callable(): LocalController $localControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleLocalByIdRequest(
    string $method,
    int $id,
    callable $localControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $local = $localControllerFactory()->show($id);

        if ($local === null) {
            sendJsonResponse(404, [
                'erro' => 'Local não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'local' => $local,
        ]);
    }

    if ($method === 'PUT') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $localAtual = $localControllerFactory()->show($id);

        if ($localAtual === null) {
            sendJsonResponse(404, [
                'erro' => 'Local não encontrado.',
            ]);
        }

        $nome = $payload['nome'] ?? $localAtual['nome'];

        $descricao = array_key_exists('descricao', $payload)
            ? $payload['descricao']
            : $localAtual['descricao'];

        $ativo = $payload['ativo'] ?? null;

        if (!is_string($nome) || trim($nome) === '') {
            sendJsonResponse(400, [
                'erro' => 'Nome é obrigatório.',
            ]);
        }

        if ($descricao !== null && !is_string($descricao)) {
            sendJsonResponse(400, [
                'erro' => 'Descrição inválida.',
            ]);
        }

        if ($ativo !== null && !is_bool($ativo)) {
            sendJsonResponse(400, [
                'erro' => 'O campo ativo deve ser booleano.',
            ]);
        }

        if (
            !array_key_exists('nome', $payload)
            && !array_key_exists('descricao', $payload)
            && $ativo === null
        ) {
            sendJsonResponse(400, [
                'erro' => 'Informe pelo menos um campo para atualização.',
            ]);
        }

        $local = $localControllerFactory()->update(
            $id,
            $nome,
            $descricao
        );

        if ($local === null) {
            sendJsonResponse(409, [
                'erro' => 'Não foi possível atualizar o local.',
            ]);
        }

        if ($ativo !== null) {
            $local = $localControllerFactory()->setActive(
                $id,
                $ativo
            );
        }

        if ($local === null) {
            sendJsonResponse(404, [
                'erro' => 'Local não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'local' => $local,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}
