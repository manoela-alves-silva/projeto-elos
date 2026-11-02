<?php

declare(strict_types=1);

use Elos\Controllers\PainelController;
use Elos\Repositories\EventoRepository;
use Elos\Repositories\FichaRepository;
use Elos\Repositories\PainelRepository;
use Elos\Services\AuthorizationService;
use Elos\Services\HistoricoService;

/*
 * Rotas da remodelação: identidade visual (capa e cor), ficha dos
 * eventos de projeto e painel ao vivo.
 *
 *   GET  /api/eventos/endereco/{slug}   evento pelo endereço (atual ou antigo)
 *   GET  /api/fichas                    fichas de todos os eventos
 *   GET  /api/eventos/{id}/ficha        ficha de um evento (404 se não tem)
 *   PUT  /api/eventos/{id}/ficha        grava a ficha (GESTOR)
 *   PUT  /api/eventos/{id}/identidade   capa e cor (GESTOR)
 *   GET  /api/painel                    dados do painel (logado, ou o
 *                                       monitor com X-Elos-Painel: código)
 *   GET|POST|DELETE /api/painel/codigo  link do monitor (GESTOR)
 */

/**
 * Atende a rota se for uma destas; senão devolve false e segue.
 *
 * @param callable(): PDO $pdoFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleProjetoRequest(string $method, string $path, callable $pdoFactory, callable $authorizationFactory): bool
{
    // Evento pelo endereço, atual ou antigo: GET /api/eventos/endereco/<slug>.
    if (preg_match('#^/api/eventos/endereco/([a-z0-9-]{1,220})$#', $path, $matches)) {
        exigirLogin($authorizationFactory);
        exigirMetodo($method, ['GET']);
        $id = (new EventoRepository($pdoFactory()))->idDoEndereco($matches[1]);

        $id === null
            ? sendJsonResponse(404, ['erro' => 'Endereço não encontrado.'])
            : sendJsonResponse(200, ['evento_id' => $id]);
    }

    if ($path === '/api/fichas') {
        exigirLogin($authorizationFactory);
        exigirMetodo($method, ['GET']);

        sendJsonResponse(200, ['fichas' => (new FichaRepository($pdoFactory()))->todas()]);
    }

    if (preg_match('#^/api/eventos/(\d+)/ficha$#', $path, $matches)) {
        exigirLogin($authorizationFactory);
        exigirMetodo($method, ['GET', 'PUT']);
        $eventoId = (int) $matches[1];
        $fichas = new FichaRepository($pdoFactory());

        if ($method === 'GET') {
            $ficha = $fichas->doEvento($eventoId);

            $ficha === null
                ? sendJsonResponse(404, ['erro' => 'Este evento não tem ficha.'])
                : sendJsonResponse(200, ['ficha' => $ficha]);
        }

        requireRole($authorizationFactory, 'GESTOR');
        exigirEvento($pdoFactory, $eventoId);

        $dados = readJsonPayload()['dados'] ?? null;

        if (!is_array($dados)) {
            sendJsonResponse(400, ['erro' => 'Ficha inválida.']);
        }

        // Só valores simples (texto, número, verdadeiro/falso), com tamanho limitado.
        $limpos = [];
        foreach ($dados as $campo => $valor) {
            if (!is_string($campo) || preg_match('/^[a-z_]{1,40}$/', $campo) !== 1) {
                continue;
            }
            if (is_string($valor)) {
                $limpos[$campo] = mb_substr($valor, 0, 4000);
            } elseif (is_int($valor) || is_float($valor) || is_bool($valor) || $valor === null) {
                $limpos[$campo] = $valor;
            }
        }

        $fichas->salvar($eventoId, $limpos);
        HistoricoService::registrar($eventoId, 'Ficha editada', '');

        sendJsonResponse(200, ['ficha' => $fichas->doEvento($eventoId)]);
    }

    if (preg_match('#^/api/eventos/(\d+)/identidade$#', $path, $matches)) {
        exigirMetodo($method, ['PUT']);
        requireRole($authorizationFactory, 'GESTOR');
        $eventoId = (int) $matches[1];
        $payload = readJsonPayload();

        $capa = $payload['imagem_capa'] ?? null;
        $cor = $payload['cor'] ?? null;

        if ($capa !== null && (!is_string($capa) || preg_match('/^[a-f0-9]{32}\.(jpg|webp)$/', $capa) !== 1)) {
            sendJsonResponse(400, ['erro' => 'Imagem de capa inválida.']);
        }

        if ($cor !== null && (!is_string($cor) || preg_match('/^#[0-9a-fA-F]{6}$/', $cor) !== 1)) {
            sendJsonResponse(400, ['erro' => 'Cor inválida.']);
        }

        $eventos = new EventoRepository($pdoFactory());
        $anterior = $eventos->atualizarIdentidade($eventoId, $capa, $cor !== null ? strtolower($cor) : null);

        if ($anterior === false) {
            sendJsonResponse(404, ['erro' => 'Evento não encontrado.']);
        }

        HistoricoService::registrar($eventoId, 'Capa e cor alteradas', '');

        sendJsonResponse(200, ['evento' => $eventos->findById($eventoId), 'capa_anterior' => $anterior]);
    }

    if ($path === '/api/painel') {
        exigirMetodo($method, ['GET']);
        $painel = new PainelController(new PainelRepository($pdoFactory()));
        $codigo = (string) ($_SERVER['HTTP_X_ELOS_PAINEL'] ?? '');

        if ($codigo !== '') {
            if (!$painel->codigoValido($codigo)) {
                sendJsonResponse(403, ['erro' => 'Link do monitor inválido ou desligado.']);
            }
        } else {
            exigirLogin($authorizationFactory);
        }

        sendJsonResponse(200, $painel->dados(date('Y-m-d')));
    }

    if ($path === '/api/painel/codigo') {
        exigirMetodo($method, ['GET', 'POST', 'DELETE']);
        requireRole($authorizationFactory, 'GESTOR');
        $painel = new PainelController(new PainelRepository($pdoFactory()));

        if ($method === 'GET') {
            $criadoEm = $painel->dataDoCodigo();
            sendJsonResponse(200, ['ativo' => $criadoEm !== null, 'criado_em' => $criadoEm]);
        }

        if ($method === 'POST') {
            $usuario = $authorizationFactory()->currentUser();
            sendJsonResponse(201, ['codigo' => $painel->novoCodigo(isset($usuario['id']) ? (int) $usuario['id'] : null)]);
        }

        $painel->desativarCodigo();
        sendJsonResponse(200, ['ok' => true]);
    }

    return false;
}

/** @param callable(): AuthorizationService $authorizationFactory */
function exigirLogin(callable $authorizationFactory): void
{
    if ($authorizationFactory()->currentUser() === null) {
        sendJsonResponse(401, ['erro' => 'Usuário não autenticado.']);
    }
}

/** @param list<string> $permitidos */
function exigirMetodo(string $method, array $permitidos): void
{
    if (!in_array($method, $permitidos, true)) {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }
}

/** @param callable(): PDO $pdoFactory */
function exigirEvento(callable $pdoFactory, int $eventoId): void
{
    if ((new EventoRepository($pdoFactory()))->findById($eventoId) === null) {
        sendJsonResponse(404, ['erro' => 'Evento não encontrado.']);
    }
}
