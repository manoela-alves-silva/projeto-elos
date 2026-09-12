<?php

declare(strict_types=1);

use Elos\Controllers\ResponsavelController;
use Elos\Services\AuthorizationService;

/**
 * Processa as requisições de responsáveis.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * POST: somente gestor ou administrador pode cadastrar.
 *
 * @param callable(): ResponsavelController $responsavelControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleResponsavelRequest(
    string $method,
    callable $responsavelControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $responsaveis = $responsavelControllerFactory()->index();

        sendJsonResponse(200, [
            'responsaveis' => $responsaveis,
        ]);
    }

    if ($method === 'POST') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $nome = $payload['nome'] ?? null;
        $tipo = $payload['tipo'] ?? null;
        $email = $payload['email'] ?? null;
        $telefone = $payload['telefone'] ?? null;
        $observacoes = $payload['observacoes'] ?? null;

        if (!is_string($nome) || trim($nome) === '') {
            sendJsonResponse(400, [
                'erro' => 'Nome é obrigatório.',
            ]);
        }

        if (!is_string($tipo) || trim($tipo) === '') {
            sendJsonResponse(400, [
                'erro' => 'Tipo é obrigatório.',
            ]);
        }

        if ($email !== null && !is_string($email)) {
            sendJsonResponse(400, [
                'erro' => 'Email inválido.',
            ]);
        }

        if ($telefone !== null && !is_string($telefone)) {
            sendJsonResponse(400, [
                'erro' => 'Telefone inválido.',
            ]);
        }

        if ($observacoes !== null && !is_string($observacoes)) {
            sendJsonResponse(400, [
                'erro' => 'Observações inválidas.',
            ]);
        }

        $responsavel = $responsavelControllerFactory()->create(
            $nome,
            $tipo,
            $email,
            $telefone,
            $observacoes
        );

        if ($responsavel === null) {
            sendJsonResponse(400, [
                'erro' => 'Não foi possível cadastrar o responsável.',
            ]);
        }

        sendJsonResponse(201, [
            'responsavel' => $responsavel,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * Busca ou edita um responsável específico pelo ID.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * PUT: somente gestor ou administrador pode editar.
 *
 * @param callable(): ResponsavelController $responsavelControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleResponsavelByIdRequest(
    string $method,
    int $id,
    callable $responsavelControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $responsavel = $responsavelControllerFactory()->show($id);

        if ($responsavel === null) {
            sendJsonResponse(404, [
                'erro' => 'Responsável não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'responsavel' => $responsavel,
        ]);
    }

    if ($method === 'PUT') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $responsavelAtual = $responsavelControllerFactory()->show($id);

        if ($responsavelAtual === null) {
            sendJsonResponse(404, [
                'erro' => 'Responsável não encontrado.',
            ]);
        }

        $nome = $payload['nome'] ?? $responsavelAtual['nome'];
        $tipo = $payload['tipo'] ?? $responsavelAtual['tipo'];
        $email = array_key_exists('email', $payload)
            ? $payload['email']
            : $responsavelAtual['email'];
        $telefone = array_key_exists('telefone', $payload)
            ? $payload['telefone']
            : $responsavelAtual['telefone'];
        $observacoes = array_key_exists('observacoes', $payload)
            ? $payload['observacoes']
            : $responsavelAtual['observacoes'];
        $ativo = $payload['ativo'] ?? null;

        if (!is_string($nome) || trim($nome) === '') {
            sendJsonResponse(400, [
                'erro' => 'Nome é obrigatório.',
            ]);
        }

        if (!is_string($tipo) || trim($tipo) === '') {
            sendJsonResponse(400, [
                'erro' => 'Tipo é obrigatório.',
            ]);
        }

        if ($email !== null && !is_string($email)) {
            sendJsonResponse(400, [
                'erro' => 'Email inválido.',
            ]);
        }

        if ($telefone !== null && !is_string($telefone)) {
            sendJsonResponse(400, [
                'erro' => 'Telefone inválido.',
            ]);
        }

        if ($observacoes !== null && !is_string($observacoes)) {
            sendJsonResponse(400, [
                'erro' => 'Observações inválidas.',
            ]);
        }

        if ($ativo !== null && !is_bool($ativo)) {
            sendJsonResponse(400, [
                'erro' => 'O campo ativo deve ser booleano.',
            ]);
        }

        $responsavel = $responsavelControllerFactory()->update(
            $id,
            $nome,
            $tipo,
            $email,
            $telefone,
            $observacoes
        );

        if ($responsavel === null) {
            sendJsonResponse(400, [
                'erro' => 'Não foi possível atualizar o responsável.',
            ]);
        }

        if ($ativo !== null) {
            $responsavel = $responsavelControllerFactory()->setActive(
                $id,
                $ativo
            );
        }

        if ($responsavel === null) {
            sendJsonResponse(404, [
                'erro' => 'Responsável não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'responsavel' => $responsavel,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}


