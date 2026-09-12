<?php

declare(strict_types=1);

use Elos\Controllers\FormularioController;
use Elos\Services\AuthorizationService;

function handleFormularioRequest(
    string $method,
    int $eventoId,
    callable $formularioControllerFactory,
    callable $authorizationFactory
): never {
    $authorization = $authorizationFactory();

    if ($method === 'GET') {
        if (!$authorization->hasRole('COLABORADOR')) {
            sendJsonResponse(403, [
                'erro' => 'Acesso negado.',
            ]);
        }

        $controller = $formularioControllerFactory();

        sendJsonResponse(200, [
            'formularios' => $controller->indexByEvento($eventoId),
        ]);
    }

    if ($method === 'POST') {
        if (!$authorization->hasRole('GESTOR')) {
            sendJsonResponse(403, [
                'erro' => 'Acesso negado.',
            ]);
        }

        $data = readJsonPayload();

        $tipo = $data['tipo'] ?? null;
        $dataPrevisao = $data['data_previsao'] ?? null;
        $dataEnvio = $data['data_envio'] ?? null;
        $status = $data['status'] ?? 'PENDENTE';
        $observacoes = $data['observacoes'] ?? null;

        if (!is_string($tipo) || trim($tipo) === '') {
            sendJsonResponse(422, [
                'erro' => 'O campo tipo é obrigatório.',
            ]);
        }

        $controller = $formularioControllerFactory();

        if (!is_string($status) || !$controller->isValidStatus($status)) {
            sendJsonResponse(422, [
                'erro' => 'Status inválido.',
            ]);
        }

        if ($dataPrevisao !== null && !is_string($dataPrevisao)) {
            sendJsonResponse(422, [
                'erro' => 'Data de previsão inválida.',
            ]);
        }

        if ($dataEnvio !== null && !is_string($dataEnvio)) {
            sendJsonResponse(422, [
                'erro' => 'Data de envio inválida.',
            ]);
        }

        if ($observacoes !== null && !is_string($observacoes)) {
            sendJsonResponse(422, [
                'erro' => 'Observações inválidas.',
            ]);
        }

        $formulario = $controller->create(
            $eventoId,
            trim($tipo),
            $dataPrevisao,
            $dataEnvio,
            $status,
            $observacoes
        );

        if ($formulario === null) {
            sendJsonResponse(500, [
                'erro' => 'Não foi possível criar o formulário.',
            ]);
        }

        sendJsonResponse(201, [
            'mensagem' => 'Formulário criado com sucesso.',
            'formulario' => $formulario,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

function handleFormularioByIdRequest(
    string $method,
    int $eventoId,
    int $id,
    callable $formularioControllerFactory,
    callable $authorizationFactory
): never {
    $authorization = $authorizationFactory();

    if ($method === 'GET') {
        if (!$authorization->hasRole('COLABORADOR')) {
            sendJsonResponse(403, [
                'erro' => 'Acesso negado.',
            ]);
        }

        $controller = $formularioControllerFactory();
        $formulario = $controller->show($id);

        if (
            $formulario === null
            || (int) $formulario['evento_id'] !== $eventoId
        ) {
            sendJsonResponse(404, [
                'erro' => 'Formulário não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'formulario' => $formulario,
        ]);
    }

    if ($method === 'PUT') {
        if (!$authorization->hasRole('GESTOR')) {
            sendJsonResponse(403, [
                'erro' => 'Acesso negado.',
            ]);
        }

        $controller = $formularioControllerFactory();
        $existente = $controller->show($id);

        if (
            $existente === null
            || (int) $existente['evento_id'] !== $eventoId
        ) {
            sendJsonResponse(404, [
                'erro' => 'Formulário não encontrado.',
            ]);
        }

        $data = readJsonPayload();

        $tipo = $data['tipo'] ?? $existente['tipo'];
        $dataPrevisao = $data['data_previsao'] ?? $existente['data_previsao'];
        $dataEnvio = $data['data_envio'] ?? $existente['data_envio'];
        $status = $data['status'] ?? $existente['status'];
        $observacoes = $data['observacoes'] ?? $existente['observacoes'];

        if (!is_string($tipo) || trim($tipo) === '') {
            sendJsonResponse(422, [
                'erro' => 'O campo tipo é obrigatório.',
            ]);
        }

        if (!is_string($status) || !$controller->isValidStatus($status)) {
            sendJsonResponse(422, [
                'erro' => 'Status inválido.',
            ]);
        }

        if ($dataPrevisao !== null && !is_string($dataPrevisao)) {
            sendJsonResponse(422, [
                'erro' => 'Data de previsão inválida.',
            ]);
        }

        if ($dataEnvio !== null && !is_string($dataEnvio)) {
            sendJsonResponse(422, [
                'erro' => 'Data de envio inválida.',
            ]);
        }

        if ($observacoes !== null && !is_string($observacoes)) {
            sendJsonResponse(422, [
                'erro' => 'Observações inválidas.',
            ]);
        }

        $formulario = $controller->update(
            $id,
            $eventoId,
            trim($tipo),
            $dataPrevisao,
            $dataEnvio,
            $status,
            $observacoes
        );

        if ($formulario === null) {
            sendJsonResponse(500, [
                'erro' => 'Não foi possível atualizar o formulário.',
            ]);
        }

        sendJsonResponse(200, [
            'mensagem' => 'Formulário atualizado com sucesso.',
            'formulario' => $formulario,
        ]);
    }

    if ($method === 'DELETE') {
        if (!$authorization->hasRole('GESTOR')) {
            sendJsonResponse(403, [
                'erro' => 'Acesso negado.',
            ]);
        }

        $controller = $formularioControllerFactory();

        if (!$controller->delete($id, $eventoId)) {
            sendJsonResponse(404, [
                'erro' => 'Formulário não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'mensagem' => 'Formulário excluído com sucesso.',
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}