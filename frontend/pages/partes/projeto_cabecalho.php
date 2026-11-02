<?php

declare(strict_types=1);

/*
 * Cabeçalho de um evento de projeto (sessão do Cine Clube, encontro do
 * Café Cultural...): a capa, o nome tirado da ficha (o filme, o tema...),
 * o dia, o local e o andamento do checklist. Incluído por evento.php,
 * que já carregou $evento, $projeto, $ficha, $plano, $resumo e $agenda.
 */

use Elos\Frontend\Identidade;
use Elos\Frontend\Projetos;

use function Elos\Frontend\classeStatusEvento;
use function Elos\Frontend\dataHoraBr;
use function Elos\Frontend\esc;
use function Elos\Frontend\linkExposicao;
use function Elos\Frontend\rotuloStatusEvento;
use function Elos\Frontend\slugDaExposicao;

$fichaAtual = is_array($ficha) ? $ficha : [];
$nomeDoEvento = Projetos::tituloDoEvento($projeto, $fichaAtual, $tituloPagina);
$capaDoEvento = Identidade::urlCapa($evento, 640);
$linhaFicha = Projetos::linhaDaFicha($projeto, $fichaAtual);
$selo = !empty($projeto['selo']) && !empty($fichaAtual[$projeto['selo']]) ? (string) $fichaAtual[$projeto['selo']] : '';
?>
<section class="projeto-cabecalho identidade" style="<?= esc(Identidade::estilo($evento, 640)) ?>">

    <div class="projeto-cabecalho-capa<?= $capaDoEvento !== null ? ' com-imagem' : '' ?>">
        <?php if ($capaDoEvento !== null): ?>
            <img src="<?= esc($capaDoEvento) ?>" alt="">
        <?php else: ?>
            <span class="projeto-capa-vazia"><?= esc($nomeDoEvento) ?></span>
        <?php endif; ?>
    </div>

    <div class="projeto-cabecalho-texto">
        <p class="eyebrow">
            <?= esc($projeto['nome']) ?>
            <span class="status-badge <?= classeStatusEvento((string) ($evento['status'] ?? '')) ?>">
                <?= esc(rotuloStatusEvento((string) ($evento['status'] ?? ''))) ?>
            </span>
        </p>

        <h1><?= esc($nomeDoEvento) ?></h1>

        <?php if ($selo !== '' || $linhaFicha !== ''): ?>
            <p class="projeto-tecnica">
                <?php if ($selo !== ''): ?>
                    <span class="selo selo-<?= esc(strtolower($selo)) ?>"><?= esc($selo) ?></span>
                <?php endif; ?>
                <?= esc($linhaFicha) ?>
            </p>
        <?php endif; ?>

        <?php foreach ($projeto['destaques'] as [$campoDestaque, $rotuloDestaque]): ?>
            <?php if (!empty($fichaAtual[$campoDestaque])): ?>
                <p class="projeto-pessoa"><span><?= esc($rotuloDestaque) ?></span> <?= esc(Projetos::rotuloDoValor($projeto, $campoDestaque, $fichaAtual[$campoDestaque])) ?></p>
            <?php endif; ?>
        <?php endforeach; ?>

        <ul class="expo-fatos">
            <li><span aria-hidden="true">◷</span> <?= esc(dataHoraBr($agenda['abertura'] ?? null, $agenda['horario'] ?? null, 'Dia a definir')) ?></li>
            <?php if (!empty($evento['local_nome'])): ?>
                <li><span aria-hidden="true">⌖</span> <?= esc($evento['local_nome']) ?></li>
            <?php endif; ?>
            <?php if (!empty($evento['responsavel_nome'])): ?>
                <li><span aria-hidden="true">◎</span> <?= esc($evento['responsavel_nome']) ?></li>
            <?php endif; ?>
        </ul>

        <div class="acoes-cabecalho">
            <a href="relatorio?evento=<?= esc(slugDaExposicao($eventoId)) ?>" class="botao-secundario">⎙ Relatório</a>
            <?php if ($podeGerenciar): ?>
                <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'ficha'], 'ficha')) ?>" class="botao-secundario">✎ Editar ficha</a>
                <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'identidade'], 'identidade')) ?>" class="botao-secundario">◐ <?= esc($projeto['capa']) ?> e cor</a>
                <a href="<?= esc(linkExposicao($eventoId, [], '', 'apagar-exposicao')) ?>" class="botao-secundario botao-apagar">Apagar <?= esc($projeto['singular']) ?></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="expo-progresso">
        <?php if ($resumo['total'] > 0): ?>
            <p class="progresso-numero">
                <strong><?= $resumo['concluidas'] ?></strong> de <?= $resumo['total'] ?> prontos
            </p>
            <div class="progresso" role="progressbar" aria-label="Checklist pronto" aria-valuenow="<?= $resumo['percentual'] ?>" aria-valuemin="0" aria-valuemax="100">
                <span style="width: <?= $resumo['percentual'] ?>%"></span>
            </div>
            <p class="progresso-legenda">
                <?= $resumo['pendentes'] ?> pendente<?= $resumo['pendentes'] === 1 ? '' : 's' ?>
                <?php if ($resumo['atrasadas'] > 0): ?>
                    · <span class="texto-atrasado"><?= $resumo['atrasadas'] ?> atrasado<?= $resumo['atrasadas'] === 1 ? '' : 's' ?></span>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="progresso-numero">Checklist vazio</p>
            <p class="progresso-legenda">Diga o que precisa ficar pronto até o dia.</p>
        <?php endif; ?>
    </div>

</section>
