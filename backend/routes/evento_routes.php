<?php

declare(strict_types=1);

use Elos\Controllers\EventoController;
use Elos\Services\AuthorizationService;

/**
 * Processa as requisições de eventos.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * POST: somente gestor ou administrador pode cadastrar.
 *
 * @param callable(): EventoController $eventoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleEventoRequest(
    string $method,
    callable $eventoControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $eventos = $eventoControllerFactory()->index();

        sendJsonResponse(200, [
            'eventos' => $eventos,
        ]);
    }

    if ($method === 'POST') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $tipoEventoId = $payload['tipo_evento_id'] ?? null;
        $responsavelId = $payload['responsavel_id'] ?? null;
        $localId = $payload['local_id'] ?? null;
        $titulo = $payload['titulo'] ?? null;
        $descricao = $payload['descricao'] ?? null;
        $prioridade = $payload['prioridade'] ?? null;
        $status = $payload['status'] ?? null;
        $observacoes = $payload['observacoes'] ?? null;

        if (!is_int($tipoEventoId) || $tipoEventoId <= 0) {
            sendJsonResponse(400, [
                'erro' => 'Tipo de evento é obrigatório.',
            ]);
        }

        if (!is_int($responsavelId) || $responsavelId <= 0) {
            sendJsonResponse(400, [
                'erro' => 'Responsável é obrigatório.',
            ]);
        }

        if (!is_int($localId) || $localId <= 0) {
            sendJsonResponse(400, [
                'erro' => 'Local é obrigatório.',
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

        $eventoController = $eventoControllerFactory();

        if (!$eventoController->isValidPriority($prioridade)) {
            sendJsonResponse(400, [
                'erro' => 'Prioridade inválida.',
            ]);
        }

        if (!$eventoController->isValidStatus($status)) {
            sendJsonResponse(400, [
                'erro' => 'Status inválido.',
            ]);
        }

        $evento = $eventoController->create(
            $tipoEventoId,
            $responsavelId,
            $localId,
            $titulo,
            $descricao,
            $prioridade,
            $status,
            $observacoes
        );

        if ($evento === null) {
            sendJsonResponse(400, [
                'erro' => 'Não foi possível cadastrar o evento.',
            ]);
        }

        sendJsonResponse(201, [
            'evento' => $evento,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * Busca ou edita um evento específico pelo ID.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * PUT: somente gestor ou administrador pode editar.
 *
 * @param callable(): EventoController $eventoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleEventoByIdRequest(
    string $method,
    int $id,
    callable $eventoControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($method === 'GET') {
        $evento = $eventoControllerFactory()->show($id);

        if ($evento === null) {
            sendJsonResponse(404, [
                'erro' => 'Evento não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'evento' => $evento,
        ]);
    }

    if ($method === 'PUT') {
        requireRole($authorizationFactory, 'GESTOR');

        $payload = readJsonPayload();

        $eventoAtual = $eventoControllerFactory()->show($id);

        if ($eventoAtual === null) {
            sendJsonResponse(404, [
                'erro' => 'Evento não encontrado.',
            ]);
        }

        $tipoEventoId = $payload['tipo_evento_id']
            ?? (int) $eventoAtual['tipo_evento_id'];

        $responsavelId = $payload['responsavel_id']
            ?? (int) $eventoAtual['responsavel_id'];

        $localId = $payload['local_id']
            ?? (int) $eventoAtual['local_id'];

        $titulo = $payload['titulo']
            ?? (string) $eventoAtual['titulo'];

        $descricao = array_key_exists('descricao', $payload)
            ? $payload['descricao']
            : $eventoAtual['descricao'];

        $prioridade = $payload['prioridade']
            ?? (string) $eventoAtual['prioridade'];

        $status = $payload['status']
            ?? (string) $eventoAtual['status'];

        $observacoes = array_key_exists('observacoes', $payload)
            ? $payload['observacoes']
            : $eventoAtual['observacoes'];

        if (!is_int($tipoEventoId) || $tipoEventoId <= 0) {
            sendJsonResponse(400, [
                'erro' => 'Tipo de evento inválido.',
            ]);
        }

        if (!is_int($responsavelId) || $responsavelId <= 0) {
            sendJsonResponse(400, [
                'erro' => 'Responsável inválido.',
            ]);
        }

        if (!is_int($localId) || $localId <= 0) {
            sendJsonResponse(400, [
                'erro' => 'Local inválido.',
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

        $eventoController = $eventoControllerFactory();

        if (!$eventoController->isValidPriority($prioridade)) {
            sendJsonResponse(400, [
                'erro' => 'Prioridade inválida.',
            ]);
        }

        if (!$eventoController->isValidStatus($status)) {
            sendJsonResponse(400, [
                'erro' => 'Status inválido.',
            ]);
        }

        $evento = $eventoController->update(
            $id,
            $tipoEventoId,
            $responsavelId,
            $localId,
            $titulo,
            $descricao,
            $prioridade,
            $status,
            $observacoes
        );

        if ($evento === null) {
            sendJsonResponse(400, [
                'erro' => 'Não foi possível atualizar o evento.',
            ]);
        }

        sendJsonResponse(200, [
            'evento' => $evento,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * Exposições que já ocupam um local num período:
 * GET /api/eventos/conflitos?local_id=1&inicio=2026-10-01&fim=2026-10-30[&exceto=5]
 *
 * @param callable(): EventoController $eventoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleEventoConflitosRequest(
    string $method,
    callable $eventoControllerFactory,
    callable $authorizationFactory
): never {
    if ($method !== 'GET') {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    requireRole($authorizationFactory, 'COLABORADOR');

    $localId = filter_var($_GET['local_id'] ?? null, FILTER_VALIDATE_INT);
    $inicio = (string) ($_GET['inicio'] ?? '');
    $fim = (string) ($_GET['fim'] ?? '');
    $exceto = filter_var($_GET['exceto'] ?? null, FILTER_VALIDATE_INT);

    $dataValida = static function (string $data): bool {
        $lida = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

        return $lida !== false && $lida->format('Y-m-d') === $data;
    };

    if ($localId === false || $localId < 1 || !$dataValida($inicio) || !$dataValida($fim)) {
        sendJsonResponse(400, ['erro' => 'Informe local_id, inicio e fim (AAAA-MM-DD).']);
    }

    sendJsonResponse(200, [
        'conflitos' => $eventoControllerFactory()->conflitos(
            $localId,
            $inicio,
            $fim,
            $exceto === false ? null : $exceto
        ),
    ]);
}
