<?php

declare(strict_types=1);

use Elos\Controllers\UsuarioController;
use Elos\Services\AuthorizationService;
use Elos\Services\LimiteLoginService;

/**
 * Processa o login da API.
 *
 * @param callable(): UsuarioController $controllerFactory
 */
function handleLoginRequest(
    string $method,
    callable $controllerFactory
): never {
    if ($method !== 'POST') {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    $payload = readJsonPayload();
    $email = $payload['email'] ?? null;
    $senha = $payload['senha'] ?? null;

    if (
        !is_string($email)
        || trim($email) === ''
        || !is_string($senha)
        || $senha === ''
    ) {
        sendJsonResponse(400, [
            'erro' => 'Email e senha são obrigatórios.',
        ]);
    }

    $limite = new LimiteLoginService(dirname(__DIR__) . '/storage/limite_login');
    $ip = enderecoDoCliente();
    $espera = $limite->segundosBloqueado($email, $ip);

    if ($espera > 0) {
        header('Retry-After: ' . $espera);
        sendJsonResponse(429, [
            'erro' => 'Muitas tentativas de entrar. Tente de novo em '
                . (int) ceil($espera / 60) . ' min.',
        ]);
    }

    try {
        $usuario = $controllerFactory()->login(
            trim($email),
            $senha
        );
    } catch (DomainException $e) {
        sendJsonResponse($e->getCode() ?: 403, ['erro' => $e->getMessage()]);
    }

    if ($usuario === null) {
        $limite->registrarFalha($email, $ip);
        sendJsonResponse(401, [
            'erro' => 'Credenciais inválidas.',
        ]);
    }

    $limite->limpar($email);

    sendJsonResponse(200, [
        'usuario' => publicUserData($usuario),
    ]);
}

/**
 * Endereço de quem está usando o site. As chamadas chegam do servidor do
 * frontend, que repassa o endereço do navegador em X-Elos-Cliente-IP; o
 * cabeçalho só é aceito quando vem da própria máquina.
 */
function enderecoDoCliente(): string
{
    $remoto = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $repassado = (string) ($_SERVER['HTTP_X_ELOS_CLIENTE_IP'] ?? '');

    if (
        in_array($remoto, ['127.0.0.1', '::1'], true)
        && filter_var($repassado, FILTER_VALIDATE_IP) !== false
    ) {
        return $repassado;
    }

    return $remoto;
}

/**
 * Processa o logout da API.
 *
 * @param callable(): UsuarioController $controllerFactory
 */
function handleLogoutRequest(
    string $method,
    callable $controllerFactory
): never {
    if ($method !== 'POST') {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    $controllerFactory()->logout();

    sendJsonResponse(200, [
        'mensagem' => 'Logout realizado com sucesso.',
    ]);
}

/**
 * Lista os usuários ativos (dados públicos apenas), usada para
 * preencher seletores como o de responsável por uma tarefa.
 *
 * @param callable(): UsuarioController $controllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleUserIndexRequest(
    callable $controllerFactory,
    callable $authorizationFactory
): never {
    requireRole($authorizationFactory, 'COLABORADOR');

    $usuarios = $controllerFactory()->index();

    sendJsonResponse(200, [
        'usuarios' => array_map('publicUserData', $usuarios),
    ]);
}

/**
 * Processa o cadastro público de um usuário.
 *
 * @param callable(): UsuarioController $controllerFactory
 */
function handleUserRegistrationRequest(
    string $method,
    callable $controllerFactory
): never {
    if ($method !== 'POST') {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    $payload = readJsonPayload();
    $nome = $payload['nome'] ?? null;
    $email = $payload['email'] ?? null;
    $senha = $payload['senha'] ?? null;

    if (!is_string($nome) || trim($nome) === '') {
        sendJsonResponse(400, [
            'erro' => 'Nome é obrigatório.',
        ]);
    }

    if (
        !is_string($email)
        || trim($email) === ''
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        sendJsonResponse(400, [
            'erro' => 'Email inválido.',
        ]);
    }

    if (!is_string($senha)) {
        sendJsonResponse(400, [
            'erro' => 'Senha é obrigatória.',
        ]);
    }

    if (strlen($senha) < 8) {
        sendJsonResponse(400, [
            'erro' => 'A senha deve ter pelo menos 8 caracteres.',
        ]);
    }

    try {
        $usuario = $controllerFactory()->register(
            trim($nome),
            trim($email),
            $senha
        );
    } catch (Throwable) {
        sendJsonResponse(500, [
            'erro' => 'Não foi possível concluir o cadastro.',
        ]);
    }

    if ($usuario === null) {
        sendJsonResponse(409, [
            'erro' => 'Email já cadastrado.',
        ]);
    }

    sendJsonResponse(201, [
        'usuario' => publicUserData($usuario),
        'pendente' => (int) $usuario['ativo'] !== 1,
    ]);
}

/**
 * Lista os cadastros que aguardam aprovação. Só gestores.
 *
 * @param callable(): UsuarioController $controllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleUserPendingIndexRequest(
    string $method,
    callable $controllerFactory,
    callable $authorizationFactory
): never {
    if ($method !== 'GET') {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    requireRole($authorizationFactory, 'GESTOR');

    sendJsonResponse(200, [
        'usuarios' => array_map('publicUserData', $controllerFactory()->pendentes()),
    ]);
}

/**
 * Aprova (POST) ou recusa (DELETE) um cadastro pendente. Só gestores.
 *
 * @param callable(): UsuarioController $controllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleUserApprovalRequest(
    string $method,
    int $id,
    callable $controllerFactory,
    callable $authorizationFactory
): never {
    if (!in_array($method, ['POST', 'DELETE'], true)) {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    requireRole($authorizationFactory, 'GESTOR');

    try {
        if ($method === 'POST') {
            $controllerFactory()->aprovar($id);
            sendJsonResponse(200, ['mensagem' => 'Cadastro aprovado.']);
        }

        $controllerFactory()->recusar($id);
        sendJsonResponse(200, ['mensagem' => 'Cadastro recusado.']);
    } catch (DomainException $e) {
        sendJsonResponse($e->getCode() ?: 400, ['erro' => $e->getMessage()]);
    }
}

/**
 * Troca o perfil de um usuário (COLABORADOR <-> GESTOR). Só gestores.
 *
 * @param callable(): UsuarioController $controllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleUserProfileRequest(
    string $method,
    int $id,
    callable $controllerFactory,
    callable $authorizationFactory
): never {
    if (!in_array($method, ['PUT', 'PATCH'], true)) {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    requireRole($authorizationFactory, 'GESTOR');

    $payload = readJsonPayload();
    $perfil = $payload['perfil'] ?? null;

    if (!is_string($perfil)) {
        sendJsonResponse(400, ['erro' => 'Perfil é obrigatório.']);
    }

    try {
        $usuario = $controllerFactory()->alterarPerfil($id, $perfil);
    } catch (DomainException $e) {
        sendJsonResponse($e->getCode() ?: 400, ['erro' => $e->getMessage()]);
    }

    sendJsonResponse(200, [
        'usuario' => publicUserData($usuario),
    ]);
}
