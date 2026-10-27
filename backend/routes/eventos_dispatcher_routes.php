<?php

declare(strict_types=1);

use Elos\Controllers\AgendaEventoController;
use Elos\Controllers\AnexoController;
use Elos\Controllers\EventoController;
use Elos\Controllers\EventoCursoController;
use Elos\Controllers\HistoricoController;
use Elos\Controllers\TarefaController;
use Elos\Services\AuthorizationService;

/**
 * Encaminha as rotas relacionadas a eventos e seus recursos.
 *
 * @param callable(): EventoController $eventoControllerFactory
 * @param callable(): EventoCursoController $eventoCursoControllerFactory
 * @param callable(): AgendaEventoController $agendaEventoControllerFactory
 * @param callable(): TarefaController $tarefaControllerFactory
 * @param callable(): AnexoController $anexoControllerFactory
 * @param callable(): HistoricoController $historicoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleEventosDispatcherRequest(
    string $method,
    string $path,
    callable $eventoControllerFactory,
    callable $eventoCursoControllerFactory,
    callable $agendaEventoControllerFactory,
    callable $tarefaControllerFactory,
    callable $anexoControllerFactory,
    callable $historicoControllerFactory,
    callable $authorizationFactory
): bool {
    if ($path === '/api/eventos') {
        handleEventoRequest(
            $method,
            $eventoControllerFactory,
            $authorizationFactory
        );
    }

    if ($path === '/api/eventos/conflitos') {
        handleEventoConflitosRequest(
            $method,
            $eventoControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)$#', $path, $matches)) {
        handleEventoByIdRequest(
            $method,
            (int) $matches[1],
            $eventoControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)/identidade$#', $path, $matches)) {
        handleEventoIdentidadeRequest(
            $method,
            (int) $matches[1],
            $eventoControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)/cursos$#', $path, $matches)) {
        handleEventoCursoRequest(
            $method,
            (int) $matches[1],
            $eventoCursoControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)/cursos/(\d+)$#', $path, $matches)) {
        handleEventoCursoByCursoRequest(
            $method,
            (int) $matches[1],
            (int) $matches[2],
            $eventoCursoControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)/agenda$#', $path, $matches)) {
        handleAgendaEventoRequest(
            $method,
            (int) $matches[1],
            $agendaEventoControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)/tarefas$#', $path, $matches)) {
        handleTarefaRequest(
            $method,
            (int) $matches[1],
            $tarefaControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)/tarefas/(\d+)$#', $path, $matches)) {
        handleTarefaByIdRequest(
            $method,
            (int) $matches[1],
            (int) $matches[2],
            $tarefaControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)/tarefas/(\d+)/status$#', $path, $matches)) {
        handleTarefaStatusRequest(
            $method,
            (int) $matches[1],
            (int) $matches[2],
            $tarefaControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)/anexos$#', $path, $matches)) {
        handleAnexoRequest(
            $method,
            (int) $matches[1],
            $anexoControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)/anexos/(\d+)$#', $path, $matches)) {
        handleAnexoByIdRequest(
            $method,
            (int) $matches[1],
            (int) $matches[2],
            $anexoControllerFactory,
            $authorizationFactory
        );
    }

    if (preg_match('#^/api/eventos/(\d+)/historico$#', $path, $matches)) {
        if ($method === 'POST') {
            handleHistoricoCreateRequest(
                $method,
                (int) $matches[1],
                $historicoControllerFactory,
                $authorizationFactory
            );
        }

        handleHistoricoRequest(
            $method,
            (int) $matches[1],
            $historicoControllerFactory,
            $authorizationFactory
        );
    }

    return false;
}