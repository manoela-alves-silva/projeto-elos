<?php

declare(strict_types=1);

/*
 * "Entrar com Google", passo 1: pede ao backend o endereço da tela do
 * Google e manda a pessoa para lá. O Google a devolve em
 * google_retorno.php.
 */

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;

require_once __DIR__ . '/../src/Api.php';

\Elos\Frontend\iniciarSessao();

// Guardado até a volta do Google ("Lembrar de mim" marcado no login).
$_SESSION['_google_lembrar'] = ($_GET['lembrar'] ?? '') === '1';

$https = \Elos\Frontend\emHttps();
$pasta = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/pages/google_entrar.php')), '/');
$retorno = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . $pasta . '/google_retorno.php';

try {
    $url = (string) (Api::post('/api/login/google/inicio', ['redirect_uri' => $retorno])['url'] ?? '');

    if (!str_starts_with($url, 'https://accounts.google.com/')) {
        throw new ApiException('Não foi possível abrir a entrada com Google.', 0);
    }

    header('Location: ' . $url);
    exit();
} catch (ApiException $e) {
    $_SESSION['aviso_login'] = [
        'tipo' => 'erro',
        'texto' => $e->statusCode === 0 ? 'Não foi possível falar com o servidor do ELOS.' : $e->getMessage(),
    ];

    header('Location: entrar');
    exit();
}
