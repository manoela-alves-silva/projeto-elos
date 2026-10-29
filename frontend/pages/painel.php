<?php

declare(strict_types=1);

/*
 * PAINEL AO VIVO — tela para deixar aberta num monitor da produção o
 * dia todo. Mostra, em tempo real e sem enfeite, o que precisa ser
 * feito, em três colunas: atrasados (pulsando devagar até alguém
 * concluir), prioridade alta e pendentes dos próximos dias. Os de
 * prioridade alta sem prazo aparecem numa faixa fina no rodapé.
 *
 * Dois jeitos de abrir:
 *   /painel           — qualquer pessoa logada. Gestores também geram
 *                       aqui o link do monitor.
 *   /painel/<código>  — o monitor, pelo link secreto: só leitura, sem
 *                       a conta de ninguém. Gerar um link novo derruba
 *                       o anterior.
 *
 * A página busca os dados de novo a cada 30 segundos (?dados=1 devolve
 * JSON) e se redesenha sozinha; o comportamento fica em assets/js/painel.js.
 */

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;
use Elos\Frontend\Identidade;

use function Elos\Frontend\esc;
use function Elos\Frontend\flash;
use function Elos\Frontend\iniciarSessao;
use function Elos\Frontend\linkExposicao;
use function Elos\Frontend\preCarregar;
use function Elos\Frontend\raizDoSite;
use function Elos\Frontend\redirecionar;
use function Elos\Frontend\usuarioLogado;

require_once __DIR__ . '/../src/elos.php';
require_once __DIR__ . '/../src/Identidade.php';

$codigo = defined('ELOS_CODIGO_PAINEL') ? (string) constant('ELOS_CODIGO_PAINEL') : '';
$modoMonitor = $codigo !== '';
$usuario = null;
$raiz = raizDoSite();

if ($modoMonitor) {
    iniciarSessao();

    // O link do monitor só mostra o painel: não aceita formulários.
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        exit();
    }

    Api::usarCodigoPainel($codigo);
} else {
    preCarregar(['/api/painel', '/api/eventos']);
    $usuario = usuarioLogado('entrar');
}

$podeGerenciar = $usuario !== null && $usuario['podeGerenciar'];

// ---------------------------------------------------------------
// Link do monitor (só gestores, logados)
// ---------------------------------------------------------------

if (!$modoMonitor && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$podeGerenciar) {
        redirecionar('painel');
    }

    try {
        if (($_POST['acao'] ?? '') === 'gerar') {
            $novo = (string) (Api::post('/api/painel/codigo')['codigo'] ?? '');
            $esquema = \Elos\Frontend\emHttps() ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $_SESSION['_painel_link_novo'] = $esquema . '://' . $host . $raiz . '/painel/' . $novo;
            flash('painel', 'Link novo gerado. O anterior parou de funcionar.');
        } elseif (($_POST['acao'] ?? '') === 'desligar') {
            Api::delete('/api/painel/codigo');
            flash('painel', 'Link do monitor desligado. Nenhum monitor entra até gerar outro.');
        }
    } catch (ApiException $e) {
        flash('painel', 'Não deu certo: ' . $e->getMessage());
    }

    redirecionar('painel?link=1');
}

// ---------------------------------------------------------------
// Dados do painel
// ---------------------------------------------------------------

/**
 * Busca o painel na API e completa cada evento com a cor (a escolhida
 * ou a automática) e, para quem está logado, o link da exposição.
 *
 * @return array{0: int, 1: array<string, mixed>} status HTTP e dados
 */
function dadosDoPainel(bool $comLinks): array
{
    try {
        $dados = Api::get('/api/painel');
    } catch (ApiException $e) {
        $status = $e->statusCode === 403 ? 403 : 503;

        return [$status, ['erro' => $status === 403 ? 'link' : 'conexao']];
    }

    $completar = static function (array $evento) use ($comLinks): array {
        $evento['cor'] = Identidade::cor($evento);
        $evento['tipo_cor'] = Identidade::corDoTipo($evento['tipo'] ?? null, $evento['tipo_cor'] ?? null);

        if ($comLinks) {
            $evento['link'] = linkExposicao((int) $evento['id']);
        }

        return $evento;
    };

    foreach ($dados['colunas'] ?? [] as $coluna => $tarefas) {
        foreach ($tarefas as $i => $tarefa) {
            $dados['colunas'][$coluna][$i]['evento'] = $completar($tarefa['evento']);
        }
    }

    foreach (['sem_prazo', 'concluidas_hoje'] as $lista) {
        foreach ($dados[$lista] ?? [] as $i => $tarefa) {
            $dados[$lista][$i]['evento'] = $completar($tarefa['evento']);
        }
    }

    return [200, $dados];
}

[$status, $dados] = dadosDoPainel(!$modoMonitor);

if (($_GET['dados'] ?? '') === '1') {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if ($modoMonitor && $status === 403) {
    http_response_code(403);
}

// Situação do link do monitor, para a janelinha dos gestores.
$link = null;
$linkNovo = '';
$avisoLink = '';

if ($podeGerenciar) {
    try {
        $link = Api::get('/api/painel/codigo');
    } catch (ApiException) {
        $link = null;
    }

    $linkNovo = (string) ($_SESSION['_painel_link_novo'] ?? '');
    unset($_SESSION['_painel_link_novo']);
    $avisoLink = flash('painel');
}

$abrirJanelaLink = $podeGerenciar && isset($_GET['link']);

$json = static fn (mixed $valor): string => json_encode(
    $valor,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Ícone do ELOS na aba do navegador, no celular e no app instalado. -->
    <link rel="icon" href="<?= esc($raiz) ?>/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="<?= esc($raiz) ?>/assets/img/icone-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="<?= esc($raiz) ?>/assets/img/icone-180.png">
    <link rel="manifest" href="<?= esc($raiz) ?>/assets/elos.webmanifest">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#123d59">
    <title>Painel ao vivo | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= esc($raiz) ?>/assets/css/painel.css?v=2026-10-29g">
</head>
<body class="painel<?= $modoMonitor ? ' painel-monitor' : '' ?>">

<div class="painel-tela" id="painel" aria-live="off">

    <header class="painel-topo">
        <span class="painel-topo-enfeite" aria-hidden="true"></span>

        <div class="painel-marca">
            <strong class="painel-logo">EL<span>O</span>S</strong>
            <span class="painel-subtitulo">Painel da<br>produção</span>
        </div>

        <div class="painel-situacao" id="painel-situacao">
            <span class="painel-ponto" aria-hidden="true"></span>
            <span id="painel-situacao-texto">Ao vivo</span>
        </div>

        <nav class="painel-ferramentas" aria-label="Ferramentas do painel">
            <?php if (!$modoMonitor): ?>
                <a href="<?= esc($raiz) ?>/" class="painel-botao">← Voltar ao ELOS</a>
            <?php endif; ?>
            <?php if ($podeGerenciar): ?>
                <button type="button" class="painel-botao" data-abrir="painel-janela-link">Link do monitor</button>
            <?php endif; ?>
            <button type="button" class="painel-botao" id="painel-tela-cheia">Tela cheia</button>
        </nav>

        <div class="painel-relogio">
            <strong id="painel-hora">--:--</strong>
            <span id="painel-data"></span>
        </div>
    </header>

    <main class="painel-colunas">
        <?php foreach ([
            'atrasados' => ['Atrasados', 'prazo vencido', 'Nada atrasado'],
            'prioridade' => ['Prioridade alta', 'próximos 30 dias', 'Nenhuma prioridade alta'],
            'pendentes' => ['Pendentes', 'próximos 7 dias', 'Nada pendente nesta semana'],
        ] as $coluna => [$tituloColuna, $periodo, $vazio]): ?>
            <section class="painel-coluna painel-coluna-<?= $coluna ?>" aria-labelledby="titulo-<?= $coluna ?>">
                <header class="painel-coluna-topo">
                    <h2 id="titulo-<?= $coluna ?>"><?= esc($tituloColuna) ?> <small><?= esc($periodo) ?></small></h2>
                    <strong class="painel-coluna-total" id="total-<?= $coluna ?>">0</strong>
                </header>
                <div class="painel-coluna-janela" id="lista-<?= $coluna ?>" data-vazio="<?= esc($vazio) ?>"></div>
                <div class="painel-coluna-paginas" id="paginas-<?= $coluna ?>" aria-hidden="true"></div>
            </section>
        <?php endforeach; ?>
    </main>

    <div class="painel-avisos" id="painel-avisos" aria-live="assertive"></div>

    <!-- Faixa fina no rodapé: os itens de prioridade alta que estão sem
         prazo (ficam fora das colunas), mostrando um exemplo por vez. -->
    <aside class="painel-lembrete" id="painel-lembrete" hidden aria-live="off">
        <strong id="painel-lembrete-titulo"></strong>
        <span class="painel-lembrete-exemplo" id="painel-lembrete-exemplo"></span>
        <span class="painel-lembrete-dica">defina um prazo no checklist</span>
    </aside>

    <div class="painel-bloqueio" id="painel-bloqueio" hidden>
        <div>
            <strong>Este link do painel não vale mais.</strong>
            <p>Peça o link novo a quem gerencia o ELOS.</p>
        </div>
    </div>


</div>

<?php if ($podeGerenciar): ?>
    <dialog class="painel-janela" id="painel-janela-link" <?= $abrirJanelaLink ? 'data-abrir-ao-carregar' : '' ?>>
        <h2>Link do monitor</h2>

        <?php if ($avisoLink !== ''): ?>
            <p class="painel-janela-aviso" role="status"><?= esc($avisoLink) ?></p>
        <?php endif; ?>

        <?php if ($linkNovo !== ''): ?>
            <p>Abra este endereço no computador do monitor. <strong>Copie agora:</strong> por segurança, ele não aparece de novo.</p>
            <div class="painel-janela-copiar">
                <input type="text" readonly value="<?= esc($linkNovo) ?>" id="painel-link-novo" aria-label="Link do monitor">
                <button type="button" class="painel-botao" data-copiar="painel-link-novo">Copiar</button>
            </div>
        <?php elseif (!empty($link['ativo'])): ?>
            <p>Há um link valendo desde <?= esc(date('d/m/Y \à\s H:i', strtotime((string) $link['criado_em']))) ?>.
               Perdeu o link? Gere outro: o anterior para de funcionar na hora.</p>
        <?php else: ?>
            <p>Nenhum link valendo. Gere um para abrir o painel no monitor sem entrar com a conta de ninguém.
               Quem tiver o link só vê o painel; não consegue mudar nada.</p>
        <?php endif; ?>

        <div class="painel-janela-acoes">
            <form method="post" action="<?= esc($raiz) ?>/painel">
                <?= \Elos\Frontend\campoCsrf() ?>
                <input type="hidden" name="acao" value="gerar">
                <button type="submit" class="painel-botao painel-botao-forte">
                    <?= !empty($link['ativo']) ? 'Gerar link novo' : 'Gerar link' ?>
                </button>
            </form>

            <?php if (!empty($link['ativo'])): ?>
                <form method="post" action="<?= esc($raiz) ?>/painel">
                    <?= \Elos\Frontend\campoCsrf() ?>
                    <input type="hidden" name="acao" value="desligar">
                    <button type="submit" class="painel-botao">Desligar link</button>
                </form>
            <?php endif; ?>

            <form method="dialog">
                <button type="submit" class="painel-botao">Fechar</button>
            </form>
        </div>
    </dialog>
<?php endif; ?>

<noscript>
    <p class="painel-sem-js">O painel precisa do JavaScript ligado para se atualizar sozinho.</p>
</noscript>

<script type="application/json" id="painel-dados-iniciais"><?= $json(['status' => $status, 'dados' => $dados]) ?></script>
<script src="<?= esc($raiz) ?>/assets/js/painel.js?v=2026-10-29g"></script>
</body>
</html>
