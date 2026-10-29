<?php

declare(strict_types=1);

use Elos\Controllers\PainelController;
use Elos\Services\AuthorizationService;
use Elos\Services\HistoricoService;

/**
 * GET /api/painel — o que a produção precisa fazer agora.
 *
 * Pode ver: qualquer pessoa logada, ou o monitor da produção, que
 * manda o código do link secreto no cabeçalho X-Elos-Painel. O
 * código só dá acesso a esta rota (só leitura).
 *
 * @param callable(): PainelController $painelControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handlePainelRequest(
    string $method,
    callable $painelControllerFactory,
    callable $authorizationFactory
): never {
    if ($method !== 'GET') {
        sendJsonResponse(405, [
            'erro' => 'Método não permitido.',
        ]);
    }

    $codigo = (string) ($_SERVER['HTTP_X_ELOS_PAINEL'] ?? '');

    if (
        !$authorizationFactory()->hasRole('COLABORADOR')
        && ($codigo === '' || !$painelControllerFactory()->codigoValido($codigo))
    ) {
        sendJsonResponse(403, [
            'erro' => 'Acesso não autorizado.',
        ]);
    }

    sendJsonResponse(200, $painelControllerFactory()->dados(date('Y-m-d')));
}

/**
 * /api/painel/codigo — link secreto do monitor (só gestores).
 *
 * GET: diz se há um link valendo e desde quando.
 * POST: gera um link novo (o anterior para de funcionar na hora).
 * DELETE: desliga o link (nenhum monitor entra até gerar outro).
 *
 * @param callable(): PainelController $painelControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handlePainelCodigoRequest(
    string $method,
    callable $painelControllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'GESTOR');

    $controller = $painelControllerFactory();

    if ($method === 'GET') {
        $criadoEm = $controller->dataDoCodigo();

        sendJsonResponse(200, [
            'ativo' => $criadoEm !== null,
            'criado_em' => $criadoEm,
        ]);
    }

    if ($method === 'POST') {
        $usuario = $authorizationFactory()->currentUser();
        $codigo = $controller->novoCodigo(isset($usuario['id']) ? (int) $usuario['id'] : null);

        HistoricoService::registrarGeral('Link do painel ao vivo gerado');

        sendJsonResponse(201, [
            'codigo' => $codigo,
        ]);
    }

    if ($method === 'DELETE') {
        $controller->desativarCodigo();

        HistoricoService::registrarGeral('Link do painel ao vivo desligado');

        sendJsonResponse(200, [
            'ativo' => false,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}
