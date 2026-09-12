<?php

declare(strict_types=1);

use Elos\Controllers\VisitaController;
use Elos\Services\AuthorizationService;

/**
 * @param callable(): VisitaController $visitaControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleVisitaRequest(
    string $method,
    int $eventoId,
    callable $visitaControllerFactory,
    callable $authorizationFactory
): void {
    if ($method === 'GET') {
        requireRole($authorizationFactory, 'COLABORADOR');

        sendJsonResponse(200, [
            'visitas' => $visitaControllerFactory()->indexByEvento($eventoId),
        ]);
    }

    if ($method === 'POST') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $instituicao = $payload['instituicao'] ?? null;
        $responsavel = $payload['responsavel'] ?? null;
        $quantidadePessoas = $payload['quantidade_pessoas'] ?? null;
        $data = $payload['data'] ?? null;
        $horario = $payload['horario'] ?? null;
        $status = $payload['status'] ?? null;
        $observacoes = $payload['observacoes'] ?? null;

        if (
            !is_string($instituicao)
            || trim($instituicao) === ''
            || !is_string($responsavel)
            || trim($responsavel) === ''
            || !is_int($quantidadePessoas)
            || $quantidadePessoas < 1
            || ($data !== null && !is_string($data))
            || ($horario !== null && !is_string($horario))
            || !is_string($status)
            || ($observacoes !== null && !is_string($observacoes))
        ) {
            sendJsonResponse(422, [
                'erro' => 'Dados da visita inválidos.',
            ]);
        }

        $visita = $visitaControllerFactory()->create(
            $eventoId,
            trim($instituicao),
            trim($responsavel),
            $quantidadePessoas,
            $data,
            $horario,
            $status,
            $observacoes
        );

        if ($visita === null) {
            sendJsonResponse(422, [
                'erro' => 'Não foi possível criar a visita.',
            ]);
        }

        sendJsonResponse(201, [
            'mensagem' => 'Visita criada com sucesso.',
            'visita' => $visita,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * @param callable(): VisitaController $visitaControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleVisitaByIdRequest(
    string $method,
    int $eventoId,
    int $id,
    callable $visitaControllerFactory,
    callable $authorizationFactory
): void {
    if ($method === 'GET') {
        requireRole($authorizationFactory, 'COLABORADOR');

        $visita = $visitaControllerFactory()->show($id, $eventoId);

        if ($visita === null) {
            sendJsonResponse(404, [
                'erro' => 'Visita não encontrada.',
            ]);
        }

        sendJsonResponse(200, [
            'visita' => $visita,
        ]);
    }

    if ($method === 'PUT') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $instituicao = $payload['instituicao'] ?? null;
        $responsavel = $payload['responsavel'] ?? null;
        $quantidadePessoas = $payload['quantidade_pessoas'] ?? null;
        $data = $payload['data'] ?? null;
        $horario = $payload['horario'] ?? null;
        $status = $payload['status'] ?? null;
        $observacoes = $payload['observacoes'] ?? null;

        if (
            !is_string($instituicao)
            || trim($instituicao) === ''
            || !is_string($responsavel)
            || trim($responsavel) === ''
            || !is_int($quantidadePessoas)
            || $quantidadePessoas < 1
            || ($data !== null && !is_string($data))
            || ($horario !== null && !is_string($horario))
            || !is_string($status)
            || ($observacoes !== null && !is_string($observacoes))
        ) {
            sendJsonResponse(422, [
                'erro' => 'Dados da visita inválidos.',
            ]);
        }

        $visita = $visitaControllerFactory()->update(
            $id,
            $eventoId,
            trim($instituicao),
            trim($responsavel),
            $quantidadePessoas,
            $data,
            $horario,
            $status,
            $observacoes
        );

        if ($visita === null) {
            sendJsonResponse(422, [
                'erro' => 'Não foi possível atualizar a visita.',
            ]);
        }

        sendJsonResponse(200, [
            'mensagem' => 'Visita atualizada com sucesso.',
            'visita' => $visita,
        ]);
    }

    if ($method === 'DELETE') {
        requireRole($authorizationFactory, 'GESTOR');

        $deleted = $visitaControllerFactory()->delete(
            $id,
            $eventoId
        );

        if (!$deleted) {
            sendJsonResponse(404, [
                'erro' => 'Visita não encontrada.',
            ]);
        }

        sendJsonResponse(200, [
            'mensagem' => 'Visita excluída com sucesso.',
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}
