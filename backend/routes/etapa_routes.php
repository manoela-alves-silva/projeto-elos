<?php

declare(strict_types=1);

use Elos\Controllers\EtapaController;
use Elos\Services\AuthorizationService;

/**
 * Processa as requisições de etapas.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * POST: somente gestor ou administrador pode cadastrar.
 *
 * @param callable(): EtapaController $etapaControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleEtapaRequest(
    string $method,
    callable $etapaControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $etapas = $etapaControllerFactory()->index();

        sendJsonResponse(200, [
            'etapas' => $etapas,
        ]);
    }

    if ($method === 'POST') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $nome = $payload['nome'] ?? null;
        $ordem = $payload['ordem'] ?? null;

        if (!is_string($nome) || trim($nome) === '') {
            sendJsonResponse(400, [
                'erro' => 'Nome é obrigatório.',
            ]);
        }

        if (!is_int($ordem) || $ordem <= 0) {
            sendJsonResponse(400, [
                'erro' => 'Ordem deve ser um número inteiro positivo.',
            ]);
        }

        $etapa = $etapaControllerFactory()->create(
            trim($nome),
            $ordem
        );

        if ($etapa === null) {
            sendJsonResponse(409, [
                'erro' => 'Etapa já cadastrada ou inválida.',
            ]);
        }

        sendJsonResponse(201, [
            'etapa' => $etapa,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * Busca ou edita uma etapa específica pelo ID.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * PUT: somente gestor ou administrador pode editar.
 *
 * @param callable(): EtapaController $etapaControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleEtapaByIdRequest(
    string $method,
    int $id,
    callable $etapaControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $etapa = $etapaControllerFactory()->show($id);

        if ($etapa === null) {
            sendJsonResponse(404, [
                'erro' => 'Etapa não encontrada.',
            ]);
        }

        sendJsonResponse(200, [
            'etapa' => $etapa,
        ]);
    }

    if ($method === 'PUT') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $etapaAtual = $etapaControllerFactory()->show($id);

        if ($etapaAtual === null) {
            sendJsonResponse(404, [
                'erro' => 'Etapa não encontrada.',
            ]);
        }

        $nome = $payload['nome'] ?? $etapaAtual['nome'];
        $ordem = $payload['ordem'] ?? (int) $etapaAtual['ordem'];
        $ativa = $payload['ativa'] ?? null;

        if (!is_string($nome) || trim($nome) === '') {
            sendJsonResponse(400, [
                'erro' => 'Nome é obrigatório.',
            ]);
        }

        if (!is_int($ordem) || $ordem <= 0) {
            sendJsonResponse(400, [
                'erro' => 'Ordem deve ser um número inteiro positivo.',
            ]);
        }

        if ($ativa !== null && !is_bool($ativa)) {
            sendJsonResponse(400, [
                'erro' => 'O campo ativa deve ser booleano.',
            ]);
        }

        if (
            !array_key_exists('nome', $payload)
            && !array_key_exists('ordem', $payload)
            && $ativa === null
        ) {
            sendJsonResponse(400, [
                'erro' => 'Informe pelo menos um campo para atualização.',
            ]);
        }

        $etapa = $etapaControllerFactory()->update(
            $id,
            trim($nome),
            $ordem
        );

        if ($etapa === null) {
            sendJsonResponse(409, [
                'erro' => 'Não foi possível atualizar a etapa.',
            ]);
        }

        if ($ativa !== null) {
            $etapa = $etapaControllerFactory()->setActive(
                $id,
                $ativa
            );
        }

        if ($etapa === null) {
            sendJsonResponse(404, [
                'erro' => 'Etapa não encontrada.',
            ]);
        }

        sendJsonResponse(200, [
            'etapa' => $etapa,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}


