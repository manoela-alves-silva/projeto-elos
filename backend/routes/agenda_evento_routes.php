<?php

declare(strict_types=1);

use Elos\Controllers\AgendaEventoController;
use Elos\Services\AuthorizationService;
use Elos\Services\HistoricoService;

/**
 * Processa as requisições da agenda de um evento.
 *
 * GET: qualquer usuário autenticado pode consultar.
 * POST: somente gestor ou administrador pode cadastrar.
 * PUT: somente gestor ou administrador pode editar.
 *
 * @param callable(): AgendaEventoController $agendaEventoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleAgendaEventoRequest(
    string $method,
    int $eventoId,
    callable $agendaEventoControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    if ($eventoId <= 0) {
        sendJsonResponse(400, [
            'erro' => 'Evento inválido.',
        ]);
    }

    if ($method === 'GET') {
        $agenda = $agendaEventoControllerFactory()->show($eventoId);

        if ($agenda === null) {
            sendJsonResponse(404, [
                'erro' => 'Agenda do evento não encontrada.',
            ]);
        }

        sendJsonResponse(200, [
            'agenda' => $agenda,
        ]);
    }

    if ($method === 'POST') {
        requireRole($authorizationFactory, 'GESTOR');

        $agendaAtual = $agendaEventoControllerFactory()->show($eventoId);

        if ($agendaAtual !== null) {
            sendJsonResponse(409, [
                'erro' => 'O evento já possui uma agenda.',
            ]);
        }

        $payload = readJsonPayload();

        $montagemInicio = $payload['montagem_inicio'] ?? null;
        $montagemFim = $payload['montagem_fim'] ?? null;
        $abertura = $payload['abertura'] ?? null;
        $horario = $payload['horario'] ?? null;
        $permanenciaInicio = $payload['permanencia_inicio'] ?? null;
        $permanenciaFim = $payload['permanencia_fim'] ?? null;
        $desmontagemInicio = $payload['desmontagem_inicio'] ?? null;
        $desmontagemFim = $payload['desmontagem_fim'] ?? null;
        $tipoHorario = $payload['tipo_horario'] ?? null;
        $observacoes = $payload['observacoes'] ?? null;

        $campos = [
            'montagem_inicio' => $montagemInicio,
            'montagem_fim' => $montagemFim,
            'abertura' => $abertura,
            'horario' => $horario,
            'permanencia_inicio' => $permanenciaInicio,
            'permanencia_fim' => $permanenciaFim,
            'desmontagem_inicio' => $desmontagemInicio,
            'desmontagem_fim' => $desmontagemFim,
            'tipo_horario' => $tipoHorario,
            'observacoes' => $observacoes,
        ];

        foreach ($campos as $nome => $valor) {
            if ($valor !== null && !is_string($valor)) {
                sendJsonResponse(400, [
                    'erro' => "O campo {$nome} é inválido.",
                ]);
            }
        }

        $agenda = $agendaEventoControllerFactory()->create(
            $eventoId,
            $montagemInicio,
            $montagemFim,
            $abertura,
            $horario,
            $permanenciaInicio,
            $permanenciaFim,
            $desmontagemInicio,
            $desmontagemFim,
            $tipoHorario,
            $observacoes
        );

        if ($agenda === null) {
            sendJsonResponse(400, [
                'erro' => 'Não foi possível cadastrar a agenda do evento.',
            ]);
        }

        HistoricoService::registrar($eventoId, 'Datas definidas', resumoDasDatas($agenda));

        sendJsonResponse(201, [
            'agenda' => $agenda,
        ]);
    }

    if ($method === 'PUT') {
        requireRole($authorizationFactory, 'GESTOR');

        $agendaAtual = $agendaEventoControllerFactory()->show($eventoId);

        if ($agendaAtual === null) {
            sendJsonResponse(404, [
                'erro' => 'Agenda do evento não encontrada.',
            ]);
        }

        $payload = readJsonPayload();

        // Campo enviado como null apaga a data; campo ausente mantém.
        $montagemInicio = array_key_exists('montagem_inicio', $payload)
            ? $payload['montagem_inicio']
            : $agendaAtual['montagem_inicio'];
        $montagemFim = array_key_exists('montagem_fim', $payload)
            ? $payload['montagem_fim']
            : $agendaAtual['montagem_fim'];
        $abertura = array_key_exists('abertura', $payload)
            ? $payload['abertura']
            : $agendaAtual['abertura'];
        $horario = array_key_exists('horario', $payload)
            ? $payload['horario']
            : $agendaAtual['horario'];
        $permanenciaInicio = array_key_exists('permanencia_inicio', $payload)
            ? $payload['permanencia_inicio']
            : $agendaAtual['permanencia_inicio'];
        $permanenciaFim = array_key_exists('permanencia_fim', $payload)
            ? $payload['permanencia_fim']
            : $agendaAtual['permanencia_fim'];
        $desmontagemInicio = array_key_exists('desmontagem_inicio', $payload)
            ? $payload['desmontagem_inicio']
            : $agendaAtual['desmontagem_inicio'];
        $desmontagemFim = array_key_exists('desmontagem_fim', $payload)
            ? $payload['desmontagem_fim']
            : $agendaAtual['desmontagem_fim'];
        $tipoHorario = array_key_exists('tipo_horario', $payload)
            ? $payload['tipo_horario']
            : $agendaAtual['tipo_horario'];
        $observacoes = array_key_exists('observacoes', $payload)
            ? $payload['observacoes']
            : $agendaAtual['observacoes'];

        $campos = [
            'montagem_inicio' => $montagemInicio,
            'montagem_fim' => $montagemFim,
            'abertura' => $abertura,
            'horario' => $horario,
            'permanencia_inicio' => $permanenciaInicio,
            'permanencia_fim' => $permanenciaFim,
            'desmontagem_inicio' => $desmontagemInicio,
            'desmontagem_fim' => $desmontagemFim,
            'tipo_horario' => $tipoHorario,
            'observacoes' => $observacoes,
        ];

        foreach ($campos as $nome => $valor) {
            if ($valor !== null && !is_string($valor)) {
                sendJsonResponse(400, [
                    'erro' => "O campo {$nome} é inválido.",
                ]);
            }
        }

        $agenda = $agendaEventoControllerFactory()->update(
            $eventoId,
            $montagemInicio,
            $montagemFim,
            $abertura,
            $horario,
            $permanenciaInicio,
            $permanenciaFim,
            $desmontagemInicio,
            $desmontagemFim,
            $tipoHorario,
            $observacoes
        );

        if ($agenda === null) {
            sendJsonResponse(400, [
                'erro' => 'Não foi possível atualizar a agenda do evento.',
            ]);
        }

        HistoricoService::registrar($eventoId, 'Datas alteradas', resumoDasDatas($agenda));

        sendJsonResponse(200, [
            'agenda' => $agenda,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * "Montagem 01/10/2026 · abertura 05/10/2026 · desmontagem 30/10/2026"
 * — só com as datas preenchidas, para o histórico.
 */
function resumoDasDatas(array $agenda): ?string
{
    $partes = [];

    foreach ([
        'montagem_inicio' => 'montagem',
        'abertura' => 'abertura',
        'permanencia_fim' => 'em cartaz até',
        'desmontagem_fim' => 'desmontagem',
    ] as $campo => $rotulo) {
        if (!empty($agenda[$campo])) {
            $partes[] = $rotulo . ' ' . date('d/m/Y', (int) strtotime((string) $agenda[$campo]));
        }
    }

    return $partes === [] ? 'sem datas' : ucfirst(implode(' · ', $partes));
}
