<?php

declare(strict_types=1);

/*
 * ROTEADOR — endereços limpos do ELOS.
 *
 * Toda visita passa por aqui (pelo .htaccess no Apache, ou como roteador
 * do "php -S" no computador). O endereço que aparece no navegador não
 * mostra pastas nem ".php": /exposicao/luz-e-memoria abre pages/evento.php
 * (o endereço antigo /exposicao?id=5 redireciona para o com o nome).
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
    'painel' => 'pages/painel.php',
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

// Exposição pelo nome: /exposicao/luz-e-memoria (a página descobre o id).
if (preg_match('#^(exposicao|apagar-exposicao)/([a-z0-9-]{1,120})$#', $caminho, $partes) === 1) {
    define('ELOS_SLUG_EXPOSICAO', $partes[2]);
    $caminho = $partes[1];
}

// Projetos da galeria (Cine Clube, Café Cultural...), descritos em
// src/Projetos.php. Cada um tem três endereços:
//   /cine-clube               lista dos eventos do projeto
//   /cine-clube/nova-sessao   criar um evento
//   /cine-clube/nome-do-filme o evento (mesma página da exposição:
//                             checklist, anexos, histórico), com o visual
//                             e a ficha do projeto
require_once __DIR__ . '/src/Projetos.php';

foreach (\Elos\Frontend\Projetos::comArea() as $chaveProjeto) {
    $projeto = \Elos\Frontend\Projetos::dados($chaveProjeto);

    if ($caminho === $projeto['lista'] || $caminho === $projeto['lista'] . '/' . $projeto['rota_nova']) {
        define('ELOS_PROJETO', $chaveProjeto);
        $arquivoProjeto = $caminho === $projeto['lista'] ? 'pages/projeto_lista.php' : 'pages/projeto_novo.php';
        break;
    }

    if (preg_match('#^' . preg_quote($projeto['pagina'], '#') . '/([a-z0-9-]{1,120})$#', $caminho, $partes) === 1) {
        define('ELOS_SLUG_EXPOSICAO', $partes[1]);
        define('ELOS_PAGINA_PROJETO', $projeto['pagina']);
        $caminho = 'exposicao';
        break;
    }
}
unset($projeto, $chaveProjeto);

// Painel ao vivo pelo link secreto do monitor: /painel/<código>.
if (preg_match('#^painel/([A-Za-z0-9_-]{43})$#', $caminho, $partes) === 1) {
    define('ELOS_CODIGO_PAINEL', $partes[1]);
    $caminho = 'painel';
}

$arquivo = $arquivoProjeto ?? ROTAS[$caminho] ?? null;

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
        . '<link rel="icon" href="' . htmlspecialchars($raiz, ENT_QUOTES, 'UTF-8') . '/assets/img/favicon.svg" type="image/svg+xml">'
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
