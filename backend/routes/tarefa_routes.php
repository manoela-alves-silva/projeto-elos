<?php

declare(strict_types=1);

use Elos\Controllers\TarefaController;
use Elos\Services\AuthorizationService;

/**
 * Lista ou cadastra tarefas de um evento.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * POST: somente gestor ou administrador pode cadastrar.
 * A etapa é opcional (checklist de necessidades do evento).
 *
 * @param callable(): TarefaController $tarefaControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleTarefaRequest(
    string $method,
    int $eventoId,
    callable $tarefaControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($eventoId <= 0) {
        sendJsonResponse(400, [
            'erro' => 'Evento inválido.',
        ]);
    }

    if ($method === 'GET') {
        $tarefas = $tarefaControllerFactory()->indexByEvento($eventoId);

        sendJsonResponse(200, [
            'tarefas' => $tarefas,
        ]);
    }

    if ($method === 'POST') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $usuarioResponsavelId = $payload['usuario_responsavel_id'] ?? null;
        $etapaId = $payload['etapa_id'] ?? null;
        $categoriaId = $payload['categoria_id'] ?? null;
        $titulo = $payload['titulo'] ?? null;
        $descricao = $payload['descricao'] ?? null;
        $prazo = $payload['prazo'] ?? null;
        $prioridade = $payload['prioridade'] ?? null;
        $status = $payload['status'] ?? null;
        $observacoes = $payload['observacoes'] ?? null;
        // Responsável em texto livre (qualquer pessoa, mesmo sem conta).
        $responsavelNome = $payload['responsavel_nome'] ?? null;

        if (
            $usuarioResponsavelId !== null
            && (!is_int($usuarioResponsavelId) || $usuarioResponsavelId <= 0)
        ) {
            sendJsonResponse(400, [
                'erro' => 'Responsável inválido.',
            ]);
        }

        // Etapa é opcional: necessidades do checklist do evento
        // (ex.: "comprar pregos") não pertencem a uma etapa.
        if ($etapaId !== null && (!is_int($etapaId) || $etapaId <= 0)) {
            sendJsonResponse(400, [
                'erro' => 'Etapa inválida.',
            ]);
        }

        if (
            $categoriaId !== null
            && (!is_int($categoriaId) || $categoriaId <= 0)
        ) {
            sendJsonResponse(400, [
                'erro' => 'Categoria inválida.',
            ]);
        }

        if (!is_string($titulo) || trim($titulo) === '') {
            sendJsonResponse(400, [
                'erro' => 'Título é obrigatório.',
            ]);
        }

        if ($descricao !== null && !is_string($descricao)) {
            sendJsonResponse(400, [
                'erro' => 'Descrição inválida.',
            ]);
        }

        if ($prazo !== null && !is_string($prazo)) {
            sendJsonResponse(400, [
                'erro' => 'Prazo inválido.',
            ]);
        }

        if (!is_string($prioridade) || trim($prioridade) === '') {
            sendJsonResponse(400, [
                'erro' => 'Prioridade é obrigatória.',
            ]);
        }

        if (!is_string($status) || trim($status) === '') {
            sendJsonResponse(400, [
                'erro' => 'Status é obrigatório.',
            ]);
        }

        if ($observacoes !== null && !is_string($observacoes)) {
            sendJsonResponse(400, [
                'erro' => 'Observações inválidas.',
            ]);
        }

        if (
            $responsavelNome !== null
            && (!is_string($responsavelNome) || mb_strlen(trim($responsavelNome)) > 150)
        ) {
            sendJsonResponse(400, [
                'erro' => 'Responsável inválido.',
            ]);
        }

        $responsavelNome = is_string($responsavelNome) && trim($responsavelNome) !== ''
            ? trim($responsavelNome)
            : null;

        $tarefaController = $tarefaControllerFactory();

        if (!$tarefaController->isValidPriority($prioridade)) {
            sendJsonResponse(400, [
                'erro' => 'Prioridade inválida.',
            ]);
        }

        if (!$tarefaController->isValidStatus($status)) {
            sendJsonResponse(400, [
                'erro' => 'Status inválido.',
            ]);
        }

        $tarefa = $tarefaController->create(
            $eventoId,
            $usuarioResponsavelId,
            $etapaId,
            $categoriaId,
            $titulo,
            $descricao,
            $prazo,
            $prioridade,
            $status,
            $observacoes,
            $responsavelNome
        );

        if ($tarefa === null) {
            sendJsonResponse(400, [
                'erro' => 'Não foi possível cadastrar a tarefa.',
            ]);
        }

        sendJsonResponse(201, [
            'tarefa' => $tarefa,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * Altera somente o status de uma tarefa.
 *
 * GESTOR/ADMIN: podem alterar o status de qualquer tarefa do evento
 * (mesmo resultado que já obtêm via PUT completo).
 * COLABORADOR: só pode alterar o status de tarefas cujo
 * usuario_responsavel_id seja o do próprio usuário autenticado na
 * sessão — nunca um usuario_id enviado pelo cliente. Nenhum outro
 * campo da tarefa é aceito nesta rota.
 *
 * @param callable(): TarefaController $tarefaControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleTarefaStatusRequest(
    string $method,
    int $eventoId,
    int $id,
    callable $tarefaControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method !== 'PUT') {
        sendJsonResponse(405, [
            'erro' => 'Método não permitido.',
        ]);
    }

    if ($eventoId <= 0 || $id <= 0) {
        sendJsonResponse(400, [
            'erro' => 'Evento ou tarefa inválidos.',
        ]);
    }

    $tarefaController = $tarefaControllerFactory();

    $tarefa = $tarefaController->show($id);

    if ($tarefa === null || (int) $tarefa['evento_id'] !== $eventoId) {
        sendJsonResponse(404, [
            'erro' => 'Tarefa não encontrada.',
        ]);
    }

    $authorization = $authorizationFactory();

    if (!$authorization->hasRole('GESTOR')) {
        // Perfil COLABORADOR (a única possibilidade aqui, já que
        // requireRole acima exige ao menos COLABORADOR): só pode
        // mexer na própria tarefa. O usuário vem da sessão, nunca
        // de um campo enviado pelo cliente.
        $usuarioAtual = $authorization->currentUser();
        $usuarioAtualId = (int) ($usuarioAtual['id'] ?? 0);

        $responsavelId = $tarefa['usuario_responsavel_id'] !== null
            ? (int) $tarefa['usuario_responsavel_id']
            : null;

        if ($responsavelId === null || $responsavelId !== $usuarioAtualId) {
            sendJsonResponse(403, [
                'erro' => 'Você só pode alterar o status de tarefas atribuídas a você.',
            ]);
        }
    }

    $payload = readJsonPayload();
    $status = $payload['status'] ?? null;

    if (!is_string($status) || trim($status) === '') {
        sendJsonResponse(400, [
            'erro' => 'Status é obrigatório.',
        ]);
    }

    if (!$tarefaController->isValidStatus($status)) {
        sendJsonResponse(400, [
            'erro' => 'Status inválido.',
        ]);
    }

    $tarefaAtualizada = $tarefaController->updateStatus(
        $id,
        $eventoId,
        $status
    );

    if ($tarefaAtualizada === null) {
        sendJsonResponse(400, [
            'erro' => 'Não foi possível atualizar o status da tarefa.',
        ]);
    }

    sendJsonResponse(200, [
        'tarefa' => $tarefaAtualizada,
    ]);
}

/**
 * Busca, edita ou exclui uma tarefa específica.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * PUT: somente gestor ou administrador pode editar.
 * DELETE: somente gestor ou administrador pode excluir.
 *
 * @param callable(): TarefaController $tarefaControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleTarefaByIdRequest(
    string $method,
    int $eventoId,
    int $id,
    callable $tarefaControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($eventoId <= 0 || $id <= 0) {
        sendJsonResponse(400, [
            'erro' => 'Evento ou tarefa inválidos.',
        ]);
    }

    $tarefaController = $tarefaControllerFactory();

    if ($method === 'GET') {
        $tarefa = $tarefaController->show($id);

        if ($tarefa === null || (int) $tarefa['evento_id'] !== $eventoId) {
            sendJsonResponse(404, [
                'erro' => 'Tarefa não encontrada.',
            ]);
        }

        sendJsonResponse(200, [
            'tarefa' => $tarefa,
        ]);
    }

    if ($method === 'PUT') {
        requireRole($authorizationFactory, 'GESTOR');

        $tarefaAtual = $tarefaController->show($id);

        if (
            $tarefaAtual === null
            || (int) $tarefaAtual['evento_id'] !== $eventoId
        ) {
            sendJsonResponse(404, [
                'erro' => 'Tarefa não encontrada.',
            ]);
        }

        $payload = readJsonPayload();

        // array_key_exists: enviar null tira o responsável (com ?? o
        // null era ignorado e o responsável antigo voltava).
        $usuarioResponsavelId = array_key_exists('usuario_responsavel_id', $payload)
            ? $payload['usuario_responsavel_id']
            : ($tarefaAtual['usuario_responsavel_id'] !== null
                ? (int) $tarefaAtual['usuario_responsavel_id']
                : null);

        $etapaId = array_key_exists('etapa_id', $payload)
            ? $payload['etapa_id']
            : (
                $tarefaAtual['etapa_id'] !== null
                    ? (int) $tarefaAtual['etapa_id']
                    : null
            );

        $categoriaId = array_key_exists('categoria_id', $payload)
            ? $payload['categoria_id']
            : (
                $tarefaAtual['categoria_id'] !== null
                    ? (int) $tarefaAtual['categoria_id']
                    : null
            );

        $titulo = $payload['titulo']
            ?? (string) $tarefaAtual['titulo'];

        $descricao = array_key_exists('descricao', $payload)
            ? $payload['descricao']
            : $tarefaAtual['descricao'];

        $prazo = array_key_exists('prazo', $payload)
            ? $payload['prazo']
            : $tarefaAtual['prazo'];

        $prioridade = $payload['prioridade']
            ?? (string) $tarefaAtual['prioridade'];

        $status = $payload['status']
            ?? (string) $tarefaAtual['status'];

        $observacoes = array_key_exists('observacoes', $payload)
            ? $payload['observacoes']
            : $tarefaAtual['observacoes'];

        $responsavelNome = array_key_exists('responsavel_nome', $payload)
            ? $payload['responsavel_nome']
            : $tarefaAtual['responsavel_nome'];

        if (
            $usuarioResponsavelId !== null
            && (!is_int($usuarioResponsavelId) || $usuarioResponsavelId <= 0)
        ) {
            sendJsonResponse(400, [
                'erro' => 'Responsável inválido.',
            ]);
        }

        if ($etapaId !== null && (!is_int($etapaId) || $etapaId <= 0)) {
            sendJsonResponse(400, [
                'erro' => 'Etapa inválida.',
            ]);
        }

        if (
            $categoriaId !== null
            && (!is_int($categoriaId) || $categoriaId <= 0)
        ) {
            sendJsonResponse(400, [
                'erro' => 'Categoria inválida.',
            ]);
        }

        if (!is_string($titulo) || trim($titulo) === '') {
            sendJsonResponse(400, [
                'erro' => 'Título é obrigatório.',
            ]);
        }

        if ($descricao !== null && !is_string($descricao)) {
            sendJsonResponse(400, [
                'erro' => 'Descrição inválida.',
            ]);
        }

        if ($prazo !== null && !is_string($prazo)) {
            sendJsonResponse(400, [
                'erro' => 'Prazo inválido.',
            ]);
        }

        if (!is_string($prioridade) || trim($prioridade) === '') {
            sendJsonResponse(400, [
                'erro' => 'Prioridade é obrigatória.',
            ]);
        }

        if (!is_string($status) || trim($status) === '') {
            sendJsonResponse(400, [
                'erro' => 'Status é obrigatório.',
            ]);
        }

        if ($observacoes !== null && !is_string($observacoes)) {
            sendJsonResponse(400, [
                'erro' => 'Observações inválidas.',
            ]);
        }

        if (
            $responsavelNome !== null
            && (!is_string($responsavelNome) || mb_strlen(trim($responsavelNome)) > 150)
        ) {
            sendJsonResponse(400, [
                'erro' => 'Responsável inválido.',
            ]);
        }

        $responsavelNome = is_string($responsavelNome) && trim($responsavelNome) !== ''
            ? trim($responsavelNome)
            : null;

        if (!$tarefaController->isValidPriority($prioridade)) {
            sendJsonResponse(400, [
                'erro' => 'Prioridade inválida.',
            ]);
        }

        if (!$tarefaController->isValidStatus($status)) {
            sendJsonResponse(400, [
                'erro' => 'Status inválido.',
            ]);
        }

        $tarefa = $tarefaController->update(
            $id,
            $eventoId,
            $usuarioResponsavelId,
            $etapaId,
            $categoriaId,
            $titulo,
            $descricao,
            $prazo,
            $prioridade,
            $status,
            $observacoes,
            $responsavelNome
        );

        if ($tarefa === null) {
            sendJsonResponse(400, [
                'erro' => 'Não foi possível atualizar a tarefa.',
            ]);
        }

        sendJsonResponse(200, [
            'tarefa' => $tarefa,
        ]);
    }

    if ($method === 'DELETE') {
        requireRole($authorizationFactory, 'GESTOR');

        $tarefa = $tarefaController->show($id);

        if (
            $tarefa === null
            || (int) $tarefa['evento_id'] !== $eventoId
        ) {
            sendJsonResponse(404, [
                'erro' => 'Tarefa não encontrada.',
            ]);
        }

        $deleted = $tarefaController->delete(
            $id,
            $eventoId
        );

        if (!$deleted) {
            sendJsonResponse(400, [
                'erro' => 'Não foi possível excluir a tarefa.',
            ]);
        }

        sendJsonResponse(200, [
            'mensagem' => 'Tarefa excluída com sucesso.',
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}
