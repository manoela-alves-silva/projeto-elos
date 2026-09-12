<?php

declare(strict_types=1);

use Elos\Controllers\CursoController;
use Elos\Services\AuthorizationService;

/**
 * Processa as requisições de cursos.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * POST: somente gestor ou administrador pode cadastrar.
 *
 * @param callable(): CursoController $cursoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleCursoRequest(
    string $method,
    callable $cursoControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $cursos = $cursoControllerFactory()->index();

        sendJsonResponse(200, [
            'cursos' => $cursos,
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

        $curso = $cursoControllerFactory()->create($nome);

        if ($curso === null) {
            sendJsonResponse(409, [
                'erro' => 'Curso já cadastrado ou inválido.',
            ]);
        }

        sendJsonResponse(201, [
            'curso' => $curso,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * Busca ou edita um curso específico pelo ID.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * PUT: somente gestor ou administrador pode editar.
 *
 * @param callable(): CursoController $cursoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleCursoByIdRequest(
    string $method,
    int $id,
    callable $cursoControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $curso = $cursoControllerFactory()->show($id);

        if ($curso === null) {
            sendJsonResponse(404, [
                'erro' => 'Curso não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'curso' => $curso,
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

        $cursoAtual = $cursoControllerFactory()->show($id);

        if ($cursoAtual === null) {
            sendJsonResponse(404, [
                'erro' => 'Curso não encontrado.',
            ]);
        }

        $nomeAtualizado = $nome !== null
            ? trim($nome)
            : (string) $cursoAtual['nome'];

        if ($nomeAtualizado === '') {
            sendJsonResponse(400, [
                'erro' => 'Nome não pode ser vazio.',
            ]);
        }

        $curso = $cursoControllerFactory()->update(
            $id,
            $nomeAtualizado
        );

        if ($curso === null) {
            sendJsonResponse(409, [
                'erro' => 'Não foi possível atualizar o curso.',
            ]);
        }

        if ($ativo !== null) {
            $curso = $cursoControllerFactory()->setActive(
                $id,
                $ativo
            );
        }

        if ($curso === null) {
            sendJsonResponse(404, [
                'erro' => 'Curso não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'curso' => $curso,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}


