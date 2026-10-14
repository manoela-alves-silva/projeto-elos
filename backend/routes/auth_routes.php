<?php

declare(strict_types=1);

use Elos\Controllers\UsuarioController;
use Elos\Services\AuthorizationService;
use Elos\Services\GoogleLoginService;
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
 * cabeçalho só é aceito quando vem da própria máquina ou quando a API
 * exige a chave ELOS_API_CHAVE (conferida em public/index.php).
 */
function enderecoDoCliente(): string
{
    $remoto = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $repassado = (string) ($_SERVER['HTTP_X_ELOS_CLIENTE_IP'] ?? '');
    $origemConfiavel = in_array($remoto, ['127.0.0.1', '::1'], true)
        || trim((string) getenv('ELOS_API_CHAVE')) !== '';

    if (
        $origemConfiavel
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
        'codigo_recuperacao' => $usuario['codigo_recuperacao'],
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

/**
 * A pessoa logada troca a própria senha (PUT, com a senha atual).
 * Errar a senha atual conta no mesmo limite de tentativas do login.
 *
 * @param callable(): UsuarioController $controllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleUserPasswordChangeRequest(
    string $method,
    callable $controllerFactory,
    callable $authorizationFactory
): never {
    if (!in_array($method, ['PUT', 'PATCH'], true)) {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    requireRole($authorizationFactory, 'COLABORADOR');

    $payload = readJsonPayload();
    $senhaAtual = $payload['senha_atual'] ?? null;
    $novaSenha = $payload['nova_senha'] ?? null;

    if (!is_string($senhaAtual) || $senhaAtual === '') {
        sendJsonResponse(400, ['erro' => 'Informe a senha atual.']);
    }

    if (!is_string($novaSenha) || strlen($novaSenha) < 8) {
        sendJsonResponse(400, ['erro' => 'A nova senha deve ter pelo menos 8 caracteres.']);
    }

    $email = (string) ($authorizationFactory()->currentUser()['email'] ?? '');
    $ip = enderecoDoCliente();
    $limite = limiteSemBloqueio($email, $ip);

    try {
        $controllerFactory()->trocarSenha($senhaAtual, $novaSenha);
    } catch (DomainException $e) {
        if ($e->getCode() === 400) {
            $limite->registrarFalha($email, $ip);
        }

        sendJsonResponse($e->getCode() ?: 400, ['erro' => $e->getMessage()]);
    }

    $limite->limpar($email);

    sendJsonResponse(200, ['mensagem' => 'Senha alterada.']);
}

/**
 * Gera uma senha provisória para um usuário (POST). Só gestores.
 *
 * @param callable(): UsuarioController $controllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleUserPasswordResetRequest(
    string $method,
    int $id,
    callable $controllerFactory,
    callable $authorizationFactory
): never {
    if ($method !== 'POST') {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    requireRole($authorizationFactory, 'GESTOR');

    try {
        $senha = $controllerFactory()->redefinirSenha($id);
    } catch (DomainException $e) {
        sendJsonResponse($e->getCode() ?: 400, ['erro' => $e->getMessage()]);
    }

    sendJsonResponse(200, ['senha_provisoria' => $senha]);
}

/**
 * Limite de tentativas compartilhado por todas as rotas que conferem
 * senha ou código. Para a requisição (429) se o e-mail/endereço estiver
 * bloqueado.
 */
function limiteSemBloqueio(string $email, string $ip): LimiteLoginService
{
    $limite = new LimiteLoginService(dirname(__DIR__) . '/storage/limite_login');
    $espera = $limite->segundosBloqueado($email, $ip);

    if ($espera > 0) {
        header('Retry-After: ' . $espera);
        sendJsonResponse(429, [
            'erro' => 'Muitas tentativas. Tente de novo em '
                . (int) ceil($espera / 60) . ' min.',
        ]);
    }

    return $limite;
}

/**
 * A pessoa logada gera um código de recuperação novo (POST, com a
 * senha atual).
 *
 * @param callable(): UsuarioController $controllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleRecoveryCodeRequest(
    string $method,
    callable $controllerFactory,
    callable $authorizationFactory
): never {
    if ($method !== 'POST') {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    requireRole($authorizationFactory, 'COLABORADOR');

    $senhaAtual = readJsonPayload()['senha_atual'] ?? null;

    if (!is_string($senhaAtual) || $senhaAtual === '') {
        sendJsonResponse(400, ['erro' => 'Informe a senha atual.']);
    }

    $email = (string) ($authorizationFactory()->currentUser()['email'] ?? '');
    $ip = enderecoDoCliente();
    $limite = limiteSemBloqueio($email, $ip);

    try {
        $codigo = $controllerFactory()->gerarCodigoRecuperacao($senhaAtual);
    } catch (DomainException $e) {
        if ($e->getCode() === 400) {
            $limite->registrarFalha($email, $ip);
        }

        sendJsonResponse($e->getCode() ?: 400, ['erro' => $e->getMessage()]);
    }

    $limite->limpar($email);

    sendJsonResponse(200, ['codigo_recuperacao' => $codigo]);
}

/**
 * "Esqueceu a senha?": cria uma senha nova com e-mail + código de
 * recuperação (POST, sem login). Devolve o próximo código.
 *
 * @param callable(): UsuarioController $controllerFactory
 */
function handlePasswordRecoveryRequest(
    string $method,
    callable $controllerFactory
): never {
    if ($method !== 'POST') {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    $payload = readJsonPayload();
    $email = $payload['email'] ?? null;
    $codigo = $payload['codigo'] ?? null;
    $novaSenha = $payload['nova_senha'] ?? null;

    if (
        !is_string($email) || trim($email) === ''
        || !is_string($codigo) || trim($codigo) === ''
    ) {
        sendJsonResponse(400, ['erro' => 'Informe o e-mail e o código de recuperação.']);
    }

    if (!is_string($novaSenha) || strlen($novaSenha) < 8) {
        sendJsonResponse(400, ['erro' => 'A nova senha deve ter pelo menos 8 caracteres.']);
    }

    $email = trim($email);
    $ip = enderecoDoCliente();
    $limite = limiteSemBloqueio($email, $ip);

    try {
        $novoCodigo = $controllerFactory()->recuperarSenha($email, $codigo, $novaSenha);
    } catch (DomainException $e) {
        $limite->registrarFalha($email, $ip);
        sendJsonResponse($e->getCode() ?: 400, ['erro' => $e->getMessage()]);
    }

    $limite->limpar($email);

    sendJsonResponse(200, ['codigo_recuperacao' => $novoCodigo]);
}

/**
 * "Entrar com Google", passo 1 (POST): devolve o endereço da tela do
 * Google. O frontend informa para onde o Google deve devolver a pessoa.
 *
 * @param callable(): GoogleLoginService $googleFactory
 */
function handleGoogleLoginStartRequest(
    string $method,
    callable $googleFactory
): never {
    if ($method !== 'POST') {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    if (!GoogleLoginService::configurado()) {
        sendJsonResponse(503, [
            'erro' => 'A entrada com Google ainda não foi configurada neste servidor.',
        ]);
    }

    $redirectUri = readJsonPayload()['redirect_uri'] ?? null;

    if (!is_string($redirectUri)) {
        sendJsonResponse(400, ['erro' => 'Endereço de retorno inválido.']);
    }

    try {
        $url = $googleFactory()->montarUrl($redirectUri);
    } catch (DomainException $e) {
        sendJsonResponse($e->getCode() ?: 400, ['erro' => $e->getMessage()]);
    }

    sendJsonResponse(200, ['url' => $url]);
}

/**
 * "Entrar com Google", passo 2 (POST): com o código que o Google
 * devolveu, confirma a conta e entra (ou cria uma conta pendente).
 *
 * @param callable(): GoogleLoginService $googleFactory
 * @param callable(): UsuarioController $controllerFactory
 */
function handleGoogleLoginRequest(
    string $method,
    callable $googleFactory,
    callable $controllerFactory
): never {
    if ($method !== 'POST') {
        sendJsonResponse(405, ['erro' => 'Método não permitido.']);
    }

    if (!GoogleLoginService::configurado()) {
        sendJsonResponse(503, [
            'erro' => 'A entrada com Google ainda não foi configurada neste servidor.',
        ]);
    }

    $payload = readJsonPayload();
    $codigo = $payload['codigo'] ?? null;
    $state = $payload['state'] ?? null;

    if (!is_string($codigo) || $codigo === '' || !is_string($state) || $state === '') {
        sendJsonResponse(400, ['erro' => 'Resposta do Google incompleta. Tente de novo.']);
    }

    try {
        $conta = $googleFactory()->confirmar($codigo, $state);
        $resultado = $controllerFactory()->loginGoogle($conta['email'], $conta['nome']);
    } catch (DomainException $e) {
        sendJsonResponse($e->getCode() ?: 400, ['erro' => $e->getMessage()]);
    }

    sendJsonResponse(200, [
        'usuario' => $resultado['pendente'] ? null : publicUserData($resultado['usuario']),
        'pendente' => $resultado['pendente'],
        'nova' => $resultado['nova'],
    ]);
}
