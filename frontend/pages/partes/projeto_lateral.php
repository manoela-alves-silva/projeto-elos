<?php

declare(strict_types=1);

/*
 * FICHA DE UM EVENTO DE PROJETO — quadro da lateral (no lugar das datas
 * da exposição): os campos do projeto e o dia e horário. Um formulário
 * só edita tudo (ação salvar_ficha em pages/evento.php).
 *
 * Usa as variáveis da página: $projeto, $ficha, $agenda, $eventoId,
 * $podeGerenciar, $editarFicha, $formComErro, $erroForm, $conflitos.
 */

use Elos\Frontend\Projetos;

use function Elos\Frontend\avisoConflito;
use function Elos\Frontend\dataHoraBr;
use function Elos\Frontend\esc;
use function Elos\Frontend\linkExposicao;

// Após um erro, reabre com o que foi digitado.
$valoresFicha = $formComErro === 'ficha'
    ? Projetos::lerFicha($projeto, $_POST)['valores']
    : Projetos::valoresDaFicha($projeto, $ficha);
$diaForm = $formComErro === 'ficha' ? (string) ($_POST['ficha_data'] ?? '') : (string) ($agenda['abertura'] ?? '');
// Campos vazios que aparecem como "A definir" (os outros somem).
$camposEmDestaque = array_column($projeto['destaques'] ?? [], 0);
$horaForm = $formComErro === 'ficha' ? (string) ($_POST['ficha_horario'] ?? '') : substr((string) ($agenda['horario'] ?? ''), 0, 5);
?>
<article class="panel painel-ficha" id="ficha">
    <div class="panel-header">
        <div><p class="eyebrow">Ficha</p><h2><?= esc(ucfirst($projeto['artigo'] . ' ' . $projeto['singular'])) ?></h2></div>
        <?php if ($podeGerenciar && !$editarFicha): ?>
            <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'ficha'], 'ficha')) ?>" class="text-link">Editar</a>
        <?php endif; ?>
    </div>

    <?php if ($editarFicha): ?>

        <form method="post" action="<?= esc(linkExposicao($eventoId)) ?>" class="form-elos form-lateral">
            <?= \Elos\Frontend\campoCsrf() ?>
            <input type="hidden" name="acao" value="salvar_ficha">
            <?php if ($formComErro === 'ficha' && $erroForm !== ''): ?>
                <p class="form-erro" role="alert"><?= esc($erroForm) ?></p>
            <?php endif; ?>

            <?php Projetos::campos($projeto, $valoresFicha, 'f-'); ?>

            <fieldset class="campo-duplo">
                <legend>Dia e horário</legend>
                <input type="date" name="ficha_data" value="<?= esc($diaForm) ?>" aria-label="Dia">
                <span>às</span>
                <input type="time" name="ficha_horario" value="<?= esc($horaForm) ?>" aria-label="Horário">
            </fieldset>
            <?php if ($formComErro === 'ficha' && $conflitos !== []) { avisoConflito($conflitos); } ?>
            <div class="form-botoes">
                <a href="<?= esc(linkExposicao($eventoId, [], 'ficha')) ?>" class="text-link">Cancelar</a>
                <button type="submit" class="primary-button">Salvar</button>
            </div>
        </form>

    <?php else: ?>

        <dl class="datas ficha-projeto">
            <div class="data-linha">
                <dt>Quando</dt>
                <dd><?= esc(dataHoraBr($agenda['abertura'] ?? null, $agenda['horario'] ?? null, 'A definir')) ?></dd>
            </div>
            <?php foreach ($projeto['campos'] ?? [] as $campo): ?>
                <?php $valor = $ficha[$campo['campo']] ?? null; ?>
                <?php if ($valor === null || $valor === '' || $valor === false): ?>
                    <?php if (!empty($campo['obrigatorio']) || in_array($campo['campo'], $camposEmDestaque, true)): ?>
                        <div class="data-linha"><dt><?= esc($campo['rotulo']) ?></dt><dd class="a-definir">A definir</dd></div>
                    <?php endif; ?>
                    <?php continue; ?>
                <?php endif; ?>
                <?php if ($campo['tipo'] === 'linhas'): ?>
                    <div class="data-linha data-linha-lista">
                        <dt><?= esc(preg_replace('/\s*\(.*\)$/', '', $campo['rotulo'])) ?></dt>
                        <dd><ol><?php foreach (Projetos::linhas((string) $valor) as $linha): ?><li><?= esc($linha) ?></li><?php endforeach; ?></ol></dd>
                    </div>
                    <?php continue; ?>
                <?php endif; ?>
                <div class="data-linha">
                    <dt><?= esc($campo['tipo'] === 'marcar' ? 'Extra' : $campo['rotulo']) ?></dt>
                    <dd><?= esc($campo['tipo'] === 'marcar' ? $campo['rotulo'] : Projetos::rotuloDoValor($projeto, $campo['campo'], $valor)) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>

    <?php endif; ?>
</article>
