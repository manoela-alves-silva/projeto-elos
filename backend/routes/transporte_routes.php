<?php

declare(strict_types=1);

use Elos\Controllers\TransporteController;
use Elos\Services\AuthorizationService;

function handleTransporteRequest(
    string $method,
    int $eventoId,
    callable $transporteControllerFactory,
    callable $authorizationFactory
): never {
    $authorization = $authorizationFactory();

    if ($method === 'GET') {
        if (!$authorization->hasRole('COLABORADOR')) {
            sendJsonResponse(403, [
                'erro' => 'Acesso negado.',
            ]);
        }

        $controller = $transporteControllerFactory();

        sendJsonResponse(200, [
            'transportes' => $controller->indexByEvento($eventoId),
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
        $origem = $data['origem'] ?? null;
        $destino = $data['destino'] ?? null;
        $dataSolicitacao = $data['data_solicitacao'] ?? null;
        $dataTransporte = $data['data_transporte'] ?? null;
        $horario = $data['horario'] ?? null;
        $status = $data['status'] ?? 'SOLICITADO';
        $observacoes = $data['observacoes'] ?? null;

        if (!is_string($tipo) || trim($tipo) === '') {
            sendJsonResponse(422, [
                'erro' => 'O campo tipo é obrigatório.',
            ]);
        }

        $controller = $transporteControllerFactory();

        if (!is_string($status) || !$controller->isValidStatus($status)) {
            sendJsonResponse(422, [
                'erro' => 'Status inválido.',
            ]);
        }

        if ($origem !== null && !is_string($origem)) {
            sendJsonResponse(422, [
                'erro' => 'Origem inválida.',
            ]);
        }

        if ($destino !== null && !is_string($destino)) {
            sendJsonResponse(422, [
                'erro' => 'Destino inválido.',
            ]);
        }

        if ($dataSolicitacao !== null && !is_string($dataSolicitacao)) {
            sendJsonResponse(422, [
                'erro' => 'Data de solicitação inválida.',
            ]);
        }

        if ($dataTransporte !== null && !is_string($dataTransporte)) {
            sendJsonResponse(422, [
                'erro' => 'Data de transporte inválida.',
            ]);
        }

        if ($horario !== null && !is_string($horario)) {
            sendJsonResponse(422, [
                'erro' => 'Horário inválido.',
            ]);
        }

        if ($observacoes !== null && !is_string($observacoes)) {
            sendJsonResponse(422, [
                'erro' => 'Observações inválidas.',
            ]);
        }

        $transporte = $controller->create(
            $eventoId,
            trim($tipo),
            $origem,
            $destino,
            $dataSolicitacao,
            $dataTransporte,
            $horario,
            $status,
            $observacoes
        );

        if ($transporte === null) {
            sendJsonResponse(500, [
                'erro' => 'Não foi possível criar o transporte.',
            ]);
        }

        sendJsonResponse(201, [
            'mensagem' => 'Transporte criado com sucesso.',
            'transporte' => $transporte,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

function handleTransporteByIdRequest(
    string $method,
    int $eventoId,
    int $id,
    callable $transporteControllerFactory,
    callable $authorizationFactory
): never {
    $authorization = $authorizationFactory();

    if ($method === 'GET') {
        if (!$authorization->hasRole('COLABORADOR')) {
            sendJsonResponse(403, [
                'erro' => 'Acesso negado.',
            ]);
        }

        $controller = $transporteControllerFactory();
        $transporte = $controller->show($id);

        if (
            $transporte === null
            || (int) $transporte['evento_id'] !== $eventoId
        ) {
            sendJsonResponse(404, [
                'erro' => 'Transporte não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'transporte' => $transporte,
        ]);
    }

    if ($method === 'PUT') {
        if (!$authorization->hasRole('GESTOR')) {
            sendJsonResponse(403, [
                'erro' => 'Acesso negado.',
            ]);
        }

        $controller = $transporteControllerFactory();
        $existente = $controller->show($id);

        if (
            $existente === null
            || (int) $existente['evento_id'] !== $eventoId
        ) {
            sendJsonResponse(404, [
                'erro' => 'Transporte não encontrado.',
            ]);
        }

        $data = readJsonPayload();

        $tipo = $data['tipo'] ?? $existente['tipo'];
        $origem = $data['origem'] ?? $existente['origem'];
        $destino = $data['destino'] ?? $existente['destino'];
        $dataSolicitacao = $data['data_solicitacao'] ?? $existente['data_solicitacao'];
        $dataTransporte = $data['data_transporte'] ?? $existente['data_transporte'];
        $horario = $data['horario'] ?? $existente['horario'];
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

        if ($origem !== null && !is_string($origem)) {
            sendJsonResponse(422, [
                'erro' => 'Origem inválida.',
            ]);
        }

        if ($destino !== null && !is_string($destino)) {
            sendJsonResponse(422, [
                'erro' => 'Destino inválido.',
            ]);
        }

        if ($dataSolicitacao !== null && !is_string($dataSolicitacao)) {
            sendJsonResponse(422, [
                'erro' => 'Data de solicitação inválida.',
            ]);
        }

        if ($dataTransporte !== null && !is_string($dataTransporte)) {
            sendJsonResponse(422, [
                'erro' => 'Data de transporte inválida.',
            ]);
        }

        if ($horario !== null && !is_string($horario)) {
            sendJsonResponse(422, [
                'erro' => 'Horário inválido.',
            ]);
        }

        if ($observacoes !== null && !is_string($observacoes)) {
            sendJsonResponse(422, [
                'erro' => 'Observações inválidas.',
            ]);
        }

        $transporte = $controller->update(
            $id,
            $eventoId,
            trim($tipo),
            $origem,
            $destino,
            $dataSolicitacao,
            $dataTransporte,
            $horario,
            $status,
            $observacoes
        );

        if ($transporte === null) {
            sendJsonResponse(500, [
                'erro' => 'Não foi possível atualizar o transporte.',
            ]);
        }

        sendJsonResponse(200, [
            'mensagem' => 'Transporte atualizado com sucesso.',
            'transporte' => $transporte,
        ]);
    }

    if ($method === 'DELETE') {
        if (!$authorization->hasRole('GESTOR')) {
            sendJsonResponse(403, [
                'erro' => 'Acesso negado.',
            ]);
        }

        $controller = $transporteControllerFactory();

        if (!$controller->delete($id, $eventoId)) {
            sendJsonResponse(404, [
                'erro' => 'Transporte não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'mensagem' => 'Transporte excluído com sucesso.',
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}
