<?php

declare(strict_types=1);

/*
 * ROTEADOR — endereços limpos do ELOS.
 *
 * Toda visita passa por aqui (pelo .htaccess no Apache, ou como roteador
 * do "php -S" no computador). O endereço que aparece no navegador não
 * mostra pastas nem ".php": /exposicao?id=5 abre pages/evento.php.
 *
 * Endereços antigos (/pages/evento.php?id=5) continuam funcionando:
 * são redirecionados para o endereço novo, então favoritos e links
 * salvos não quebram.
 */

// Endereço limpo => arquivo de verdade.
const ROTAS = [
    '' => 'index.php',
    'entrar' => 'pages/login.php',
    'sair' => 'pages/logout.php',
    'criar-conta' => 'pages/cadastro.php',
    'recuperar-senha' => 'pages/recuperar_senha.php',
    'entrar-com-google' => 'pages/google_entrar.php',
    'exposicoes' => 'pages/eventos.php',
    'nova-exposicao' => 'pages/evento_novo.php',
    'exposicao' => 'pages/evento.php',
    'apagar-exposicao' => 'pages/apagar_exposicao.php',
    'calendario' => 'pages/agenda.php',
    'relatorio' => 'pages/relatorio.php',
    'equipe' => 'pages/equipe.php',
    'minha-conta' => 'pages/minha_conta.php',
    'capa' => 'capa.php',
];

// Fica no endereço antigo: é o que está cadastrado no Google.
const SEM_REDIRECIONAR = ['pages/google_retorno.php'];

// Pasta do site no domínio ("" quando o ELOS está na raiz).
$raiz = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/roteador.php'))), '/');
if ($raiz === '.' || PHP_SAPI === 'cli-server') {
    $raiz = ''; // no "php -S" o site está sempre na raiz
}
define('ELOS_RAIZ', $raiz);

$caminho = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
if ($raiz !== '' && str_starts_with($caminho, $raiz)) {
    $caminho = substr($caminho, strlen($raiz));
}
$caminho = trim($caminho, '/');

// No "php -S": arquivos de verdade em assets/ são entregues direto.
if (PHP_SAPI === 'cli-server' && str_starts_with($caminho, 'assets/') && is_file(__DIR__ . '/' . $caminho)) {
    return false;
}

$arquivo = ROTAS[$caminho] ?? null;

if ($arquivo === null) {
    // Endereço antigo? Redireciona para o novo (só GET; POST é atendido no lugar).
    $antigo = array_search($caminho, ROTAS, true);

    // "pages/exposicao" (endereço limpo com a pasta na frente, que o
    // Início chegou a gerar): também redireciona para o certo.
    if ($antigo === false && str_starts_with($caminho, 'pages/') && isset(ROTAS[substr($caminho, 6)])) {
        $antigo = substr($caminho, 6);
    }

    if (in_array($caminho, SEM_REDIRECIONAR, true)) {
        $arquivo = $caminho;
    } elseif ($antigo !== false) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $consulta = (string) ($_SERVER['QUERY_STRING'] ?? '');
            header('Location: ' . $raiz . '/' . $antigo . ($consulta !== '' ? '?' . $consulta : ''), true, 301);
            exit();
        }
        $arquivo = ROTAS[$antigo];
    }
}

if ($arquivo === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Página não encontrada | ELOS</title></head>'
        . '<body style="font-family: sans-serif; padding: 32px;">'
        . '<h1>Página não encontrada</h1>'
        . '<p><a href="' . htmlspecialchars($raiz . '/', ENT_QUOTES, 'UTF-8') . '">Voltar para o início</a></p>'
        . '</body></html>';
    exit();
}

// A página roda como se tivesse sido aberta direto (alguns trechos
// usam SCRIPT_NAME, como o endereço de retorno do Google).
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $raiz . '/' . $arquivo;
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/' . $arquivo;
chdir(dirname(__DIR__ . '/' . $arquivo));

require __DIR__ . '/' . $arquivo;
