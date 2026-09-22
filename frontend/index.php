<?php

declare(strict_types=1);

/*
 * INÍCIO — a professora entende a situação sem abrir nenhuma aba.
 *
 * Mostra a exposição em acompanhamento (a que está em cartaz ou a
 * próxima), com as próximas atividades, pendências e concluídas. Tudo
 * vem do que foi cadastrado na exposição; nada de cartões fixos por
 * categoria: o que não existe não aparece.
 */

use Elos\Frontend\MenuLateral;
use Elos\Frontend\Planejamento;

use function Elos\Frontend\apiLista;
use function Elos\Frontend\classeStatusEvento;
use function Elos\Frontend\dataHoraBr;
use function Elos\Frontend\diaMes;
use function Elos\Frontend\esc;
use function Elos\Frontend\exposicaoAtiva;
use function Elos\Frontend\flash;
use function Elos\Frontend\hoje;
use function Elos\Frontend\intervaloBr;
use function Elos\Frontend\mesAbreviado;
use function Elos\Frontend\rotuloStatusEvento;
use function Elos\Frontend\usuarioLogado;

require_once __DIR__ . '/src/Planejamento.php';

$usuario = usuarioLogado('pages/login.php');

$eventos = apiLista('/api/eventos', 'eventos');
$apiIndisponivel = $eventos === null;

$planos = array_map(
    static fn(array $evento): Planejamento => Planejamento::carregar($evento),
    array_values(array_filter($eventos ?? [], 'Elos\Frontend\exposicaoAtiva'))
);

// Em cartaz hoje primeiro; depois as próximas a começar; sem data no fim.
$hoje = hoje();
$ordem = static function (Planejamento $p) use ($hoje): array {
    ['inicio' => $inicio, 'fim' => $fim] = $p->periodo();
    $emCartaz = $inicio !== null && $inicio <= $hoje && ($fim === null || $fim >= $hoje);

    return [$emCartaz ? 0 : ($inicio !== null && $inicio > $hoje ? 1 : 2), $inicio ?? '9999-12-31'];
};
usort($planos, static fn(Planejamento $a, Planejamento $b): int => $ordem($a) <=> $ordem($b));

$escolhida = (int) ($_GET['exposicao'] ?? 0);
$foco = null;
foreach ($planos as $plano) {
    if ($plano->id() === $escolhida) {
        $foco = $plano;
    }
}
$foco ??= $planos[0] ?? null;

// Links do planejamento são relativos a pages/.
$linkPagina = static fn(string $link): string => 'pages/' . $link;

// O que precisa de atenção em todas as exposições.
$alertas = [];
$minhas = [];
foreach ($planos as $plano) {
    foreach ($plano->alertas() as $alerta) {
        $alertas[] = $alerta + ['_plano' => $plano];
    }

    foreach ($plano->necessidades ?? [] as $tarefa) {
        if (
            (int) ($tarefa['usuario_responsavel_id'] ?? 0) === $usuario['id']
            && !in_array(Planejamento::estadoNecessidade($tarefa), ['concluido', 'cancelado'], true)
        ) {
            $minhas[] = $tarefa + ['_plano' => $plano];
        }
    }
}
usort($alertas, static fn(array $a, array $b): int => ($a['prazo'] ?? '') <=> ($b['prazo'] ?? ''));
usort($minhas, static fn(array $a, array $b): int => ($a['prazo'] ?? '9999-12-31') <=> ($b['prazo'] ?? '9999-12-31'));

if ($foco !== null) {
    $resumo = $foco->resumo();
    $periodo = $foco->periodo();
    $proximas = $foco->proximasAtividades(8);
    $proximoMarco = null;
    foreach ($foco->itensDatados() as $item) {
        if ($item['origem'] === 'marco' && $item['data'] >= $hoje) {
            $proximoMarco = $item;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Início | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/planejamento.css">
    <link rel="stylesheet" href="assets/css/inicio.css">
</head>
<body>

<div class="app-shell">

    <?php MenuLateral::renderizar('inicio', true, $usuario['nome'], $usuario['primeiroNome'], $usuario['perfil']); ?>

    <main class="main-content">

        <header class="topbar">
            <form class="topbar-search" action="pages/eventos.php" method="get" role="search">
                <span class="search-icon">⌕</span>
                <input type="search" name="q" placeholder="Buscar exposição…" aria-label="Buscar exposição">
            </form>

            <div class="topbar-actions">
                <?php if ($alertas !== []): ?>
                    <a href="#alertas" class="icon-button" aria-label="<?= count($alertas) ?> alerta(s)" title="<?= count($alertas) ?> alerta(s)">
                        ♢ <span class="notification-dot"></span>
                    </a>
                <?php endif; ?>
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

            <?php if (($avisoInicio = flash('inicio')) !== ''): ?>
                <div class="aviso aviso-ok" role="status"><?= esc($avisoInicio) ?></div>
            <?php endif; ?>

            <?php if ($apiIndisponivel): ?>
                <div class="aviso aviso-erro" role="alert">Não foi possível falar com o servidor do ELOS. Recarregue a página em instantes.</div>
            <?php endif; ?>

            <section class="welcome-section">
                <div>
                    <p class="eyebrow">Painel ELOS</p>
                    <h1>
                        Olá, <?= esc($usuario['primeiroNome']) ?>.
                        <span><?= $foco !== null ? 'Veja o que precisa de atenção.' : 'Vamos dar movimento às exposições.' ?></span>
                    </h1>
                </div>
                <div class="acoes-cabecalho">
                    <?php if ($foco !== null): ?>
                        <a href="pages/relatorio.php" class="botao-secundario">⎙ Relatório de atividades</a>
                    <?php endif; ?>
                    <?php if ($usuario['podeGerenciar']): ?>
                        <a href="pages/evento_novo.php" class="primary-button"><span>+</span> Nova exposição</a>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($foco === null): ?>

                <article class="panel">
                    <div class="empty-state large">
                        <div class="empty-icon">▣</div>
                        <h2>Nenhuma exposição em andamento</h2>
                        <p>Crie uma exposição e diga o que ela precisa. O ELOS organiza as datas, o calendário e as pendências.</p>
                        <?php if ($usuario['podeGerenciar']): ?>
                            <a href="pages/evento_novo.php" class="primary-button"><span>+</span> Nova exposição</a>
                        <?php endif; ?>
                    </div>
                </article>

            <?php else: ?>

                <?php if (count($planos) > 1): ?>
                    <nav class="seletor-exposicao" aria-label="Exposição em acompanhamento">
                        <span>Acompanhando:</span>
                        <?php foreach ($planos as $plano): ?>
                            <a href="?exposicao=<?= $plano->id() ?>" class="<?= $plano->id() === $foco->id() ? 'ativa' : '' ?>" <?= $plano->id() === $foco->id() ? 'aria-current="true"' : '' ?>>
                                <?= esc($plano->titulo()) ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>

                <div class="inicio-grade">

                    <div class="inicio-principal">

                        <!-- A EXPOSIÇÃO EM ACOMPANHAMENTO -->
                        <article class="foco">
                            <div class="foco-cabecalho">
                                <div>
                                    <p class="eyebrow">
                                        <?= esc($foco->evento['tipo_evento_nome'] ?? 'Exposição') ?>
                                        <span class="status-badge <?= classeStatusEvento((string) ($foco->evento['status'] ?? '')) ?>">
                                            <?= esc(rotuloStatusEvento((string) ($foco->evento['status'] ?? ''))) ?>
                                        </span>
                                    </p>
                                    <h2><a href="pages/evento.php?id=<?= $foco->id() ?>"><?= esc($foco->titulo()) ?></a></h2>
                                    <p class="foco-fatos">
                                        <span>◷ <?= esc(intervaloBr($periodo['inicio'], $periodo['fim'], 'Datas a definir')) ?></span>
                                        <?php if (!empty($foco->evento['local_nome'])): ?>
                                            <span>⌖ <?= esc($foco->evento['local_nome']) ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <a href="pages/evento.php?id=<?= $foco->id() ?>" class="primary-button">Abrir exposição →</a>
                            </div>

                            <div class="foco-numeros">
                                <?php if ($resumo['total'] > 0): ?>
                                    <a href="pages/evento.php?id=<?= $foco->id() ?>#necessidades" class="numero pendentes">
                                        <strong><?= $resumo['pendentes'] ?></strong>
                                        <span>pendência<?= $resumo['pendentes'] === 1 ? '' : 's' ?></span>
                                    </a>
                                    <?php if ($resumo['atrasadas'] > 0): ?>
                                        <a href="pages/evento.php?id=<?= $foco->id() ?>#necessidades" class="numero atrasadas">
                                            <strong><?= $resumo['atrasadas'] ?></strong>
                                            <span>atrasada<?= $resumo['atrasadas'] === 1 ? '' : 's' ?></span>
                                        </a>
                                    <?php endif; ?>
                                    <a href="pages/evento.php?id=<?= $foco->id() ?>#necessidades" class="numero concluidas">
                                        <strong><?= $resumo['concluidas'] ?></strong>
                                        <span>concluída<?= $resumo['concluidas'] === 1 ? '' : 's' ?></span>
                                    </a>
                                <?php else: ?>
                                    <p class="numero-vazio">
                                        Nada no planejamento ainda.
                                        <?php if ($usuario['podeGerenciar']): ?>
                                            <a href="pages/evento.php?id=<?= $foco->id() ?>#necessidades" class="text-link">Dizer o que esta exposição precisa →</a>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($proximoMarco !== null): ?>
                                    <a href="<?= esc($linkPagina($proximoMarco['link'])) ?>" class="numero marco grupo-<?= esc($proximoMarco['grupo']) ?>">
                                        <strong><?= esc(diaMes($proximoMarco['data'])) ?></strong>
                                        <span><?= esc($proximoMarco['titulo']) ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if ($resumo['total'] > 0): ?>
                                <div class="foco-progresso">
                                    <div class="progresso"><span style="width: <?= $resumo['percentual'] ?>%"></span></div>
                                    <span><?= $resumo['percentual'] ?>% resolvido</span>
                                </div>
                            <?php endif; ?>
                        </article>

                        <!-- PRÓXIMAS ATIVIDADES (tudo que tem data) -->
                        <article class="panel">
                            <div class="panel-header">
                                <div>
                                    <p class="eyebrow">Da exposição</p>
                                    <h2>Próximas atividades</h2>
                                </div>
                                <a href="pages/agenda.php?evento=<?= $foco->id() ?>" class="text-link">Ver no calendário →</a>
                            </div>

                            <?php if ($proximas === []): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">◷</div>
                                    <p>Nada com data pela frente nesta exposição.</p>
                                </div>
                            <?php else: ?>
                                <ol class="lista-datas">
                                    <?php foreach ($proximas as $item): ?>
                                        <li class="estado-<?= esc($item['estado']) ?> grupo-<?= esc($item['grupo']) ?>">
                                            <span class="event-date">
                                                <strong><?= esc(substr($item['data'], 8, 2)) ?></strong>
                                                <span><?= esc(mesAbreviado($item['data'])) ?></span>
                                            </span>
                                            <a href="<?= esc($linkPagina($item['link'])) ?>" class="lista-datas-texto">
                                                <small><?= esc($item['categoria']) ?><?= $item['horario'] ? ' · ' . esc($item['horario']) : '' ?></small>
                                                <strong><?= esc($item['titulo']) ?></strong>
                                            </a>
                                            <?php if ($item['estado'] !== 'marco'): ?>
                                                <span class="estado-tag <?= esc($item['estado']) ?>"><?= esc($item['rotuloEstado']) ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </article>

                    </div>

                    <aside class="inicio-lateral">

                        <!-- ALERTAS (todas as exposições) -->
                        <article class="panel alert-panel" id="alertas">
                            <div class="panel-header">
                                <div>
                                    <p class="eyebrow">Atenção</p>
                                    <h2>Alertas</h2>
                                </div>
                                <?php if ($alertas !== []): ?>
                                    <span class="alert-symbol"><?= count($alertas) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($alertas === []): ?>
                                <div class="all-clear">
                                    <span class="all-clear-icon">✓</span>
                                    <div>
                                        <strong>Tudo em ordem.</strong>
                                        <p>Nenhuma pendência atrasada ou urgente.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert-list">
                                    <?php foreach (array_slice($alertas, 0, 5) as $alerta): ?>
                                        <a href="pages/evento.php?id=<?= $alerta['_plano']->id() ?>#item-<?= (int) $alerta['id'] ?>" class="alert-card">
                                            <strong><?= esc($alerta['titulo'] ?? '') ?></strong>
                                            <span>
                                                <?= esc($alerta['_motivo']) ?>
                                                <?= !empty($alerta['prazo']) ? '· ' . esc(dataHoraBr($alerta['prazo'])) : '' ?>
                                            </span>
                                            <small><?= esc($alerta['_plano']->titulo()) ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                    <?php if (count($alertas) > 5): ?>
                                        <p class="mais">+ <?= count($alertas) - 5 ?> outros alertas</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </article>

                        <!-- ATRIBUÍDAS A MIM (só aparece se houver) -->
                        <?php if ($minhas !== []): ?>
                            <article class="panel">
                                <div class="panel-header">
                                    <div>
                                        <p class="eyebrow">Com você</p>
                                        <h2>Suas pendências</h2>
                                    </div>
                                    <span class="panel-count"><?= count($minhas) ?></span>
                                </div>
                                <ul class="lista-minhas">
                                    <?php foreach (array_slice($minhas, 0, 6) as $tarefa): ?>
                                        <li>
                                            <a href="pages/evento.php?id=<?= $tarefa['_plano']->id() ?>#item-<?= (int) $tarefa['id'] ?>">
                                                <strong><?= esc($tarefa['titulo'] ?? '') ?></strong>
                                                <span>
                                                    <?= esc(!empty($tarefa['prazo']) ? diaMes((string) $tarefa['prazo']) : 'Sem data') ?>
                                                    · <?= esc($tarefa['_plano']->titulo()) ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </article>
                        <?php endif; ?>

                        <!-- OUTRAS EXPOSIÇÕES -->
                        <?php if (count($planos) > 1): ?>
                            <article class="panel">
                                <div class="panel-header">
                                    <div>
                                        <p class="eyebrow">Em andamento</p>
                                        <h2>Outras exposições</h2>
                                    </div>
                                    <a href="pages/eventos.php" class="text-link">Ver todas →</a>
                                </div>
                                <ul class="lista-outras">
                                    <?php foreach ($planos as $plano): ?>
                                        <?php if ($plano->id() === $foco->id()) { continue; } ?>
                                        <?php $r = $plano->resumo(); $p = $plano->periodo(); ?>
                                        <li>
                                            <a href="?exposicao=<?= $plano->id() ?>">
                                                <strong><?= esc($plano->titulo()) ?></strong>
                                                <span><?= esc(intervaloBr($p['inicio'], $p['fim'], 'Datas a definir')) ?></span>
                                                <?php if ($r['total'] > 0): ?>
                                                    <span class="progresso"><span style="width: <?= $r['percentual'] ?>%"></span></span>
                                                    <small><?= $r['pendentes'] ?> pendente<?= $r['pendentes'] === 1 ? '' : 's' ?><?= $r['atrasadas'] > 0 ? ' · ' . $r['atrasadas'] . ' atrasada' . ($r['atrasadas'] === 1 ? '' : 's') : '' ?></small>
                                                <?php else: ?>
                                                    <small>Nada no planejamento ainda</small>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </article>
                        <?php endif; ?>

                        <article class="movement-card">
                            <div class="movement-decoration">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            <p>IDEIA DO ELOS</p>
                            <h3>
                                Eventos não são apenas datas.
                                <span>São experiências em movimento.</span>
                            </h3>
                        </article>

                    </aside>

                </div>

            <?php endif; ?>

        </div>
    </main>
</div>

</body>
</html>
