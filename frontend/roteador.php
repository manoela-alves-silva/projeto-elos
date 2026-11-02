<?php

declare(strict_types=1);

/*
 * ROTEADOR — todo endereço do site passa por aqui.
 *
 * Os endereços são curtos e legíveis (/exposicoes, /calendario,
 * /exposicao/memorias-do-rio-antigo, /cine-clube/nova-sessao) e cada
 * um abre a página certa em pages/. Os endereços antigos
 * (pages/evento.php?id=3...) redirecionam para os novos.
 *
 * Apache: o .htaccess manda para cá tudo que não é arquivo.
 * Desenvolvimento: php -S 127.0.0.1:8081 -t frontend frontend/roteador.php
 */

use Elos\Frontend\Projetos;

use function Elos\Frontend\idDaExposicaoNoParametro;
use function Elos\Frontend\linkExposicao;

$caminho = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));

// Raiz do site: '' na raiz do domínio, '/pasta' se o site estiver numa pasta.
$raiz = PHP_SAPI === 'cli-server'
    ? ''
    : rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/roteador.php'))), '/.');
define('ELOS_RAIZ', $raiz);

if ($raiz !== '' && str_starts_with($caminho, $raiz)) {
    $caminho = substr($caminho, strlen($raiz));
}

$caminho = trim($caminho, '/');

// Servidor de desenvolvimento: arquivos de verdade (CSS, imagens, JS)
// saem direto. Código (src/) e arquivos ocultos, nunca.
if (PHP_SAPI === 'cli-server' && $caminho !== '') {
    $arquivo = __DIR__ . '/' . $caminho;

    if (str_starts_with($caminho, 'src/') || str_contains('/' . $caminho, '/.')) {
        http_response_code(404);
        exit();
    }

    if (is_file($arquivo) && !str_ends_with($caminho, '.php')) {
        return false;
    }

    // Volta do login com Google: o endereço está cadastrado no Google.
    if ($caminho === 'pages/google_retorno.php') {
        require __DIR__ . '/pages/google_retorno.php';
        exit();
    }
}

/**
 * Página que atende o endereço (null = nenhuma). Pode definir as
 * constantes que a página lê (ELOS_PROJETO, ELOS_SLUG_EXPOSICAO...).
 * Redirecionamentos de endereços antigos saem daqui mesmo.
 */
function elosPaginaDoEndereco(string $caminho, string $raiz): ?string
{
    $paginasFixas = [
        '' => 'index.php',
        'entrar' => 'pages/login.php',
        'criar-conta' => 'pages/cadastro.php',
        'recuperar-senha' => 'pages/recuperar_senha.php',
        'entrar-com-google' => 'pages/google_entrar.php',
        'sair' => 'pages/logout.php',
        'minha-conta' => 'pages/minha_conta.php',
        'equipe' => 'pages/equipe.php',
        'exposicoes' => 'pages/eventos.php',
        'nova-exposicao' => 'pages/evento_novo.php',
        'calendario' => 'pages/agenda.php',
        'relatorio' => 'pages/relatorio.php',
        'painel' => 'pages/painel.php',
        'capa' => 'capa.php',
    ];

    if (isset($paginasFixas[$caminho])) {
        return $paginasFixas[$caminho];
    }

    require_once __DIR__ . '/src/Planejamento.php';

    $partes = explode('/', $caminho);

    // Monitor do painel ao vivo: /painel/<código>.
    if (count($partes) === 2 && $partes[0] === 'painel' && preg_match('/^[A-Za-z0-9_-]{43}$/', $partes[1]) === 1) {
        define('ELOS_CODIGO_PAINEL', $partes[1]);
        return 'pages/painel.php';
    }

    // Lista de um projeto (/cine-clube) e novo evento (/cine-clube/nova-sessao).
    $projeto = Projetos::daLista($partes[0]);
    if ($projeto !== null && $projeto !== Projetos::EXPOSICOES) {
        $dados = Projetos::dados($projeto);
        define('ELOS_PROJETO', $projeto);

        if (count($partes) === 1) {
            return 'pages/projeto_lista.php';
        }

        if (count($partes) === 2 && $partes[1] === $dados['rota_nova']) {
            return 'pages/projeto_novo.php';
        }
    }

    // Um evento: /exposicao/nome, /sessao/nome... e /.../apagar-exposicao.
    $doEvento = Projetos::daPagina($partes[0]);
    if ($doEvento !== null && (count($partes) === 2 || (count($partes) === 3 && $partes[2] === 'apagar-exposicao'))) {
        // Endereço com um nome antigo (o evento mudou de nome): leva para o atual.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            \Elos\Frontend\iniciarSessao();
            $id = !empty($_SESSION['usuario']['id']) ? idDaExposicaoNoParametro($partes[1]) : 0;

            if ($id > 0 && \Elos\Frontend\slugDaExposicao($id) !== $partes[1]) {
                $consulta = array_filter($_GET, 'is_scalar');
                header('Location: ' . linkExposicao($id, $consulta, '', $partes[2] ?? ''), true, 301);
                exit();
            }
        }

        define('ELOS_PAGINA_PROJETO', $partes[0]);
        define('ELOS_SLUG_EXPOSICAO', $partes[1]);
        return count($partes) === 3 ? 'pages/apagar_exposicao.php' : 'pages/evento.php';
    }

    // Endereços antigos (.php): leva para o novo, mantendo os parâmetros.
    $antigos = [
        'index.php' => '',
        'pages/login.php' => 'entrar',
        'pages/cadastro.php' => 'criar-conta',
        'pages/recuperar_senha.php' => 'recuperar-senha',
        'pages/google_entrar.php' => 'entrar-com-google',
        'pages/logout.php' => 'sair',
        'pages/minha_conta.php' => 'minha-conta',
        'pages/equipe.php' => 'equipe',
        'pages/eventos.php' => 'exposicoes',
        'pages/evento_novo.php' => 'nova-exposicao',
        'pages/agenda.php' => 'calendario',
        'pages/relatorio.php' => 'relatorio',
        'pages/painel.php' => 'painel',
        'capa.php' => 'capa',
    ];

    $consulta = $_GET;

    if (in_array($caminho, ['pages/evento.php', 'pages/apagar_exposicao.php'], true)) {
        \Elos\Frontend\iniciarSessao();
        $id = idDaExposicaoNoParametro($consulta['id'] ?? '');
        unset($consulta['id']);

        if ($id > 0 && !empty($_SESSION['usuario']['id'])) {
            header('Location: ' . linkExposicao($id, array_filter($consulta, 'is_scalar'), '', $caminho === 'pages/apagar_exposicao.php' ? 'apagar-exposicao' : ''), true, 301);
            exit();
        }

        header('Location: ' . $raiz . '/' . ($id > 0 ? 'entrar' : 'exposicoes'));
        exit();
    }

    if (isset($antigos[$caminho])) {
        $query = http_build_query(array_filter($consulta, 'is_scalar'));
        header('Location: ' . $raiz . '/' . $antigos[$caminho] . ($query !== '' ? '?' . $query : ''), true, 301);
        exit();
    }

    return null;
}

$elosPagina = elosPaginaDoEndereco($caminho, $raiz);
unset($caminho, $arquivo);

if ($elosPagina !== null) {
    // No escopo global, como se a página tivesse sido aberta direto.
    require __DIR__ . '/' . $elosPagina;
    exit();
}

// Nada com esse endereço.
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página não encontrada | ELOS</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($raiz, ENT_QUOTES) ?>/assets/css/base.css?v=2026-11-01">
    <link rel="icon" href="<?= htmlspecialchars($raiz, ENT_QUOTES) ?>/assets/img/favicon.svg" type="image/svg+xml">
</head>
<body style="display: grid; place-items: center; min-height: 100vh; text-align: center; padding: 32px;">
    <main>
        <p style="font-size: 3rem; margin: 0;">EL<span style="color: #f4c542;">O</span>S</p>
        <h1>Página não encontrada</h1>
        <p>O endereço pode ter mudado. Comece pelo início.</p>
        <p><a href="<?= htmlspecialchars($raiz, ENT_QUOTES) ?>/" class="primary-button">Ir para o início</a></p>
    </main>
</body>
</html>
