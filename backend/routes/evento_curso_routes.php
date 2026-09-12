<?php

declare(strict_types=1);

use Elos\Controllers\EventoCursoController;
use Elos\Services\AuthorizationService;

function handleEventoCursoRequest(
    string $method,
    int $eventoId,
    callable $eventoCursoControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $cursos = $eventoCursoControllerFactory()->index($eventoId);

        sendJsonResponse(200, [
            'cursos' => $cursos,
        ]);
    }

    if ($method === 'POST') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();
        $cursoId = $payload['curso_id'] ?? null;

        if (!is_int($cursoId) || $cursoId <= 0) {
            sendJsonResponse(400, [
                'erro' => 'Curso é obrigatório.',
            ]);
        }

        $associado = $eventoCursoControllerFactory()->attach($eventoId, $cursoId);

        if (!$associado) {
            sendJsonResponse(409, [
                'erro' => 'Curso já está associado ao evento ou os dados são inválidos.',
            ]);
        }

        sendJsonResponse(201, [
            'mensagem' => 'Curso associado ao evento com sucesso.',
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}


function handleEventoCursoByCursoRequest(
    string $method,
    int $eventoId,
    int $cursoId,
    callable $eventoCursoControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'GESTOR');

    if ($method !== 'DELETE') {
        sendJsonResponse(405, [
            'erro' => 'Método não permitido.',
        ]);
    }

    $removido = $eventoCursoControllerFactory()->detach($eventoId, $cursoId);

    if (!$removido) {
        sendJsonResponse(404, [
            'erro' => 'Associação entre evento e curso não encontrada.',
        ]);
    }

    sendJsonResponse(200, [
        'mensagem' => 'Curso removido do evento com sucesso.',
    ]);
}

