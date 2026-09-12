<?php

declare(strict_types=1);

use Elos\Controllers\CategoriaTarefaController;
use Elos\Services\AuthorizationService;

/**
 * Processa as requisições de categorias de tarefas.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * POST: somente gestor ou administrador pode cadastrar.
 *
 * @param callable(): CategoriaTarefaController $categoriaTarefaControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleCategoriaTarefaRequest(
    string $method,
    callable $categoriaTarefaControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $categorias = $categoriaTarefaControllerFactory()->index();

        sendJsonResponse(200, [
            'categorias_tarefa' => $categorias,
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

        $categoria = $categoriaTarefaControllerFactory()->create(
            trim($nome)
        );

        if ($categoria === null) {
            sendJsonResponse(409, [
                'erro' => 'Categoria de tarefa já cadastrada ou inválida.',
            ]);
        }

        sendJsonResponse(201, [
            'categoria_tarefa' => $categoria,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * Busca ou edita uma categoria de tarefa específica pelo ID.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * PUT: somente gestor ou administrador pode editar.
 *
 * @param callable(): CategoriaTarefaController $categoriaTarefaControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleCategoriaTarefaByIdRequest(
    string $method,
    int $id,
    callable $categoriaTarefaControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $categoria = $categoriaTarefaControllerFactory()->show($id);

        if ($categoria === null) {
            sendJsonResponse(404, [
                'erro' => 'Categoria de tarefa não encontrada.',
            ]);
        }

        sendJsonResponse(200, [
            'categoria_tarefa' => $categoria,
        ]);
    }

    if ($method === 'PUT') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $categoriaAtual = $categoriaTarefaControllerFactory()->show($id);

        if ($categoriaAtual === null) {
            sendJsonResponse(404, [
                'erro' => 'Categoria de tarefa não encontrada.',
            ]);
        }

        $nome = $payload['nome'] ?? $categoriaAtual['nome'];
        $ativa = $payload['ativa'] ?? null;

        if (!is_string($nome) || trim($nome) === '') {
            sendJsonResponse(400, [
                'erro' => 'Nome é obrigatório.',
            ]);
        }

        if ($ativa !== null && !is_bool($ativa)) {
            sendJsonResponse(400, [
                'erro' => 'O campo ativa deve ser booleano.',
            ]);
        }

        if (
            !array_key_exists('nome', $payload)
            && $ativa === null
        ) {
            sendJsonResponse(400, [
                'erro' => 'Informe pelo menos um campo para atualização.',
            ]);
        }

        $categoria = $categoriaTarefaControllerFactory()->update(
            $id,
            trim($nome)
        );

        if ($categoria === null) {
            sendJsonResponse(409, [
                'erro' => 'Não foi possível atualizar a categoria de tarefa.',
            ]);
        }

        if ($ativa !== null) {
            $categoria = $categoriaTarefaControllerFactory()->setActive(
                $id,
                $ativa
            );
        }

        if ($categoria === null) {
            sendJsonResponse(404, [
                'erro' => 'Categoria de tarefa não encontrada.',
            ]);
        }

        sendJsonResponse(200, [
            'categoria_tarefa' => $categoria,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}
