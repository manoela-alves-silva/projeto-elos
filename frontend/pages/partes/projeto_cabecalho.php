<?php

declare(strict_types=1);

/*
 * CABEÇALHO DE UM EVENTO DE PROJETO (sessão do Cine Clube, encontro do
 * Café Cultural...) — usado por pages/evento.php no lugar do cabeçalho
 * da exposição: a capa (cartaz, capa do livro), a ficha, as pessoas em
 * destaque e um "bilhete" com o dia e o horário. O visual de cada
 * projeto vem do CSS do tema (cinema.css, cafe.css...).
 *
 * Usa as variáveis da página: $projeto, $evento, $ficha, $agenda,
 * $resumo, $eventoId, $podeGerenciar, $tituloPagina.
 */

use Elos\Frontend\Identidade;
use Elos\Frontend\Projetos;

use function Elos\Frontend\classeStatusEvento;
use function Elos\Frontend\esc;
use function Elos\Frontend\linkExposicao;
use function Elos\Frontend\rotuloStatusEvento;
use function Elos\Frontend\slugDaExposicao;

const DIAS_DA_SEMANA = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
const MESES_CURTOS = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

$capa = Identidade::urlCapa($evento);
$diaEvento = (string) ($agenda['abertura'] ?? '');
$horaEvento = substr((string) ($agenda['horario'] ?? ''), 0, 5);
$nomeEvento = Projetos::tituloDoEvento($projeto, $ficha, $tituloPagina);
$linhaFicha = Projetos::linhaDaFicha($projeto, $ficha);
$selo = !empty($projeto['selo']) ? (string) ($ficha[$projeto['selo']] ?? '') : '';
?>
<section class="projeto-cabecalho" style="<?= esc(Identidade::estilo($evento)) ?>">

    <div class="projeto-capa<?= $capa !== null ? ' com-imagem' : '' ?>">
        <?php if ($capa !== null): ?>
            <img src="<?= esc($capa) ?>" alt="<?= esc($projeto['capa'] . ': ' . $nomeEvento) ?>">
        <?php else: ?>
            <span class="projeto-capa-vazia"><?= esc($nomeEvento) ?></span>
        <?php endif; ?>
    </div>

    <div class="projeto-ficha">
        <p class="projeto-sobretitulo">
            <?= esc($projeto['sobretitulo']) ?>
            <span class="status-badge <?= classeStatusEvento((string) ($evento['status'] ?? '')) ?>">
                <?= esc(rotuloStatusEvento((string) ($evento['status'] ?? ''))) ?>
            </span>
        </p>

        <h1><?= esc($nomeEvento) ?></h1>

        <?php if ($nomeEvento !== $tituloPagina): ?>
            <p class="projeto-subtitulo"><?= esc($tituloPagina) ?></p>
        <?php endif; ?>

        <?php if ($selo !== '' || $linhaFicha !== '' || $ficha === null): ?>
            <p class="projeto-tecnica">
                <?php if ($selo !== ''): ?>
                    <span class="selo selo-<?= esc(strtolower($selo)) ?>" title="<?= esc(Projetos::rotuloDoValor($projeto, $projeto['selo'], $selo)) ?>"><?= esc($selo) ?></span>
                <?php endif; ?>
                <?= esc($linhaFicha !== '' ? $linhaFicha : ($ficha === null ? 'Ficha ainda não preenchida' : '')) ?>
            </p>
        <?php endif; ?>

        <?php foreach ($projeto['destaques'] ?? [] as [$campo, $rotulo]): ?>
            <?php if (!empty($ficha[$campo])): ?>
                <p class="projeto-pessoa">
                    <span><?= esc($rotulo) ?></span>
                    <?php if (Projetos::tipoDoCampo($projeto, $campo) === 'exposicao'): ?>
                        <a href="<?= esc(linkExposicao((int) $ficha[$campo])) ?>"><?= esc(Projetos::rotuloDoValor($projeto, $campo, $ficha[$campo])) ?></a>
                    <?php else: ?>
                        <?= esc(Projetos::rotuloDoValor($projeto, $campo, $ficha[$campo])) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php foreach ($projeto['etiquetas'] ?? [] as [$campo, $rotulo]): ?>
            <?php if (!empty($ficha[$campo])): ?>
                <span class="projeto-etiqueta"><?= esc($rotulo) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!empty($projeto['lista_destaque']) && !empty($ficha[$projeto['lista_destaque']])): ?>
            <!-- A programação, como num cartaz de show. -->
            <ul class="projeto-lineup">
                <?php foreach (Projetos::linhas((string) $ficha[$projeto['lista_destaque']]) as $linha): ?>
                    <li><?= esc($linha) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="acoes-cabecalho">
            <a href="relatorio?evento=<?= esc(slugDaExposicao($eventoId)) ?>" class="botao-secundario">⎙ Relatório para imprimir</a>
            <?php if ($podeGerenciar): ?>
                <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'ficha'], 'ficha')) ?>" class="botao-secundario">✎ Ficha e horário</a>
                <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'identidade'], 'identidade')) ?>" class="botao-secundario">◐ <?= esc($projeto['capa']) ?></a>
                <a href="<?= esc(linkExposicao($eventoId, [], '', 'apagar-exposicao')) ?>" class="botao-secundario botao-apagar">Apagar <?= esc($projeto['singular']) ?></a>
            <?php endif; ?>
        </div>
    </div>

    <!-- O bilhete: dia, horário, local e o andamento do checklist. -->
    <aside class="projeto-bilhete" aria-label="Dia e horário">
        <?php if ($diaEvento !== ''): ?>
            <?php $quando = strtotime($diaEvento); ?>
            <span class="bilhete-semana"><?= esc(DIAS_DA_SEMANA[(int) date('w', $quando)]) ?></span>
            <strong class="bilhete-dia"><?= esc(date('d', $quando)) ?></strong>
            <span class="bilhete-mes"><?= esc(MESES_CURTOS[(int) date('n', $quando)]) ?> <?= esc(date('Y', $quando)) ?></span>
            <span class="bilhete-hora"><?= $horaEvento !== '' ? esc(str_replace(':', 'h', $horaEvento)) : 'horário a definir' ?></span>
        <?php else: ?>
            <strong class="bilhete-dia bilhete-sem-data">?</strong>
            <span class="bilhete-mes">data a definir</span>
        <?php endif; ?>
        <?php if (!empty($evento['local_nome'])): ?>
            <span class="bilhete-local"><?= esc($evento['local_nome']) ?></span>
        <?php endif; ?>

        <span class="bilhete-andamento">
            <?php if ($resumo['total'] > 0): ?>
                <?= $resumo['concluidas'] ?> de <?= $resumo['total'] ?> prontos
                <?php if ($resumo['atrasadas'] > 0): ?>
                    · <b><?= $resumo['atrasadas'] ?> atrasado<?= $resumo['atrasadas'] === 1 ? '' : 's' ?></b>
                <?php endif; ?>
            <?php else: ?>
                checklist vazio
            <?php endif; ?>
        </span>
    </aside>

</section>
