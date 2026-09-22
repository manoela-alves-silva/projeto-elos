<?php

declare(strict_types=1);

/*
 * CALENDÁRIO — consequência do planejamento, nunca a origem.
 *
 * Mostra tudo o que tem data nas exposições: marcos da agenda
 * (montagem, abertura, período, desmontagem), necessidades com o
 * status atual, visitas, transportes e documentos. Nada é cadastrado
 * aqui: cada item leva à exposição, onde ele é editado.
 */

use Elos\Frontend\MenuLateral;
use Elos\Frontend\Planejamento;

use function Elos\Frontend\apiLista;
use function Elos\Frontend\dataBr;
use function Elos\Frontend\esc;
use function Elos\Frontend\hoje;
use function Elos\Frontend\redirecionar;
use function Elos\Frontend\usuarioLogado;

use const Elos\Frontend\MESES;

require_once __DIR__ . '/../src/Planejamento.php';

$usuario = usuarioLogado('login.php');

// Endereço antigo do editor de agenda: as datas agora são editadas na exposição.
if ((int) ($_GET['agenda_evento'] ?? 0) > 0) {
    redirecionar('evento.php?id=' . (int) $_GET['agenda_evento'] . '&editar=datas#datas');
}

$eventos = apiLista('/api/eventos', 'eventos');
$apiIndisponivel = $eventos === null;

// Canceladas não ocupam o calendário.
$eventos = array_values(array_filter($eventos ?? [], static fn(array $e): bool => ($e['status'] ?? '') !== 'CANCELADO'));
usort($eventos, static fn(array $a, array $b): int => strcmp((string) $a['titulo'], (string) $b['titulo']));

$filtroEvento = (int) ($_GET['evento'] ?? 0);
$planos = [];
foreach ($eventos as $evento) {
    if ($filtroEvento === 0 || (int) $evento['id'] === $filtroEvento) {
        $planos[] = Planejamento::carregar($evento);
    }
}

// Itens por dia e dias em cartaz.
$itensPorDia = [];
$emCartazPorDia = [];
foreach ($planos as $plano) {
    foreach ($plano->itensDatados() as $item) {
        $itensPorDia[$item['data']][] = $item;
    }

    ['inicio' => $inicio, 'fim' => $fim] = $plano->periodo();
    if ($inicio !== null && $fim !== null && $inicio <= $fim) {
        for ($dia = strtotime($inicio); $dia <= strtotime($fim); $dia = strtotime('+1 day', $dia)) {
            $emCartazPorDia[date('Y-m-d', $dia)][] = $plano->titulo();
        }
    }
}

// Mês exibido: o pedido; ao abrir uma exposição, o da próxima data
// dela; senão o mês atual.
$hojeData = new DateTimeImmutable('today');
$mesPadrao = $hojeData->format('Y-m');
if ($filtroEvento > 0 && !isset($_GET['mes'])) {
    $datas = array_keys($itensPorDia);
    sort($datas);
    $futuras = array_values(array_filter($datas, static fn(string $d): bool => $d >= hoje()));
    $mesPadrao = substr($futuras[0] ?? ($datas[0] ?? $mesPadrao), 0, 7);
}
$ano = (int) ($_GET['ano'] ?? substr($mesPadrao, 0, 4));
$mes = (int) ($_GET['mes'] ?? substr($mesPadrao, 5, 2));
if ($mes < 1 || $mes > 12 || $ano < 1970 || $ano > 2200) {
    $ano = (int) $hojeData->format('Y');
    $mes = (int) $hojeData->format('n');
}

$primeiroDia = new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes));
$diasNoMes = (int) $primeiroDia->format('t');
$inicioSemana = (int) $primeiroDia->format('w');
$totalCelulas = (int) ceil(($inicioSemana + $diasNoMes) / 7) * 7;
$anterior = $primeiroDia->modify('-1 month');
$seguinte = $primeiroDia->modify('+1 month');

$diaSelecionado = (string) ($_GET['dia'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $diaSelecionado)) {
    $diaSelecionado = '';
}

/** Link mantendo o filtro de exposição. */
function linkCalendario(int $ano, int $mes, int $evento, ?string $dia = null): string
{
    $parametros = ['ano' => $ano, 'mes' => $mes];
    if ($evento > 0) {
        $parametros['evento'] = $evento;
    }
    if ($dia !== null) {
        $parametros['dia'] = $dia;
    }

    return '?' . http_build_query($parametros);
}

/** Classe de cor de um item: marcos pela fase, o resto pelo estado. */
function corDoItem(array $item): string
{
    return $item['origem'] === 'marco' ? 'fase-' . $item['grupo'] : 'estado-' . $item['estado'];
}

/** Texto curto da pílula. */
function textoDaPilula(array $item, bool $variasExposicoes): string
{
    if ($item['origem'] === 'marco') {
        return $item['titulo'] . ($variasExposicoes ? ' · ' . $item['evento_titulo'] : '');
    }

    return $item['titulo'];
}

$variasExposicoes = count($planos) > 1;
$nomeMes = ucfirst(MESES[$mes]) . ' de ' . $ano;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/planejamento.css">
    <link rel="stylesheet" href="../assets/css/agenda.css">
</head>
<body>

<div class="app-shell">

    <?php MenuLateral::renderizar('agenda', false, $usuario['nome'], $usuario['primeiroNome'], $usuario['perfil']); ?>

    <main class="main-content">

        <header class="topbar">
            <form method="get" action="agenda.php" class="filtro-exposicao form-elos">
                <input type="hidden" name="ano" value="<?= $ano ?>">
                <input type="hidden" name="mes" value="<?= $mes ?>">
                <label for="evento" class="sr-only">Exposição</label>
                <select id="evento" name="evento" onchange="this.form.submit()">
                    <option value="0">Todas as exposições</option>
                    <?php foreach ($eventos as $evento): ?>
                        <option value="<?= (int) $evento['id'] ?>" <?= (int) $evento['id'] === $filtroEvento ? 'selected' : '' ?>><?= esc($evento['titulo']) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="text-link">Filtrar</button></noscript>
            </form>
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

            <?php if ($apiIndisponivel): ?>
                <div class="aviso aviso-erro" role="alert">Não foi possível carregar as exposições agora.</div>
            <?php endif; ?>

            <section class="welcome-section">
                <div>
                    <p class="eyebrow">Acompanhamento</p>
                    <h1>
                        Calendário
                        <span>Tudo o que tem data nas exposições aparece aqui sozinho.</span>
                    </h1>
                </div>
            </section>

            <article class="panel">

                <div class="panel-header calendario-topo">
                    <div class="calendar-nav">
                        <a href="<?= esc(linkCalendario((int) $anterior->format('Y'), (int) $anterior->format('n'), $filtroEvento)) ?>" class="icon-button" aria-label="Mês anterior">‹</a>
                        <a href="<?= esc(linkCalendario((int) $hojeData->format('Y'), (int) $hojeData->format('n'), $filtroEvento)) ?>" class="botao-hoje">Hoje</a>
                        <a href="<?= esc(linkCalendario((int) $seguinte->format('Y'), (int) $seguinte->format('n'), $filtroEvento)) ?>" class="icon-button" aria-label="Próximo mês">›</a>
                        <h2><?= esc($nomeMes) ?></h2>
                    </div>

                    <ul class="legenda" aria-label="Legenda">
                        <li class="fase-montagem">Montagem</li>
                        <li class="fase-abertura">Abertura</li>
                        <li class="fase-permanencia">Período</li>
                        <li class="fase-desmontagem">Desmontagem</li>
                        <li class="estado-pendente">Pendente</li>
                        <li class="estado-atrasado">Atrasado</li>
                        <li class="estado-agendado">Agendado</li>
                        <li class="estado-concluido">Concluído</li>
                    </ul>
                </div>

                <div class="calendar-weekdays">
                    <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $nomeDia): ?>
                        <span><?= $nomeDia ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="calendar-grid">
                    <?php for ($i = 0; $i < $totalCelulas; $i++): ?>
                        <?php
                        $numero = $i - $inicioSemana + 1;
                        $dentro = $numero >= 1 && $numero <= $diasNoMes;
                        ?>
                        <?php if (!$dentro): ?>
                            <div class="calendar-day outside"></div>
                            <?php continue; ?>
                        <?php endif; ?>
                        <?php
                        $data = sprintf('%04d-%02d-%02d', $ano, $mes, $numero);
                        $itens = $itensPorDia[$data] ?? [];
                        $classes = ['calendar-day'];
                        if ($data === hoje()) {
                            $classes[] = 'today';
                        }
                        if ($data === $diaSelecionado) {
                            $classes[] = 'selected';
                        }
                        if (isset($emCartazPorDia[$data])) {
                            $classes[] = 'em-cartaz';
                        }
                        ?>
                        <div class="<?= implode(' ', $classes) ?>" <?= isset($emCartazPorDia[$data]) ? 'title="Em cartaz: ' . esc(implode(', ', array_unique($emCartazPorDia[$data]))) . '"' : '' ?>>
                            <a href="<?= esc(linkCalendario($ano, $mes, $filtroEvento, $data)) ?>#dia" class="day-number" aria-label="Ver o dia <?= $numero ?>"><?= $numero ?></a>

                            <?php if ($itens !== []): ?>
                                <div class="day-markers">
                                    <?php foreach (array_slice($itens, 0, 3) as $item): ?>
                                        <a
                                            href="<?= esc($item['link']) ?>"
                                            class="marker-pill <?= corDoItem($item) ?>"
                                            title="<?= esc($item['categoria'] . ': ' . $item['titulo'] . ($item['estado'] !== 'marco' ? ' — ' . $item['rotuloEstado'] : '') . ' (' . $item['evento_titulo'] . ')') ?>"
                                        ><?= $item['estado'] === 'concluido' ? '✓ ' : '' ?><?= esc(textoDaPilula($item, $variasExposicoes)) ?></a>
                                    <?php endforeach; ?>
                                    <?php if (count($itens) > 3): ?>
                                        <a href="<?= esc(linkCalendario($ano, $mes, $filtroEvento, $data)) ?>#dia" class="marker-more">+<?= count($itens) - 3 ?> mais</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>

            </article>

            <?php if ($diaSelecionado !== ''): ?>
                <?php $itensDoDia = $itensPorDia[$diaSelecionado] ?? []; ?>
                <article class="panel dia-selecionado" id="dia">
                    <div class="panel-header">
                        <div>
                            <p class="eyebrow">Dia selecionado</p>
                            <h2><?= esc(dataBr($diaSelecionado)) ?></h2>
                        </div>
                        <a href="<?= esc(linkCalendario($ano, $mes, $filtroEvento)) ?>" class="text-link">Fechar</a>
                    </div>

                    <?php if (isset($emCartazPorDia[$diaSelecionado])): ?>
                        <p class="em-cartaz-texto">Em cartaz: <?= esc(implode(', ', array_unique($emCartazPorDia[$diaSelecionado]))) ?></p>
                    <?php endif; ?>

                    <?php if ($itensDoDia === []): ?>
                        <p class="lateral-vazio">Nada marcado para este dia.</p>
                    <?php else: ?>
                        <ul class="lista-dia">
                            <?php foreach ($itensDoDia as $item): ?>
                                <li>
                                    <span class="bolinha <?= corDoItem($item) ?>" aria-hidden="true"></span>
                                    <a href="<?= esc($item['link']) ?>">
                                        <small><?= esc($item['categoria']) ?><?= $item['horario'] ? ' · ' . esc($item['horario']) : '' ?> · <?= esc($item['evento_titulo']) ?></small>
                                        <strong><?= esc($item['titulo']) ?></strong>
                                    </a>
                                    <?php if ($item['estado'] !== 'marco'): ?>
                                        <span class="estado-tag <?= esc($item['estado']) ?>"><?= esc($item['rotuloEstado']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endif; ?>

        </div>
    </main>
</div>

</body>
</html>
