<?php

declare(strict_types=1);

/*
 * RELATÓRIO DE ATIVIDADES — versão para imprimir (ou salvar em PDF
 * pelo próprio navegador) do planejamento dos eventos da galeria:
 * exposições e eventos dos projetos (Cine Clube, Café Cultural...).
 *
 * A professora escolhe o projeto e o evento (ou todos em andamento), o
 * que incluir (tudo ou só pendências) e, se quiser, um período. Nos
 * eventos de projeto, no lugar das datas de montagem vem a ficha (o
 * filme, o autor, a programação...). Na impressão saem só o cabeçalho
 * e o conteúdo, sem menus nem botões.
 */

use Elos\Frontend\Fases;
use Elos\Frontend\Planejamento;
use Elos\Frontend\Projetos;

use function Elos\Frontend\apiLista;
use function Elos\Frontend\slugDaExposicao;
use function Elos\Frontend\idDaExposicaoNoParametro;
use function Elos\Frontend\linkExposicao;
use function Elos\Frontend\dataBr;
use function Elos\Frontend\dataValida;
use function Elos\Frontend\dataHoraBr;
use function Elos\Frontend\esc;
use function Elos\Frontend\exposicaoAtiva;
use function Elos\Frontend\intervaloBr;
use function Elos\Frontend\nomeDoResponsavel;
use function Elos\Frontend\rotuloPrioridade;
use function Elos\Frontend\rotuloStatusEvento;
use function Elos\Frontend\rotuloTipoHorario;
use function Elos\Frontend\preCarregar;
use function Elos\Frontend\usuarioLogado;

require_once __DIR__ . '/../src/Planejamento.php';

preCarregar(['/api/eventos', '/api/fichas']);
$usuario = usuarioLogado('entrar');

$todosEventos = apiLista('/api/eventos', 'eventos') ?? [];
usort($todosEventos, static fn(array $a, array $b): int => strcmp((string) $a['titulo'], (string) $b['titulo']));

$filtroEvento = idDaExposicaoNoParametro($_GET['evento'] ?? '');

// Projeto (?projeto=cine-clube): vazio = todos. Escolhido um evento,
// vale o projeto dele.
$filtroProjeto = (string) ($_GET['projeto'] ?? '');
foreach ($todosEventos as $evento) {
    if ((int) $evento['id'] === $filtroEvento) {
        $filtroProjeto = Projetos::doEvento($evento);
    }
}
if (!in_array($filtroProjeto, Projetos::chaves(), true)) {
    $filtroProjeto = '';
}
$eventos = $filtroProjeto !== '' ? Projetos::filtrar($todosEventos, $filtroProjeto) : $todosEventos;

// Ficha dos eventos de projeto (filme, autor, programação...).
$fichas = [];
foreach (apiLista('/api/fichas', 'fichas') ?? [] as $ficha) {
    $fichas[(int) $ficha['evento_id']] = is_array($ficha['dados'] ?? null) ? $ficha['dados'] : [];
}
$somentePendentes = ($_GET['incluir'] ?? 'tudo') === 'pendentes';
$de = is_string($_GET['de'] ?? null) && dataValida($_GET['de']) ? $_GET['de'] : '';
$ate = is_string($_GET['ate'] ?? null) && dataValida($_GET['ate']) ? $_GET['ate'] : '';

// Uma exposição escolhida, ou todas as que estão em andamento/planejamento.
$selecionados = array_values(array_filter(
    $eventos,
    static fn(array $e): bool => $filtroEvento !== 0 ? (int) $e['id'] === $filtroEvento : exposicaoAtiva($e)
));
$planos = Planejamento::carregarVarios($selecionados);

// Exposições na ordem do calendário (as sem data no fim), depois pelo nome.
usort($planos, static fn(Planejamento $a, Planejamento $b): int =>
    [$a->periodo()['inicio'] ?? '9999-12-31', mb_strtolower($a->titulo())]
    <=> [$b->periodo()['inicio'] ?? '9999-12-31', mb_strtolower($b->titulo())]);

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
$tituloRelatorio = match (true) {
    count($planos) === 1 => $planos[0]->titulo(),
    $filtroProjeto === Projetos::EXPOSICOES => 'Exposições em andamento',
    $filtroProjeto !== '' => Projetos::dados($filtroProjeto)['nome'] . ' — em andamento',
    default => 'Eventos da galeria em andamento',
};

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
    <link rel="stylesheet" href="../assets/css/base.css?v=2026-10-29">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=2026-10-29">
    <link rel="stylesheet" href="../assets/css/planejamento.css?v=2026-10-29">
    <link rel="stylesheet" href="../assets/css/relatorio.css?v=2026-10-29b">
    <!-- Ícone do ELOS na aba do navegador, no celular e no app instalado. -->
    <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="../assets/img/icone-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="../assets/img/icone-180.png">
    <link rel="manifest" href="../assets/elos.webmanifest">
    <meta name="theme-color" content="#123d59">
</head>
<body class="pagina-relatorio">

<!-- CONTROLES (não saem na impressão) -->
<header class="relatorio-controles">
    <a href="<?= esc($filtroEvento > 0 ? linkExposicao($filtroEvento) : ($filtroProjeto !== '' ? Projetos::dados($filtroProjeto)['lista'] : './')) ?>" class="voltar">← Voltar</a>

    <form method="get" action="relatorio" class="form-elos relatorio-filtros">
        <div class="campo">
            <label for="projeto">Projeto</label>
            <!-- Trocar o projeto limpa o evento escolhido (pode ser de outro projeto). -->
            <select id="projeto" name="projeto" onchange="this.form.evento.value = ''">
                <option value="">Todos</option>
                <?php foreach (Projetos::chaves() as $chaveProjeto): ?>
                    <option value="<?= esc($chaveProjeto) ?>" <?= $chaveProjeto === $filtroProjeto ? 'selected' : '' ?>><?= esc(Projetos::dados($chaveProjeto)['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="evento">Evento</label>
            <select id="evento" name="evento">
                <option value="">Todos em andamento</option>
                <?php foreach (Projetos::chaves() as $chaveProjeto): ?>
                    <?php $doProjeto = Projetos::filtrar($eventos, $chaveProjeto); ?>
                    <?php if ($doProjeto !== []): ?>
                        <optgroup label="<?= esc(Projetos::dados($chaveProjeto)['nome']) ?>">
                            <?php foreach ($doProjeto as $evento): ?>
                                <option value="<?= esc(slugDaExposicao((int) $evento['id'])) ?>" <?= (int) $evento['id'] === $filtroEvento ? 'selected' : '' ?>><?= esc($evento['titulo']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
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
        <p class="folha-vazio">Nenhum evento encontrado para os filtros escolhidos.</p>
    <?php endif; ?>

    <?php foreach ($planos as $indice => $plano): ?>
        <?php
        $evento = $plano->evento;
        $resumo = $plano->resumo();
        $periodo = $plano->periodo();
        $agenda = $plano->agenda;
        $projeto = Projetos::dados(Projetos::doEvento($evento));
        $comFicha = $projeto['chave'] !== Projetos::EXPOSICOES;
        $ficha = $fichas[(int) $evento['id']] ?? null;

        // Cronograma: tudo que tem data, dentro do período e do filtro.
        // Num evento de projeto, o marco "Abertura" é a "Sessão", o "Encontro"...
        $cronograma = array_values(array_filter(
            $plano->itensDatados(),
            static fn(array $i): bool => $dentroDoPeriodo($i['data'])
                && (!$somentePendentes || $pendente($i['estado']))
        ));
        if ($comFicha) {
            $cronograma = array_map(static function (array $item) use ($projeto): array {
                if ($item['origem'] === 'marco' && $item['grupo'] === 'abertura') {
                    $item['titulo'] = $projeto['momento'];
                }
                return $item;
            }, $cronograma);
        }

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

        // Agrupado por fase, na ordem do trabalho (igual à tela da exposição).
        $fases = Fases::agrupar($necessidades);
        ?>
        <section class="folha-exposicao<?= $indice > 0 ? ' nova-pagina' : '' ?>">

            <?php if (count($planos) > 1): ?>
                <h2 class="folha-exposicao-titulo">
                    <?= esc($plano->titulo()) ?>
                    <?php if ($filtroProjeto === ''): ?>
                        <small class="folha-projeto"><?= esc($evento['tipo_evento_nome'] ?? $projeto['nome']) ?></small>
                    <?php endif; ?>
                </h2>
            <?php endif; ?>

            <dl class="folha-resumo">
                <?php if ($comFicha): ?>
                    <div><dt>Dia</dt><dd><?= esc(dataHoraBr($agenda['abertura'] ?? null, $agenda['horario'] ?? null, 'A definir')) ?></dd></div>
                <?php else: ?>
                    <div><dt>Período</dt><dd><?= esc(intervaloBr($periodo['inicio'], $periodo['fim'], 'A definir')) ?></dd></div>
                <?php endif; ?>
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

            <?php if ($comFicha): ?>
                <!-- Ficha do projeto (o filme, o autor, a programação...). -->
                <h3>Ficha d<?= esc($projeto['artigo'] . ' ' . $projeto['singular']) ?></h3>
                <?php if ($ficha === null): ?>
                    <p class="folha-vazio">Ficha ainda não preenchida.</p>
                <?php else: ?>
                    <table class="folha-tabela tabela-datas">
                        <tbody>
                            <?php foreach ($projeto['campos'] as $campo): ?>
                                <?php $valor = $ficha[$campo['campo']] ?? null; ?>
                                <?php if ($valor === null || $valor === '' || $valor === false) { continue; } ?>
                                <tr>
                                    <th><?= esc(match ($campo['tipo']) {
                                        'marcar' => 'Extra',
                                        'linhas' => preg_replace('/\s*\(.*\)$/', '', $campo['rotulo']), // "Programação (uma por linha)" -> "Programação"
                                        default => $campo['rotulo'],
                                    }) ?></th>
                                    <td>
                                        <?php if ($campo['tipo'] === 'linhas'): ?>
                                            <?= implode('<br>', array_map('Elos\Frontend\esc', Projetos::linhas((string) $valor))) ?>
                                        <?php else: ?>
                                            <?= esc($campo['tipo'] === 'marcar' ? $campo['rotulo'] : Projetos::rotuloDoValor($projeto, $campo['campo'], $valor)) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php elseif ($agenda !== null): ?>
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
                            <th>Descrição</th>
                            <th class="col-data">Data</th>
                            <th>Responsável</th>
                            <th>Prioridade</th>
                            <th class="col-situacao">Situação</th>
                        </tr>
                    </thead>
                    <?php foreach ($fases as $fase): ?>
                        <tbody class="fase-grupo" style="<?= Fases::estiloCor($fase['etapa']) ?>">
                            <tr class="fase-cabecalho">
                                <th colspan="6" scope="rowgroup">
                                    <span class="fase-nome"><?= esc($fase['rotulo']) ?></span>
                                    <span class="fase-etapa"><?= esc(Fases::ROTULOS_ETAPA[$fase['etapa']] ?? '') ?></span>
                                    <span class="fase-andamento">
                                        <?php if ($somentePendentes): ?>
                                            <?= $fase['pendentes'] ?> pendente<?= $fase['pendentes'] === 1 ? '' : 's' ?>
                                        <?php else: ?>
                                            <?= $fase['feitas'] ?> de <?= $fase['total'] ?> concluída<?= $fase['total'] === 1 ? '' : 's' ?>
                                        <?php endif; ?>
                                        <?= $fase['atrasadas'] > 0 ? ' · ' . $fase['atrasadas'] . ' atrasada' . ($fase['atrasadas'] === 1 ? '' : 's') : '' ?>
                                    </span>
                                </th>
                            </tr>
                            <?php foreach ($fase['itens'] as $tarefa): ?>
                                <?php
                                $estado = Planejamento::estadoNecessidade($tarefa);
                                $categoria = trim((string) ($tarefa['categoria_nome'] ?? ''));
                                ?>
                                <tr class="estado-<?= esc($estado) ?>">
                                    <td class="col-marca"><span class="marca-caixa"><?= $estado === 'concluido' ? '✓' : '' ?></span></td>
                                    <td class="col-descricao">
                                        <?= esc($tarefa['titulo'] ?? '') ?>
                                        <?php if ($categoria !== '' && mb_strtolower($categoria) !== mb_strtolower($fase['rotulo'])): ?>
                                            <br><small class="item-categoria"><?= esc($categoria) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($tarefa['observacoes'])): ?>
                                            <br><small><?= esc($tarefa['observacoes']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-data"><?= esc(dataBr($tarefa['prazo'] ?? null, 'A definir')) ?><?= !empty($tarefa['horario']) ? '<br><small>' . esc(substr((string) $tarefa['horario'], 0, 5)) . '</small>' : '' ?></td>
                                    <td><?= esc(nomeDoResponsavel($tarefa) ?: '—') ?></td>
                                    <td><?= esc(rotuloPrioridade((string) ($tarefa['prioridade'] ?? ''))) ?></td>
                                    <td class="col-situacao"><?= esc($rotuloSituacao($estado)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php endforeach; ?>
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
