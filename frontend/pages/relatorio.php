<?php

declare(strict_types=1);

/*
 * RELATÓRIO DE ATIVIDADES — versão para imprimir (ou salvar em PDF
 * pelo próprio navegador) do planejamento das exposições.
 *
 * A professora escolhe a exposição (ou todas em andamento), o que
 * incluir (tudo ou só pendências) e, se quiser, um período. Na
 * impressão saem só o cabeçalho e o conteúdo, sem menus nem botões.
 */

use Elos\Frontend\Planejamento;

use function Elos\Frontend\apiLista;
use function Elos\Frontend\dataBr;
use function Elos\Frontend\dataHoraBr;
use function Elos\Frontend\esc;
use function Elos\Frontend\exposicaoAtiva;
use function Elos\Frontend\intervaloBr;
use function Elos\Frontend\nomeDoResponsavel;
use function Elos\Frontend\rotuloPrioridade;
use function Elos\Frontend\rotuloStatusEvento;
use function Elos\Frontend\rotuloTipoHorario;
use function Elos\Frontend\usuarioLogado;

require_once __DIR__ . '/../src/Planejamento.php';

$usuario = usuarioLogado('login.php');

$eventos = apiLista('/api/eventos', 'eventos') ?? [];
usort($eventos, static fn(array $a, array $b): int => strcmp((string) $a['titulo'], (string) $b['titulo']));

$filtroEvento = (int) ($_GET['evento'] ?? 0);
$somentePendentes = ($_GET['incluir'] ?? 'tudo') === 'pendentes';
$de = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['de'] ?? '')) ? (string) $_GET['de'] : '';
$ate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['ate'] ?? '')) ? (string) $_GET['ate'] : '';

// Uma exposição escolhida, ou todas as que estão em andamento/planejamento.
$selecionados = array_values(array_filter(
    $eventos,
    static fn(array $e): bool => $filtroEvento > 0 ? (int) $e['id'] === $filtroEvento : exposicaoAtiva($e)
));
$planos = array_map(static fn(array $e): Planejamento => Planejamento::carregar($e), $selecionados);

$dentroDoPeriodo = static fn(?string $data): bool =>
    ($de === '' || ($data !== null && $data >= $de)) && ($ate === '' || ($data !== null && $data <= $ate));

$pendente = static fn(string $estado): bool => in_array($estado, ['pendente', 'atrasado'], true);

$rotuloSituacao = static fn(string $estado): string => match ($estado) {
    'concluido' => 'Concluído',
    'cancelado' => 'Cancelado',
    'atrasado' => 'Atrasado',
    'agendado' => 'Agendado',
    default => 'Pendente',
};

$geradoEm = date('d/m/Y \à\s H:i');
$tituloRelatorio = count($planos) === 1 ? $planos[0]->titulo() : 'Exposições em andamento';

$descricaoFiltros = [];
$descricaoFiltros[] = $somentePendentes ? 'somente pendências' : 'todas as atividades';
if ($de !== '' || $ate !== '') {
    $descricaoFiltros[] = 'período: ' . ($de !== '' ? dataBr($de) : 'início') . ' a ' . ($ate !== '' ? dataBr($ate) : 'fim');
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório — <?= esc($tituloRelatorio) ?> | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/planejamento.css">
    <link rel="stylesheet" href="../assets/css/relatorio.css">
</head>
<body class="pagina-relatorio">

<!-- CONTROLES (não saem na impressão) -->
<header class="relatorio-controles">
    <a href="<?= $filtroEvento > 0 ? 'evento.php?id=' . $filtroEvento : '../index.php' ?>" class="voltar">← Voltar</a>

    <form method="get" action="relatorio.php" class="form-elos relatorio-filtros">
        <div class="campo">
            <label for="evento">Exposição</label>
            <select id="evento" name="evento">
                <option value="0">Todas em andamento</option>
                <?php foreach ($eventos as $evento): ?>
                    <option value="<?= (int) $evento['id'] ?>" <?= (int) $evento['id'] === $filtroEvento ? 'selected' : '' ?>><?= esc($evento['titulo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="incluir">Incluir</label>
            <select id="incluir" name="incluir">
                <option value="tudo">Todas as atividades</option>
                <option value="pendentes" <?= $somentePendentes ? 'selected' : '' ?>>Somente pendências</option>
            </select>
        </div>
        <div class="campo">
            <label for="de">De</label>
            <input id="de" type="date" name="de" value="<?= esc($de) ?>">
        </div>
        <div class="campo">
            <label for="ate">Até</label>
            <input id="ate" type="date" name="ate" value="<?= esc($ate) ?>">
        </div>
        <button type="submit" class="botao-atualizar">Atualizar</button>
    </form>

    <button type="button" class="primary-button" onclick="window.print()">⎙ Imprimir</button>
</header>

<!-- O DOCUMENTO -->
<main class="folha">

    <header class="folha-cabecalho">
        <div class="folha-marca">
            <strong>EL<span>O</span>S</strong>
            <small>Eventos que conectam</small>
        </div>
        <div class="folha-titulo">
            <p>Relatório de atividades</p>
            <h1><?= esc($tituloRelatorio) ?></h1>
            <p class="folha-meta">
                Gerado em <?= esc($geradoEm) ?> por <?= esc($usuario['nome']) ?> · <?= esc(implode(' · ', $descricaoFiltros)) ?>
            </p>
        </div>
    </header>

    <?php if ($planos === []): ?>
        <p class="folha-vazio">Nenhuma exposição encontrada para os filtros escolhidos.</p>
    <?php endif; ?>

    <?php foreach ($planos as $indice => $plano): ?>
        <?php
        $evento = $plano->evento;
        $resumo = $plano->resumo();
        $periodo = $plano->periodo();
        $agenda = $plano->agenda;

        // Cronograma: tudo que tem data, dentro do período e do filtro.
        $cronograma = array_values(array_filter(
            $plano->itensDatados(),
            static fn(array $i): bool => $dentroDoPeriodo($i['data'])
                && (!$somentePendentes || $pendente($i['estado']))
        ));

        // Checklist completo (inclui o que não tem data).
        $necessidades = array_values(array_filter(
            $plano->necessidades ?? [],
            static function (array $t) use ($somentePendentes, $pendente, $dentroDoPeriodo, $de, $ate): bool {
                $estado = Planejamento::estadoNecessidade($t);
                if ($estado === 'cancelado' || ($somentePendentes && !$pendente($estado))) {
                    return false;
                }

                // Com período escolhido, itens sem data continuam aparecendo
                // (ainda precisam de definição); os com data, só se couberem.
                return empty($t['prazo']) || ($de === '' && $ate === '') || $dentroDoPeriodo((string) $t['prazo']);
            }
        ));
        ?>
        <section class="folha-exposicao<?= $indice > 0 ? ' nova-pagina' : '' ?>">

            <?php if (count($planos) > 1): ?>
                <h2 class="folha-exposicao-titulo"><?= esc($plano->titulo()) ?></h2>
            <?php endif; ?>

            <dl class="folha-resumo">
                <div><dt>Período</dt><dd><?= esc(intervaloBr($periodo['inicio'], $periodo['fim'], 'A definir')) ?></dd></div>
                <div><dt>Local</dt><dd><?= esc($evento['local_nome'] ?? '—') ?></dd></div>
                <div><dt>Responsável</dt><dd><?= esc($evento['responsavel_nome'] ?? '—') ?></dd></div>
                <div><dt>Situação</dt><dd><?= esc(rotuloStatusEvento((string) ($evento['status'] ?? ''))) ?></dd></div>
                <div>
                    <dt>Andamento</dt>
                    <dd>
                        <?= $resumo['concluidas'] ?> de <?= $resumo['total'] ?> necessidades concluídas
                        (<?= $resumo['pendentes'] ?> pendente<?= $resumo['pendentes'] === 1 ? '' : 's' ?><?= $resumo['atrasadas'] > 0 ? ', ' . $resumo['atrasadas'] . ' atrasada' . ($resumo['atrasadas'] === 1 ? '' : 's') : '' ?>)
                    </dd>
                </div>
            </dl>

            <?php if ($agenda !== null): ?>
                <h3>Datas da exposição</h3>
                <table class="folha-tabela tabela-datas">
                    <tbody>
                        <tr><th>Montagem</th><td><?= esc(intervaloBr($agenda['montagem_inicio'] ?? null, $agenda['montagem_fim'] ?? null)) ?></td></tr>
                        <tr><th>Abertura</th><td><?= esc(dataHoraBr($agenda['abertura'] ?? null, $agenda['horario'] ?? null, 'Não definida')) ?></td></tr>
                        <tr><th>Em cartaz</th><td><?= esc(intervaloBr($agenda['permanencia_inicio'] ?? null, $agenda['permanencia_fim'] ?? null)) ?></td></tr>
                        <tr><th>Desmontagem</th><td><?= esc(intervaloBr($agenda['desmontagem_inicio'] ?? null, $agenda['desmontagem_fim'] ?? null)) ?></td></tr>
                        <?php if (!empty($agenda['tipo_horario'])): ?>
                            <tr><th>Funcionamento</th><td><?= esc(rotuloTipoHorario((string) $agenda['tipo_horario'])) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h3>Cronograma</h3>
            <?php if ($cronograma === []): ?>
                <p class="folha-vazio">Nenhuma atividade com data <?= $somentePendentes ? 'pendente ' : '' ?>neste recorte.</p>
            <?php else: ?>
                <table class="folha-tabela">
                    <thead>
                        <tr>
                            <th class="col-data">Data</th>
                            <th>Tipo</th>
                            <th>Atividade</th>
                            <th class="col-situacao">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cronograma as $item): ?>
                            <tr class="estado-<?= esc($item['estado']) ?>">
                                <td class="col-data"><?= esc(dataBr($item['data'])) ?><?= $item['horario'] ? '<br><small>' . esc($item['horario']) . '</small>' : '' ?></td>
                                <td><?= esc($item['categoria']) ?></td>
                                <td><?= esc($item['titulo']) ?></td>
                                <td class="col-situacao"><?= $item['estado'] === 'marco' ? '—' : esc($item['rotuloEstado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h3>Checklist de necessidades</h3>
            <?php if ($necessidades === []): ?>
                <p class="folha-vazio"><?= $somentePendentes ? 'Nenhuma pendência. ' : 'Nenhuma necessidade registrada.' ?></p>
            <?php else: ?>
                <table class="folha-tabela tabela-checklist">
                    <thead>
                        <tr>
                            <th class="col-marca" aria-label="Concluído"></th>
                            <th>Categoria</th>
                            <th>Descrição</th>
                            <th class="col-data">Data</th>
                            <th>Responsável</th>
                            <th>Prioridade</th>
                            <th class="col-situacao">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($necessidades as $tarefa): ?>
                            <?php $estado = Planejamento::estadoNecessidade($tarefa); ?>
                            <tr class="estado-<?= esc($estado) ?>">
                                <td class="col-marca"><span class="marca-caixa"><?= $estado === 'concluido' ? '✓' : '' ?></span></td>
                                <td><?= esc($tarefa['categoria_nome'] ?? '—') ?></td>
                                <td>
                                    <?= esc($tarefa['titulo'] ?? '') ?>
                                    <?php if (!empty($tarefa['observacoes'])): ?>
                                        <br><small><?= esc($tarefa['observacoes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="col-data"><?= esc(dataBr($tarefa['prazo'] ?? null, 'A definir')) ?></td>
                                <td><?= esc(nomeDoResponsavel($tarefa) ?: '—') ?></td>
                                <td><?= esc(rotuloPrioridade((string) ($tarefa['prioridade'] ?? ''))) ?></td>
                                <td class="col-situacao"><?= esc($rotuloSituacao($estado)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </section>
    <?php endforeach; ?>

    <footer class="folha-rodape">
        ELOS · Relatório gerado em <?= esc($geradoEm) ?>
    </footer>

</main>

</body>
</html>
