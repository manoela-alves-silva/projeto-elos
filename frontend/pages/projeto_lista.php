<?php

declare(strict_types=1);

/*
 * LISTA DE UM PROJETO (Cine Clube, Café Cultural...) — o próximo evento
 * em destaque, os seguintes como cartões e os que já passaram numa
 * lista discreta. Clicar abre o evento, onde está o checklist, os
 * anexos e o histórico. O projeto vem do endereço (ver roteador.php) e
 * o visual vem do CSS do tema dele (cinema.css, cafe.css...).
 */

use Elos\Frontend\Identidade;
use Elos\Frontend\MenuLateral;
use Elos\Frontend\Planejamento;
use Elos\Frontend\Projetos;

use function Elos\Frontend\apiLista;
use function Elos\Frontend\esc;
use function Elos\Frontend\flash;
use function Elos\Frontend\hoje;
use function Elos\Frontend\linkExposicao;
use function Elos\Frontend\preCarregar;
use function Elos\Frontend\usuarioLogado;

require_once __DIR__ . '/../src/Planejamento.php';
require_once __DIR__ . '/../src/Identidade.php';

$projeto = Projetos::dados(defined('ELOS_PROJETO') ? (string) constant('ELOS_PROJETO') : Projetos::CINE_CLUBE);

preCarregar(['/api/eventos', '/api/fichas']);
$usuario = usuarioLogado('entrar');

$eventos = apiLista('/api/eventos', 'eventos');
$apiIndisponivel = $eventos === null;
$planos = Planejamento::carregarVarios(Projetos::filtrar($eventos ?? [], $projeto['chave']));

// Ficha de cada evento (evento_id => campos), numa resposta só.
$fichas = [];
foreach (apiLista('/api/fichas', 'fichas') ?? [] as $ficha) {
    $fichas[(int) $ficha['evento_id']] = is_array($ficha['dados'] ?? null) ? $ficha['dados'] : [];
}

// Dia do evento: a data marcada na agenda (abertura).
$diaDo = static fn(Planejamento $p): string => (string) ($p->agenda['abertura'] ?? '');

$proximos = $anteriores = [];
foreach ($planos as $plano) {
    $dia = $diaDo($plano);
    if (($plano->evento['status'] ?? '') === 'CANCELADO' || ($dia !== '' && $dia < hoje())) {
        $anteriores[] = $plano;
    } else {
        $proximos[] = $plano;
    }
}

// Próximos: o mais perto primeiro (sem data no fim). Anteriores: o mais recente primeiro.
usort($proximos, static fn(Planejamento $a, Planejamento $b): int =>
    (($diaDo($a) ?: '9999') . ($a->agenda['horario'] ?? '')) <=> (($diaDo($b) ?: '9999') . ($b->agenda['horario'] ?? '')));
usort($anteriores, static fn(Planejamento $a, Planejamento $b): int => $diaDo($b) <=> $diaDo($a));

$destaque = $proximos !== [] && $diaDo($proximos[0]) !== '' ? array_shift($proximos) : null;
$mensagem = flash($projeto['chave']);

const SEMANA_CURTA = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
const MES_CURTO = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/** "qui · 13 nov · 19h00" (ou "data a definir"). */
function quandoDoEvento(Planejamento $plano): string
{
    $dia = (string) ($plano->agenda['abertura'] ?? '');

    if ($dia === '') {
        return 'data a definir';
    }

    $t = strtotime($dia);
    $hora = substr((string) ($plano->agenda['horario'] ?? ''), 0, 5);

    return SEMANA_CURTA[(int) date('w', $t)] . ' · ' . date('d', $t) . ' ' . MES_CURTO[(int) date('n', $t)]
        . ($hora !== '' ? ' · ' . str_replace(':', 'h', $hora) : '');
}

/** "3 de 9 prontos · 1 atrasado" */
function andamentoDoEvento(Planejamento $plano): string
{
    $r = $plano->resumo();

    if ($r['total'] === 0) {
        return 'checklist vazio';
    }

    return $r['concluidas'] . ' de ' . $r['total'] . ' prontos'
        . ($r['atrasadas'] > 0 ? ' · ' . $r['atrasadas'] . ' atrasado' . ($r['atrasadas'] === 1 ? '' : 's') : '');
}

/** "Debate com Fulano" / "Com Fulana · Mediação: Sicrano" */
function destaquesDoEvento(array $projeto, array $ficha): array
{
    $linhas = [];
    foreach ($projeto['destaques'] ?? [] as [$campo, $rotulo]) {
        if (!empty($ficha[$campo])) {
            $linhas[] = [$rotulo, Projetos::rotuloDoValor($projeto, $campo, $ficha[$campo])];
        }
    }

    return $linhas;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($projeto['nome']) ?> | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&family=Lora:ital,wght@0,600;1,500&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=2026-10-29">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=2026-10-29">
    <link rel="stylesheet" href="assets/css/planejamento.css?v=2026-10-29">
    <link rel="stylesheet" href="assets/css/projeto.css?v=2026-10-29">
    <link rel="stylesheet" href="assets/css/<?= esc($projeto['tema']) ?>.css?v=2026-10-29">
    <!-- Ícone do ELOS na aba do navegador, no celular e no app instalado. -->
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/img/icone-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="assets/img/icone-180.png">
    <link rel="manifest" href="assets/elos.webmanifest">
    <meta name="theme-color" content="#123d59">
</head>
<body class="tema-<?= esc($projeto['tema']) ?>">

<div class="app-shell">

    <?php MenuLateral::renderizar($projeto['menu'], false, $usuario['nome'], $usuario['primeiroNome'], $usuario['perfil']); ?>

    <main class="main-content">

        <header class="topbar">
            <div></div>
            <div class="topbar-actions">
                <div class="topbar-user">
                    <div class="user-avatar small"><?= esc(mb_strtoupper(mb_substr($usuario['nome'], 0, 1))) ?></div>
                    <div>
                        <strong><?= esc($usuario['primeiroNome']) ?></strong>
                        <span><?= esc($usuario['perfil']) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <div class="content-wrapper">

            <?php if ($mensagem !== ''): ?>
                <div class="aviso aviso-ok" role="status"><?= esc($mensagem) ?></div>
            <?php endif; ?>

            <?php if ($apiIndisponivel): ?>
                <div class="aviso aviso-erro" role="alert">Não foi possível carregar agora. Tente de novo em instantes.</div>
            <?php endif; ?>

            <section class="welcome-section">
                <div>
                    <p class="eyebrow"><?= esc($projeto['sobretitulo']) ?></p>
                    <h1>
                        <?= esc($projeto['plural']) ?>
                        <span><?= esc($projeto['frase_lista']) ?></span>
                    </h1>
                </div>

                <div class="projeto-acoes">
                    <a href="relatorio?projeto=<?= esc($projeto['chave']) ?>" class="botao-secundario">⎙ Relatório</a>
                    <?php if ($usuario['podeGerenciar']): ?>
                        <a href="<?= esc($projeto['lista'] . '/' . $projeto['rota_nova']) ?>" class="primary-button"><span>+</span> <?= esc($projeto['novo']) ?></a>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($destaque === null && $proximos === [] && $anteriores === []): ?>

                <article class="panel">
                    <div class="empty-state large">
                        <div class="empty-icon"><?= esc($projeto['icone']) ?></div>
                        <h2><?= esc($projeto['vazio'][0]) ?></h2>
                        <p><?= esc($projeto['vazio'][1]) ?></p>
                        <?php if ($usuario['podeGerenciar']): ?>
                            <a href="<?= esc($projeto['lista'] . '/' . $projeto['rota_nova']) ?>" class="primary-button"><?= esc($projeto['novo']) ?></a>
                        <?php endif; ?>
                    </div>
                </article>

            <?php endif; ?>

            <?php if ($destaque !== null): ?>
                <?php
                $id = (int) $destaque->evento['id'];
                $ficha = $fichas[$id] ?? [];
                $capa = Identidade::urlCapa($destaque->evento);
                ?>
                <!-- O PRÓXIMO: em destaque -->
                <a href="<?= esc(linkExposicao($id)) ?>" class="projeto-destaque" style="<?= esc(Identidade::estilo($destaque->evento)) ?>">
                    <div class="projeto-capa<?= $capa !== null ? ' com-imagem' : '' ?>">
                        <?php if ($capa !== null): ?>
                            <img src="<?= esc($capa) ?>" alt="">
                        <?php else: ?>
                            <span class="projeto-capa-vazia"><?= esc(Projetos::tituloDoEvento($projeto, $ficha, (string) $destaque->evento['titulo'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="projeto-ficha">
                        <p class="projeto-sobretitulo"><?= esc($projeto['proxima']) ?></p>
                        <h2><?= esc(Projetos::tituloDoEvento($projeto, $ficha, (string) $destaque->evento['titulo'])) ?></h2>
                        <p class="projeto-tecnica">
                            <?php if (!empty($projeto['selo']) && !empty($ficha[$projeto['selo']])): ?>
                                <span class="selo selo-<?= esc(strtolower((string) $ficha[$projeto['selo']])) ?>"><?= esc($ficha[$projeto['selo']]) ?></span>
                            <?php endif; ?>
                            <?= esc(Projetos::linhaDaFicha($projeto, $ficha)) ?>
                        </p>
                        <?php foreach (destaquesDoEvento($projeto, $ficha) as [$rotulo, $valor]): ?>
                            <p class="projeto-pessoa"><span><?= esc($rotulo) ?></span> <?= esc($valor) ?></p>
                        <?php endforeach; ?>
                        <?php if (!empty($projeto['lista_destaque']) && !empty($ficha[$projeto['lista_destaque']])): ?>
                            <ul class="projeto-lineup">
                                <?php foreach (array_slice(Projetos::linhas((string) $ficha[$projeto['lista_destaque']]), 0, 8) as $linha): ?>
                                    <li><?= esc($linha) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div class="projeto-bilhete">
                        <span class="bilhete-quando"><?= esc(quandoDoEvento($destaque)) ?></span>
                        <?php if (!empty($destaque->evento['local_nome'])): ?>
                            <span class="bilhete-local"><?= esc($destaque->evento['local_nome']) ?></span>
                        <?php endif; ?>
                        <span class="bilhete-andamento"><?= esc(andamentoDoEvento($destaque)) ?></span>
                    </div>
                </a>
            <?php endif; ?>

            <?php if ($proximos !== []): ?>
                <section class="projeto-secao">
                    <h2 class="projeto-secao-titulo">Em breve</h2>
                    <div class="projeto-cartoes">
                        <?php foreach ($proximos as $plano): ?>
                            <?php
                            $id = (int) $plano->evento['id'];
                            $ficha = $fichas[$id] ?? [];
                            $capa = Identidade::urlCapa($plano->evento);
                            $nome = Projetos::tituloDoEvento($projeto, $ficha, (string) $plano->evento['titulo']);
                            $pessoa = destaquesDoEvento($projeto, $ficha)[0] ?? null;
                            ?>
                            <a href="<?= esc(linkExposicao($id)) ?>" class="projeto-cartao" style="<?= esc(Identidade::estilo($plano->evento)) ?>">
                                <div class="projeto-cartao-capa<?= $capa !== null ? ' com-imagem' : '' ?>">
                                    <?php if ($capa !== null): ?>
                                        <img src="<?= esc($capa) ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <span><?= esc($nome) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="projeto-cartao-info">
                                    <span class="projeto-cartao-quando"><?= esc(quandoDoEvento($plano)) ?></span>
                                    <strong><?= esc($nome) ?></strong>
                                    <?php if ($pessoa !== null): ?>
                                        <span class="projeto-cartao-pessoa"><?= esc($pessoa[0] . ' ' . $pessoa[1]) ?></span>
                                    <?php endif; ?>
                                    <span class="projeto-cartao-andamento<?= $plano->resumo()['atrasadas'] > 0 ? ' com-atraso' : '' ?>"><?= esc(andamentoDoEvento($plano)) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($anteriores !== []): ?>
                <section class="projeto-secao">
                    <h2 class="projeto-secao-titulo"><?= esc($projeto['anteriores']) ?></h2>
                    <ul class="projeto-anteriores">
                        <?php foreach ($anteriores as $plano): ?>
                            <?php
                            $id = (int) $plano->evento['id'];
                            $ficha = $fichas[$id] ?? [];
                            $pessoa = destaquesDoEvento($projeto, $ficha)[0] ?? null;
                            ?>
                            <li>
                                <a href="<?= esc(linkExposicao($id)) ?>">
                                    <span class="anterior-quando"><?= esc(quandoDoEvento($plano)) ?></span>
                                    <strong><?= esc(Projetos::tituloDoEvento($projeto, $ficha, (string) $plano->evento['titulo'])) ?></strong>
                                    <?php if (($plano->evento['status'] ?? '') === 'CANCELADO'): ?>
                                        <span class="estado-tag cancelado"><?= esc($projeto['cancelado']) ?></span>
                                    <?php elseif ($pessoa !== null): ?>
                                        <span class="anterior-pessoa"><?= esc($pessoa[0] . ' ' . $pessoa[1]) ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

        </div>

    </main>

</div>

</body>
</html>
