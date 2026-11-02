<?php

declare(strict_types=1);

/*
 * "Entrar com Google", passo 2: o Google devolve a pessoa aqui com um
 * código. O backend confere o código com o Google e diz se a pessoa
 * entra, se a conta ainda espera aprovação ou se foi criada agora.
 */

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;

require_once __DIR__ . '/../src/Api.php';
require_once __DIR__ . '/../src/elos.php';

\Elos\Frontend\iniciarSessao();

$voltarAoLogin = static function (string $tipo, string $texto): never {
    $_SESSION['aviso_login'] = ['tipo' => $tipo, 'texto' => $texto];
    header('Location: ' . \Elos\Frontend\raizDoSite() . '/entrar');
    exit();
};

// A pessoa desistiu na tela do Google (ou o Google recusou).
if (isset($_GET['error'])) {
    $voltarAoLogin('erro', 'A entrada com Google foi cancelada.');
}

$codigo = is_string($_GET['code'] ?? null) ? $_GET['code'] : '';
$state = is_string($_GET['state'] ?? null) ? $_GET['state'] : '';

if ($codigo === '' || $state === '') {
    $voltarAoLogin('erro', 'Resposta do Google incompleta. Tente de novo.');
}

try {
    $dados = Api::post('/api/login/google', ['codigo' => $codigo, 'state' => $state]);
} catch (ApiException $e) {
    $voltarAoLogin('erro', $e->statusCode === 0 ? 'Não foi possível falar com o servidor do ELOS.' : $e->getMessage());
}

if (!empty($dados['pendente'])) {
    $voltarAoLogin('info', !empty($dados['nova'])
        ? 'Conta criada com o seu Google! Agora é só esperar um gestor aprovar o seu acesso.'
        : 'Sua conta ainda aguarda a aprovação de um gestor.');
}

$usuario = $dados['usuario'] ?? null;

if (!is_array($usuario) || empty($usuario['id'])) {
    $voltarAoLogin('erro', 'Não foi possível entrar com Google. Tente de novo.');
}

// Sessão nova a cada login, como na entrada com senha.
session_regenerate_id(true);

$_SESSION['usuario'] = [
    'id' => $usuario['id'] ?? null,
    'nome' => $usuario['nome'] ?? null,
    'email' => $usuario['email'] ?? null,
    'perfil' => $usuario['perfil'] ?? null,
];
\Elos\Frontend\lembrarLogin(!empty($_SESSION['_google_lembrar']));
unset($_SESSION['_google_lembrar']);

header('Location: ' . \Elos\Frontend\raizDoSite() . '/');
exit();
