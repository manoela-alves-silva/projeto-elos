<?php

declare(strict_types=1);

use Elos\Controllers\UsuarioController;

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

    $usuario = $controllerFactory()->login(
        trim($email),
        $senha
    );

    if ($usuario === null) {
        sendJsonResponse(401, [
            'erro' => 'Credenciais inválidas.',
        ]);
    }

    sendJsonResponse(200, [
        'usuario' => publicUserData($usuario),
    ]);
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
    ]);
}
