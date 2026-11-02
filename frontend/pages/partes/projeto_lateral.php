<?php

declare(strict_types=1);

/*
 * Lateral de um evento de projeto: a ficha (o filme, o convidado, a
 * programação...) com o dia e o horário, e o formulário para editá-la.
 * Incluído por evento.php.
 */

use Elos\Frontend\Projetos;

use function Elos\Frontend\avisoConflito;
use function Elos\Frontend\dataHoraBr;
use function Elos\Frontend\esc;
use function Elos\Frontend\linkExposicao;

$fichaAtual = is_array($ficha) ? $ficha : [];
?>
<article class="panel ficha-projeto" id="ficha">
    <div class="panel-header">
        <div><p class="eyebrow">Ficha</p><h2><?= esc($projeto['passo_ficha'][0] ?: 'Ficha') ?></h2></div>
        <?php if ($podeGerenciar && !$editarFicha): ?>
            <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'ficha'], 'ficha')) ?>" class="text-link">Editar</a>
        <?php endif; ?>
    </div>

    <?php if ($editarFicha): ?>

        <?php
        // Após um erro ou aviso, reabre com o que foi digitado.
        $comErro = $formComErro === 'ficha';
        $valoresFicha = $comErro
            ? Projetos::lerFicha($projeto, $_POST)['valores']
            : Projetos::valoresDaFicha($projeto, $fichaAtual);
        $diaFicha = $comErro ? (string) ($_POST['ficha_data'] ?? '') : (string) ($agenda['abertura'] ?? '');
        $horarioFicha = $comErro ? (string) ($_POST['ficha_horario'] ?? '') : substr((string) ($agenda['horario'] ?? ''), 0, 5);
        ?>
        <form method="post" action="<?= esc(linkExposicao($eventoId)) ?>" class="form-elos form-lateral form-ficha">
            <?= \Elos\Frontend\campoCsrf() ?>
            <input type="hidden" name="acao" value="salvar_ficha">
            <?php if ($comErro && $erroForm !== ''): ?>
                <p class="form-erro" role="alert"><?= esc($erroForm) ?></p>
            <?php endif; ?>

            <?php Projetos::campos($projeto, $valoresFicha); ?>

            <fieldset class="campo-duplo">
                <legend>Dia</legend>
                <input type="date" name="ficha_data" value="<?= esc($diaFicha) ?>" aria-label="Dia">
                <span>às</span>
                <input type="time" name="ficha_horario" value="<?= esc($horarioFicha) ?>" aria-label="Horário">
            </fieldset>

            <?php if ($comErro && $conflitos !== []) { avisoConflito($conflitos); } ?>

            <div class="form-botoes">
                <a href="<?= esc(linkExposicao($eventoId, [], 'ficha')) ?>" class="text-link">Cancelar</a>
                <button type="submit" class="primary-button">Salvar ficha</button>
            </div>
        </form>

    <?php else: ?>

        <dl class="sobre ficha-dados">
            <div>
                <dt><?= esc($projeto['momento']) ?></dt>
                <dd><?= esc(dataHoraBr($agenda['abertura'] ?? null, $agenda['horario'] ?? null, 'Dia a definir')) ?></dd>
            </div>
            <?php $algumCampo = false; ?>
            <?php foreach ($projeto['campos'] as $campo): ?>
                <?php
                $valor = $fichaAtual[$campo['campo']] ?? null;
                if ($valor === null || $valor === '' || $valor === false) {
                    continue;
                }
                $algumCampo = true;
                ?>
                <div>
                    <?php if ($campo['tipo'] === 'marcar'): ?>
                        <dt>Extra</dt>
                        <dd><?= esc($campo['rotulo']) ?></dd>
                    <?php elseif ($campo['tipo'] === 'linhas'): ?>
                        <dt><?= esc((string) preg_replace('/\s*\(.*\)$/', '', $campo['rotulo'])) ?></dt>
                        <dd>
                            <ul class="ficha-linhas">
                                <?php foreach (Projetos::linhas((string) $valor) as $linha): ?>
                                    <li><?= esc($linha) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </dd>
                    <?php else: ?>
                        <dt><?= esc($campo['rotulo']) ?></dt>
                        <dd><?= esc(Projetos::rotuloDoValor($projeto, $campo['campo'], $valor)) ?></dd>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </dl>

        <?php if (!$algumCampo): ?>
            <p class="lateral-vazio">
                A ficha ainda está vazia.
                <?php if ($podeGerenciar): ?>
                    <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'ficha'], 'ficha')) ?>" class="text-link">Preencher</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

    <?php endif; ?>
</article>
