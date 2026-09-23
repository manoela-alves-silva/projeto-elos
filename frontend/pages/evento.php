<?php

declare(strict_types=1);

/*
 * A EXPOSIÇÃO — o lugar de trabalho da professora.
 *
 * Tudo daquela exposição está aqui: informações, datas, o checklist
 * de necessidades (adicionar, editar, marcar, remover) e, só quando
 * existirem, visitas, transportes, documentos, anexos e histórico.
 * Nada disso exige sair da página.
 */

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;
use Elos\Frontend\MenuLateral;
use Elos\Frontend\Planejamento;

use function Elos\Frontend\apiGet;
use function Elos\Frontend\apiLista;
use function Elos\Frontend\avisoConflito;
use function Elos\Frontend\conflitosNoLocal;
use function Elos\Frontend\classePrioridade;
use function Elos\Frontend\classeStatusEvento;
use function Elos\Frontend\dataBr;
use function Elos\Frontend\dataHoraBr;
use function Elos\Frontend\diasAte;
use function Elos\Frontend\distanciaRelativa;
use function Elos\Frontend\esc;
use function Elos\Frontend\flash;
use function Elos\Frontend\intervaloBr;
use function Elos\Frontend\mesAbreviado;
use function Elos\Frontend\nomeDoResponsavel;
use function Elos\Frontend\redirecionar;
use function Elos\Frontend\extraTipoResponsavel;
use function Elos\Frontend\resolverCadastrosDaExposicao;
use function Elos\Frontend\resolverCategoria;
use function Elos\Frontend\resolverCursos;
use function Elos\Frontend\resolverResponsavel;
use function Elos\Frontend\selectAberto;
use function Elos\Frontend\sugestoesDeResponsavel;
use function Elos\Frontend\rotuloPrioridade;
use function Elos\Frontend\rotuloStatusEvento;
use function Elos\Frontend\rotuloTipoHorario;
use function Elos\Frontend\usuarioLogado;

use const Elos\Frontend\PRIORIDADES;

require_once __DIR__ . '/../src/Planejamento.php';

$usuario = usuarioLogado('login.php');
$podeGerenciar = $usuario['podeGerenciar'];

const TIPOS_HORARIO = ['HORARIO_ESPECIFICO', 'DIA_TODO', 'TURNO_MANHA', 'TURNO_NOITE', 'MANHA_E_NOITE'];
const CAMPOS_NECESSIDADE = ['categoria_id', 'nova_categoria', 'titulo', 'prazo', 'horario', 'responsavel', 'prioridade', 'observacoes'];

/**
 * Lê e valida os campos de uma necessidade vindos do POST.
 *
 * @return array{valores: array<string, string>, corpo: array<string, mixed>|null, erro: string}
 */
function lerNecessidade(array $post): array
{
    $valores = [];

    foreach (CAMPOS_NECESSIDADE as $campo) {
        $valores[$campo] = trim((string) ($post[$campo] ?? ''));
    }

    $erro = match (true) {
        $valores['titulo'] === '' => 'Escreva o que precisa ser feito.',
        !in_array($valores['prioridade'], PRIORIDADES, true) => 'Escolha a prioridade.',
        $valores['prazo'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valores['prazo']) => 'Data inválida.',
        $valores['horario'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $valores['horario']) => 'Horário inválido.',
        default => '',
    };

    if ($erro !== '') {
        return ['valores' => $valores, 'corpo' => null, 'erro' => $erro];
    }

    $categoria = resolverCategoria($valores['categoria_id'], $valores['nova_categoria']);

    if ($categoria['erro'] !== '') {
        return ['valores' => $valores, 'corpo' => null, 'erro' => $categoria['erro']];
    }

    return [
        'valores' => $valores,
        'corpo' => [
            'categoria_id' => $categoria['id'],
            'titulo' => $valores['titulo'],
            'prazo' => $valores['prazo'] !== '' ? $valores['prazo'] : null,
            'horario' => $valores['horario'] !== '' ? $valores['horario'] : null,
            'prioridade' => $valores['prioridade'],
            'observacoes' => $valores['observacoes'] !== '' ? $valores['observacoes'] : null,
        ] + resolverResponsavel($valores['responsavel'], apiLista('/api/usuarios', 'usuarios') ?? []),
        'erro' => '',
    ];
}

// ---------------------------------------------------------------
// Ações (POST → redireciona de volta para a seção certa)
// ---------------------------------------------------------------

$eventoId = (int) ($_GET['id'] ?? 0);
$erroForm = '';         // mensagem do formulário que falhou
$formComErro = '';      // qual reabrir: nova | item-N | info | datas | geral
$valoresNova = array_fill_keys(CAMPOS_NECESSIDADE, '') + [];
$valoresNova['prioridade'] = 'MEDIA';
$valoresItem = [];
$conflitos = [];        // local ocupado: reabre o formulário com o aviso

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $eventoId > 0) {
    $acao = (string) ($_POST['acao'] ?? '');
    $tarefaId = (int) ($_POST['tarefa_id'] ?? 0);
    $base = 'evento.php?id=' . $eventoId;

    try {
        if ($acao === 'marcar_necessidade') {
            // Rota só de status: GESTOR marca qualquer item; COLABORADOR
            // só os atribuídos a ele (o backend garante).
            $novoStatus = ($_POST['novo_status'] ?? '') === 'CONCLUIDA' ? 'CONCLUIDA' : 'PENDENTE';
            Api::put('/api/eventos/' . $eventoId . '/tarefas/' . $tarefaId . '/status', ['status' => $novoStatus]);
            flash('evento', $novoStatus === 'CONCLUIDA' ? 'Marcado como concluído.' : 'Voltou para pendente.');
            redirecionar($base . '#item-' . $tarefaId);
        }

        if (!$podeGerenciar) {
            throw new ApiException('Você não tem permissão para alterar esta exposição.', 403);
        }

        switch ($acao) {
            case 'adicionar_necessidade':
                $lida = lerNecessidade($_POST);
                $valoresNova = $lida['valores'];

                if ($lida['corpo'] === null) {
                    $erroForm = $lida['erro'];
                    $formComErro = 'nova';
                    break;
                }

                // Toda necessidade nasce PENDENTE; etapa não se aplica.
                $resposta = Api::post(
                    '/api/eventos/' . $eventoId . '/tarefas',
                    $lida['corpo'] + ['etapa_id' => null, 'descricao' => null, 'status' => 'PENDENTE']
                );
                flash('evento', 'Adicionado: ' . $lida['valores']['titulo'] . '.');
                redirecionar($base . '#item-' . (int) ($resposta['tarefa']['id'] ?? 0));

            case 'editar_necessidade':
                $lida = lerNecessidade($_POST);
                $valoresItem = $lida['valores'];

                if ($lida['corpo'] === null) {
                    $erroForm = $lida['erro'];
                    $formComErro = 'item-' . $tarefaId;
                    break;
                }

                // Sem etapa_id/status no corpo: o backend mantém os atuais.
                Api::put('/api/eventos/' . $eventoId . '/tarefas/' . $tarefaId, $lida['corpo']);
                flash('evento', 'Alterações salvas.');
                redirecionar($base . '#item-' . $tarefaId);

            case 'remover_necessidade':
                Api::delete('/api/eventos/' . $eventoId . '/tarefas/' . $tarefaId);
                flash('evento', 'Removido do planejamento.');
                redirecionar($base . '#necessidades');

            case 'salvar_datas':
                $corpo = [];

                foreach (array_keys(Planejamento::CAMPOS_AGENDA) as $campo) {
                    $valor = trim((string) ($_POST[$campo] ?? ''));
                    $corpo[$campo] = $valor !== '' ? $valor : null;
                }

                $horario = trim((string) ($_POST['horario'] ?? ''));
                $corpo['horario'] = $horario !== '' ? $horario : null;
                $tipoHorario = (string) ($_POST['tipo_horario'] ?? '');
                $corpo['tipo_horario'] = in_array($tipoHorario, TIPOS_HORARIO, true) ? $tipoHorario : null;
                $observacoes = trim((string) ($_POST['observacoes_agenda'] ?? ''));
                $corpo['observacoes'] = $observacoes !== '' ? $observacoes : null;

                if (($_POST['confirmar_conflito'] ?? '') !== '1') {
                    $atual = Api::get('/api/eventos/' . $eventoId)['evento'] ?? [];

                    if (($atual['status'] ?? '') !== 'CANCELADO') {
                        $conflitos = conflitosNoLocal((int) ($atual['local_id'] ?? 0), $corpo, $eventoId);
                    }

                    if ($conflitos !== []) {
                        $formComErro = 'datas';
                        break;
                    }
                }

                // POST cria, PUT altera; a agenda é criada na primeira vez.
                $agendaExiste = true;

                try {
                    Api::get('/api/eventos/' . $eventoId . '/agenda');
                } catch (ApiException $e) {
                    if ($e->statusCode !== 404) {
                        throw $e;
                    }
                    $agendaExiste = false;
                }

                $agendaExiste
                    ? Api::put('/api/eventos/' . $eventoId . '/agenda', $corpo)
                    : Api::post('/api/eventos/' . $eventoId . '/agenda', $corpo);

                flash('evento', 'Datas salvas. O calendário já acompanha.');
                redirecionar($base . '#datas');

            case 'salvar_informacoes':
                $titulo = trim((string) ($_POST['titulo'] ?? ''));

                if ($titulo === '') {
                    $erroForm = 'O nome da exposição é obrigatório.';
                    $formComErro = 'info';
                    break;
                }

                // Tipo, responsável e local: escolhidos ou escritos como novos.
                $cadastros = resolverCadastrosDaExposicao($_POST);

                if ($cadastros['erro'] !== '') {
                    $erroForm = $cadastros['erro'];
                    $formComErro = 'info';
                    break;
                }

                $descricao = trim((string) ($_POST['descricao'] ?? ''));
                $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

                // O andamento vem das datas; aqui só se cancela ou reativa.
                $cancelada = ($_POST['cancelada'] ?? '') === '1';

                // Mudou de local, ou voltou a valer: o novo local está livre?
                if (!$cancelada && ($_POST['confirmar_conflito'] ?? '') !== '1') {
                    $atual = Api::get('/api/eventos/' . $eventoId)['evento'] ?? [];
                    $novoLocal = (int) ($cadastros['ids']['local_id'] ?? 0);

                    if ($novoLocal !== (int) ($atual['local_id'] ?? 0) || ($atual['status'] ?? '') === 'CANCELADO') {
                        $agendaAtual = (array) (apiGet('/api/eventos/' . $eventoId . '/agenda')['agenda'] ?? []);
                        $conflitos = conflitosNoLocal($novoLocal, $agendaAtual, $eventoId);
                    }

                    if ($conflitos !== []) {
                        $formComErro = 'info';
                        break;
                    }
                }

                Api::put('/api/eventos/' . $eventoId, $cadastros['ids'] + [
                    'titulo' => $titulo,
                    'status' => $cancelada ? 'CANCELADO' : 'PLANEJAMENTO',
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'observacoes' => $observacoes !== '' ? $observacoes : null,
                ]);

                // Cursos marcados + escritos; aplica só a diferença.
                $cursosEscolhidos = resolverCursos((array) ($_POST['cursos'] ?? []), (string) ($_POST['cursos_outros'] ?? ''));
                $antes = array_map(
                    static fn(array $c): int => (int) $c['id'],
                    apiLista('/api/eventos/' . $eventoId . '/cursos', 'cursos') ?? []
                );
                $depois = $cursosEscolhidos['ids'];

                foreach (array_diff($depois, $antes) as $cursoId) {
                    Api::post('/api/eventos/' . $eventoId . '/cursos', ['curso_id' => $cursoId]);
                }

                foreach (array_diff($antes, $depois) as $cursoId) {
                    Api::delete('/api/eventos/' . $eventoId . '/cursos/' . $cursoId);
                }

                flash('evento', 'Informações da exposição salvas.'
                    . ($cursosEscolhidos['falhas'] !== [] ? ' Não foi possível adicionar: ' . implode(', ', $cursosEscolhidos['falhas']) . '.' : ''));
                redirecionar($base);

            default:
                $erroForm = 'Ação inválida.';
                $formComErro = 'geral';
        }
    } catch (ApiException $e) {
        $erroForm = $e->getMessage();
        $formComErro = match ($acao) {
            'adicionar_necessidade' => 'nova',
            'editar_necessidade' => 'item-' . $tarefaId,
            'salvar_datas' => 'datas',
            'salvar_informacoes' => 'info',
            default => 'geral',
        };

        if ($acao === 'adicionar_necessidade') {
            foreach (CAMPOS_NECESSIDADE as $campo) {
                $valoresNova[$campo] = trim((string) ($_POST[$campo] ?? $valoresNova[$campo]));
            }
        }

        if ($acao === 'editar_necessidade') {
            $valoresItem = lerNecessidade($_POST)['valores'];
        }
    }
}

// ---------------------------------------------------------------
// Dados
// ---------------------------------------------------------------

$evento = null;
$erroEvento = '';

if ($eventoId <= 0) {
    $erroEvento = 'Nenhuma exposição foi informada.';
} else {
    try {
        $dadosEvento = Api::get('/api/eventos/' . $eventoId);
        $evento = is_array($dadosEvento['evento'] ?? null) ? $dadosEvento['evento'] : null;
        $erroEvento = $evento === null ? 'Exposição não encontrada.' : '';
    } catch (ApiException $e) {
        $erroEvento = match ($e->statusCode) {
            404 => 'Exposição não encontrada.',
            0 => 'Não foi possível falar com o servidor do ELOS.',
            default => $e->getMessage(),
        };
    }
}

$mensagem = flash('evento');

if ($evento !== null) {
    $plano = Planejamento::carregar($evento);
    $agenda = $plano->agenda;
    $resumo = $plano->resumo();
    $periodo = $plano->periodo();
    $cursosDoEvento = apiLista('/api/eventos/' . $eventoId . '/cursos', 'cursos') ?? [];
    $anexos = apiLista('/api/eventos/' . $eventoId . '/anexos', 'anexos') ?? [];
    $historico = apiLista('/api/eventos/' . $eventoId . '/historico', 'historico') ?? [];
    $proximas = $plano->proximasAtividades(6);

    // Checklist dividido pelo que importa agora.
    $grupos = ['atrasado' => [], 'semana' => [], 'depois' => [], 'semData' => [], 'feito' => []];

    foreach ($plano->necessidades ?? [] as $tarefa) {
        $estado = Planejamento::estadoNecessidade($tarefa);
        $grupo = match (true) {
            in_array($estado, ['concluido', 'cancelado'], true) => 'feito',
            $estado === 'atrasado' => 'atrasado',
            empty($tarefa['prazo']) => 'semData',
            diasAte((string) $tarefa['prazo']) <= 7 => 'semana',
            default => 'depois',
        };
        $grupos[$grupo][] = $tarefa;
    }

    $titulosGrupos = [
        'atrasado' => 'Atrasadas',
        'semana' => 'Nos próximos 7 dias',
        'depois' => 'Mais adiante',
        'semData' => 'Sem data definida',
    ];

    // Listas dos formulários (só para quem pode editar).
    $categorias = $usuarios = $tipos = $responsaveis = $locais = $cursos = [];

    if ($podeGerenciar) {
        $categorias = array_values(array_filter(
            apiLista('/api/categorias-tarefa', 'categorias_tarefa') ?? [],
            static fn(array $c): bool => (int) ($c['ativa'] ?? 1) === 1
        ));
        $usuarios = apiLista('/api/usuarios', 'usuarios') ?? [];
        $tipos = apiLista('/api/tipos-evento', 'tipos_evento') ?? [];
        $responsaveis = apiLista('/api/responsaveis', 'responsaveis') ?? [];
        $locais = apiLista('/api/locais', 'locais') ?? [];
        $cursos = apiLista('/api/cursos', 'cursos') ?? [];

        // "Outro" já vem escolhido: é o ponto de partida mais neutro.
        if ($valoresNova['categoria_id'] === '') {
            foreach ($categorias as $categoria) {
                if (mb_strtolower((string) $categoria['nome']) === 'outro') {
                    $valoresNova['categoria_id'] = (string) $categoria['id'];
                }
            }
        }
    }

    $editarInfo = $podeGerenciar && (($_GET['editar'] ?? '') === 'info' || $formComErro === 'info');
    $editarDatas = $podeGerenciar && (($_GET['editar'] ?? '') === 'datas' || $formComErro === 'datas');
}

/**
 * Campos de uma necessidade (usados em "adicionar" e em "editar").
 *
 * @param array<string, string> $v
 */
function camposNecessidade(string $prefixo, array $v, array $categorias, array $usuarios): void
{
    ?>
    <div class="necessidade-campos">
        <div class="campo campo-categoria">
            <label for="<?= $prefixo ?>categoria">Categoria</label>
            <select id="<?= $prefixo ?>categoria" name="categoria_id" class="js-lista-aberta" required>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= (int) $categoria['id'] ?>" <?= (string) $categoria['id'] === $v['categoria_id'] ? 'selected' : '' ?>>
                        <?= esc($categoria['nome']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="__nova__" <?= $v['categoria_id'] === '__nova__' ? 'selected' : '' ?>>+ Nova categoria…</option>
            </select>
            <div class="novo-item js-novo-item">
                <input
                    type="text"
                    name="nova_categoria"
                    maxlength="100"
                    placeholder="Ex.: Cenografia"
                    value="<?= esc($v['nova_categoria']) ?>"
                    aria-label="Nome da nova categoria"
                    data-obrigatorio
                >
            </div>
        </div>

        <div class="campo campo-titulo">
            <label for="<?= $prefixo ?>titulo">O que precisa ser feito</label>
            <input
                id="<?= $prefixo ?>titulo"
                type="text"
                name="titulo"
                maxlength="200"
                required
                placeholder="Ex.: 3 projetores e 2 extensões"
                value="<?= esc($v['titulo']) ?>"
            >
        </div>

        <div class="campo campo-curto">
            <label for="<?= $prefixo ?>prazo">Data</label>
            <input id="<?= $prefixo ?>prazo" type="date" name="prazo" value="<?= esc($v['prazo']) ?>">
        </div>

        <div class="campo campo-curto">
            <label for="<?= $prefixo ?>horario">Horário <span>(opcional)</span></label>
            <input id="<?= $prefixo ?>horario" type="time" name="horario" value="<?= esc($v['horario']) ?>">
        </div>

        <div class="campo">
            <label for="<?= $prefixo ?>responsavel">Responsável</label>
            <input
                id="<?= $prefixo ?>responsavel"
                type="text"
                name="responsavel"
                maxlength="150"
                list="sugestoes-responsavel"
                autocomplete="off"
                placeholder="Nome de quem vai cuidar"
                value="<?= esc($v['responsavel']) ?>"
            >
        </div>

        <div class="campo">
            <label for="<?= $prefixo ?>prioridade">Prioridade</label>
            <select id="<?= $prefixo ?>prioridade" name="prioridade">
                <?php foreach (PRIORIDADES as $prioridade): ?>
                    <option value="<?= $prioridade ?>" <?= $prioridade === $v['prioridade'] ? 'selected' : '' ?>>
                        <?= esc(rotuloPrioridade($prioridade)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo campo-largo">
            <label for="<?= $prefixo ?>obs">Observações <span>(opcional)</span></label>
            <input
                id="<?= $prefixo ?>obs"
                type="text"
                name="observacoes"
                placeholder="Fornecedor, quantidade, origem e destino, nº de pessoas…"
                value="<?= esc($v['observacoes']) ?>"
            >
        </div>
    </div>
    <?php
}

/** Uma linha do checklist. */
function itemChecklist(array $tarefa, int $eventoId, bool $podeGerenciar, int $usuarioId, string $formComErro, array $valoresItem, string $erroForm, array $categorias, array $usuarios): void
{
    $id = (int) ($tarefa['id'] ?? 0);
    $estado = Planejamento::estadoNecessidade($tarefa);
    $concluida = $estado === 'concluido';
    $cancelada = $estado === 'cancelado';
    $podeMarcar = !$cancelada && ($podeGerenciar || (int) ($tarefa['usuario_responsavel_id'] ?? 0) === $usuarioId);
    $editando = $formComErro === 'item-' . $id;
    $valores = $editando ? $valoresItem : [
        'categoria_id' => (string) ($tarefa['categoria_id'] ?? ''),
        'nova_categoria' => '',
        'titulo' => (string) ($tarefa['titulo'] ?? ''),
        'prazo' => (string) ($tarefa['prazo'] ?? ''),
        'horario' => substr((string) ($tarefa['horario'] ?? ''), 0, 5),
        'responsavel' => nomeDoResponsavel($tarefa),
        'prioridade' => (string) ($tarefa['prioridade'] ?? 'MEDIA'),
        'observacoes' => (string) ($tarefa['observacoes'] ?? ''),
    ];
    ?>
    <li class="item estado-<?= $estado ?>" id="item-<?= $id ?>">

        <form method="post" action="evento.php?id=<?= $eventoId ?>" class="item-check">
            <?= \Elos\Frontend\campoCsrf() ?>
            <input type="hidden" name="acao" value="marcar_necessidade">
            <input type="hidden" name="tarefa_id" value="<?= $id ?>">
            <input type="hidden" name="novo_status" value="<?= $concluida ? 'PENDENTE' : 'CONCLUIDA' ?>">
            <button
                type="submit"
                class="check<?= $concluida ? ' marcado' : '' ?>"
                <?= $podeMarcar ? '' : 'disabled' ?>
                aria-pressed="<?= $concluida ? 'true' : 'false' ?>"
                title="<?= esc($podeMarcar ? ($concluida ? 'Desmarcar (voltar a pendente)' : 'Marcar como concluído') : ($cancelada ? 'Cancelado' : 'Só o responsável ou um gestor pode marcar')) ?>"
            >
                <span class="check-caixa" aria-hidden="true"><?= $concluida ? '✓' : '' ?></span>
                <span class="sr-only"><?= $concluida ? 'Concluído — desmarcar' : 'Pendente — marcar como concluído' ?></span>
            </button>
        </form>

        <div class="item-corpo">
            <p class="item-titulo">
                <span class="categoria"><?= esc($tarefa['categoria_nome'] ?? 'Sem categoria') ?></span>
                <strong><?= esc($tarefa['titulo'] ?? '') ?></strong>
            </p>

            <p class="item-meta">
                <?php if (!empty($tarefa['prazo'])): ?>
                    <span class="item-data<?= $estado === 'atrasado' ? ' atrasada' : '' ?>">
                        <?= esc(dataHoraBr($tarefa['prazo'], $tarefa['horario'] ?? null)) ?><?php if (!$concluida && !$cancelada): ?> · <?= esc(distanciaRelativa((string) $tarefa['prazo'])) ?><?php endif; ?>
                    </span>
                <?php else: ?>
                    <span class="item-data sem">Sem data</span>
                <?php endif; ?>
                <span><?= esc(nomeDoResponsavel($tarefa) ?: 'Sem responsável') ?></span>
                <strong class="prioridade <?= classePrioridade((string) ($tarefa['prioridade'] ?? '')) ?>">
                    <?= esc(rotuloPrioridade((string) ($tarefa['prioridade'] ?? ''))) ?>
                </strong>
                <?php if ($cancelada): ?>
                    <span class="estado-tag cancelado">Cancelado</span>
                <?php elseif (($tarefa['status'] ?? '') === 'EM_ANDAMENTO'): ?>
                    <span class="estado-tag agendado">Em andamento</span>
                <?php elseif (($tarefa['status'] ?? '') === 'BLOQUEADA'): ?>
                    <span class="estado-tag atrasado">Bloqueada</span>
                <?php endif; ?>
            </p>

            <?php $observacao = trim(($tarefa['descricao'] ?? '') . ' ' . ($tarefa['observacoes'] ?? '')); ?>
            <?php if ($observacao !== ''): ?>
                <p class="item-obs"><?= esc($observacao) ?></p>
            <?php endif; ?>

            <?php if ($podeGerenciar): ?>
                <div class="item-acoes">
                    <details class="acao-editar" <?= $editando ? 'open' : '' ?>>
                        <summary>Editar</summary>
                        <form method="post" action="evento.php?id=<?= $eventoId ?>" class="form-elos form-item">
                            <?= \Elos\Frontend\campoCsrf() ?>
                            <input type="hidden" name="acao" value="editar_necessidade">
                            <input type="hidden" name="tarefa_id" value="<?= $id ?>">
                            <?php if ($editando && $erroForm !== ''): ?>
                                <p class="form-erro" role="alert"><?= esc($erroForm) ?></p>
                            <?php endif; ?>
                            <?php camposNecessidade('i' . $id . '-', $valores, $categorias, $usuarios); ?>
                            <div class="form-botoes">
                                <button type="submit" class="primary-button">Salvar</button>
                            </div>
                        </form>
                    </details>

                    <details class="acao-remover">
                        <summary>Remover</summary>
                        <form method="post" action="evento.php?id=<?= $eventoId ?>" class="confirmar-remocao">
                            <?= \Elos\Frontend\campoCsrf() ?>
                            <input type="hidden" name="acao" value="remover_necessidade">
                            <input type="hidden" name="tarefa_id" value="<?= $id ?>">
                            <span>Remover “<?= esc($tarefa['titulo'] ?? '') ?>” do planejamento?</span>
                            <button type="submit" class="botao-perigo">Sim, remover</button>
                        </form>
                    </details>
                </div>
            <?php endif; ?>
        </div>
    </li>
    <?php
}

$tituloPagina = $evento !== null ? (string) ($evento['titulo'] ?? 'Exposição') : 'Exposição';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($tituloPagina) ?> | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/planejamento.css">
    <link rel="stylesheet" href="../assets/css/evento.css">
</head>
<body>

<div class="app-shell">

    <?php MenuLateral::renderizar('eventos', false, $usuario['nome'], $usuario['primeiroNome'], $usuario['perfil']); ?>

    <main class="main-content">

        <header class="topbar">
            <a href="eventos.php" class="voltar">← Exposições</a>
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

        <?php if ($evento === null): ?>

            <article class="panel">
                <div class="empty-state large">
                    <div class="empty-icon">▣</div>
                    <h2><?= esc($erroEvento) ?></h2>
                    <p>Volte para a lista e escolha uma exposição.</p>
                    <a href="eventos.php" class="primary-button">Ver exposições</a>
                </div>
            </article>

        <?php else: ?>

            <?php if ($mensagem !== ''): ?>
                <div class="aviso aviso-ok" role="status">✓ <?= esc($mensagem) ?></div>
            <?php endif; ?>

            <?php if ($erroForm !== '' && $formComErro === 'geral'): ?>
                <div class="aviso aviso-erro" role="alert"><?= esc($erroForm) ?></div>
            <?php endif; ?>

            <!-- CABEÇALHO DA EXPOSIÇÃO -->
            <section class="expo-cabecalho">

                <div class="expo-identidade">
                    <p class="eyebrow">
                        <?= esc($evento['tipo_evento_nome'] ?? 'Exposição') ?>
                        <span class="status-badge <?= classeStatusEvento((string) ($evento['status'] ?? '')) ?>">
                            <?= esc(rotuloStatusEvento((string) ($evento['status'] ?? ''))) ?>
                        </span>
                    </p>

                    <h1><?= esc($tituloPagina) ?></h1>

                    <ul class="expo-fatos">
                        <li><span aria-hidden="true">◷</span> <?= esc(intervaloBr($periodo['inicio'], $periodo['fim'], 'Datas a definir')) ?></li>
                        <?php if (!empty($evento['local_nome'])): ?>
                            <li><span aria-hidden="true">⌖</span> <?= esc($evento['local_nome']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($evento['responsavel_nome'])): ?>
                            <li><span aria-hidden="true">◎</span> <?= esc($evento['responsavel_nome']) ?></li>
                        <?php endif; ?>
                    </ul>

                    <div class="acoes-cabecalho">
                        <a href="relatorio.php?evento=<?= $eventoId ?>" class="botao-secundario">⎙ Relatório para imprimir</a>
                    </div>
                </div>

                <div class="expo-progresso">
                    <?php if ($resumo['total'] > 0): ?>
                        <p class="progresso-numero">
                            <strong><?= $resumo['concluidas'] ?></strong> de <?= $resumo['total'] ?> resolvidas
                        </p>
                        <div class="progresso" role="progressbar" aria-label="Necessidades resolvidas" aria-valuenow="<?= $resumo['percentual'] ?>" aria-valuemin="0" aria-valuemax="100">
                            <span style="width: <?= $resumo['percentual'] ?>%"></span>
                        </div>
                        <p class="progresso-legenda">
                            <?= $resumo['pendentes'] ?> pendente<?= $resumo['pendentes'] === 1 ? '' : 's' ?>
                            <?php if ($resumo['atrasadas'] > 0): ?>
                                · <span class="texto-atrasado"><?= $resumo['atrasadas'] ?> atrasada<?= $resumo['atrasadas'] === 1 ? '' : 's' ?></span>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="progresso-numero">Nada no planejamento ainda</p>
                        <p class="progresso-legenda">Comece dizendo o que esta exposição precisa.</p>
                    <?php endif; ?>
                </div>

            </section>

            <div class="expo-grade">

                <!-- COLUNA PRINCIPAL: O QUE ESTA EXPOSIÇÃO PRECISA -->
                <div class="expo-principal">

                    <article class="panel" id="necessidades">

                        <div class="panel-header">
                            <div>
                                <p class="eyebrow">Checklist</p>
                                <h2>O que esta exposição precisa</h2>
                            </div>
                        </div>

                        <?php if ($podeGerenciar): ?>
                            <details class="adicionar" <?= $formComErro === 'nova' || ($plano->necessidades ?? []) === [] ? 'open' : '' ?>>
                                <summary class="primary-button"><span>+</span> Adicionar necessidade</summary>

                                <form method="post" action="evento.php?id=<?= $eventoId ?>" class="form-elos form-adicionar">
                                    <?= \Elos\Frontend\campoCsrf() ?>
                                    <input type="hidden" name="acao" value="adicionar_necessidade">
                                    <?php if ($formComErro === 'nova' && $erroForm !== ''): ?>
                                        <p class="form-erro" role="alert"><?= esc($erroForm) ?></p>
                                    <?php endif; ?>
                                    <?php camposNecessidade('n-', $valoresNova, $categorias, $usuarios); ?>
                                    <div class="form-botoes">
                                        <p class="dica">O item é registrado como pendente. Itens com data aparecem automaticamente no calendário e no Início.</p>
                                        <button type="submit" class="primary-button">Adicionar</button>
                                    </div>
                                </form>
                            </details>
                        <?php endif; ?>

                        <?php if ($plano->necessidades === null): ?>

                            <div class="empty-state"><p>Não foi possível carregar o checklist agora.</p></div>

                        <?php elseif ($plano->necessidades === []): ?>

                            <div class="empty-state">
                                <div class="empty-icon">✓</div>
                                <p>
                                    Nenhuma necessidade registrada.
                                        <?= $podeGerenciar ? 'Registre aqui os recursos, serviços e providências necessários para a realização da exposição.' : '' ?>
                                </p>
                            </div>

                        <?php else: ?>

                            <?php foreach ($titulosGrupos as $chave => $titulo): ?>
                                <?php if ($grupos[$chave] !== []): ?>
                                    <h3 class="grupo-titulo grupo-<?= $chave ?>"><?= esc($titulo) ?> <span><?= count($grupos[$chave]) ?></span></h3>
                                    <ul class="checklist">
                                        <?php foreach ($grupos[$chave] as $tarefa): ?>
                                            <?php itemChecklist($tarefa, $eventoId, $podeGerenciar, $usuario['id'], $formComErro, $valoresItem, $erroForm, $categorias, $usuarios); ?>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if ($grupos['feito'] !== []): ?>
                                <?php
                                $abrirFeito = false;
                                foreach ($grupos['feito'] as $tarefa) {
                                    $abrirFeito = $abrirFeito || $formComErro === 'item-' . (int) $tarefa['id'];
                                }
                                ?>
                                <details class="grupo-feito" <?= $abrirFeito ? 'open' : '' ?>>
                                    <summary class="grupo-titulo">Resolvidas <span><?= count($grupos['feito']) ?></span></summary>
                                    <ul class="checklist">
                                        <?php foreach ($grupos['feito'] as $tarefa): ?>
                                            <?php itemChecklist($tarefa, $eventoId, $podeGerenciar, $usuario['id'], $formComErro, $valoresItem, $erroForm, $categorias, $usuarios); ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>

                        <?php endif; ?>

                    </article>

                    <!-- SÓ APARECE O QUE EXISTE -->

                    <?php if ($anexos !== []): ?>
                        <article class="panel" id="anexos">
                            <div class="panel-header">
                                <div><p class="eyebrow">Arquivos</p><h2>Anexos</h2></div>
                            </div>
                            <ul class="lista-simples">
                                <?php foreach ($anexos as $anexo): ?>
                                    <li>
                                        <strong><?= esc($anexo['nome_original'] ?? $anexo['nome'] ?? 'Arquivo') ?></strong>
                                        <span><?= esc($anexo['tipo'] ?? '') ?></span>
                                        <span><?= esc(dataBr($anexo['created_at'] ?? null)) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endif; ?>

                    <?php if ($historico !== []): ?>
                        <details class="panel historico" id="historico">
                            <summary class="panel-header">
                                <div><p class="eyebrow">Registros</p><h2>Histórico</h2></div>
                                <span class="text-link">Mostrar</span>
                            </summary>
                            <ul class="lista-simples">
                                <?php foreach ($historico as $registro): ?>
                                    <li>
                                        <strong><?= esc($registro['acao'] ?? '') ?></strong>
                                        <?php if (($registro['descricao'] ?? '') !== ''): ?>
                                            <span class="historico-detalhe"><?= esc($registro['descricao']) ?></span>
                                        <?php endif; ?>
                                        <span><?= esc(($registro['usuario_nome'] ?? '') !== '' ? $registro['usuario_nome'] : 'Sistema') ?> · <?= esc(dataHoraBr($registro['created_at'] ?? null)) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>

                </div>

                <!-- LATERAL: DATAS, PRÓXIMOS PASSOS, SOBRE -->
                <aside class="expo-lateral">

                    <article class="panel" id="datas">
                        <div class="panel-header">
                            <div><p class="eyebrow">Agenda</p><h2>Datas da exposição</h2></div>
                            <?php if ($podeGerenciar && !$editarDatas): ?>
                                <a href="evento.php?id=<?= $eventoId ?>&editar=datas#datas" class="text-link">Editar</a>
                            <?php endif; ?>
                        </div>

                        <?php if ($editarDatas): ?>

                            <form method="post" action="evento.php?id=<?= $eventoId ?>" class="form-elos form-lateral">
                                <?= \Elos\Frontend\campoCsrf() ?>
                                <input type="hidden" name="acao" value="salvar_datas">
                                <?php if ($formComErro === 'datas' && $erroForm !== ''): ?>
                                    <p class="form-erro" role="alert"><?= esc($erroForm) ?></p>
                                <?php endif; ?>
                                <?php
                                // Após um aviso ou erro, reabre com o que foi digitado.
                                $datasForm = $formComErro === 'datas' ? $_POST + ['observacoes' => $_POST['observacoes_agenda'] ?? ''] : ($agenda ?? []);
                                $linhasDatas = [
                                    'Montagem' => ['montagem_inicio', 'montagem_fim'],
                                    'Em cartaz' => ['permanencia_inicio', 'permanencia_fim'],
                                    'Desmontagem' => ['desmontagem_inicio', 'desmontagem_fim'],
                                ];
                                ?>
                                <?php foreach ($linhasDatas as $rotulo => [$inicio, $fim]): ?>
                                    <fieldset class="campo-duplo">
                                        <legend><?= esc($rotulo) ?></legend>
                                        <input type="date" name="<?= $inicio ?>" value="<?= esc($datasForm[$inicio] ?? '') ?>" aria-label="<?= esc($rotulo) ?> — início">
                                        <span>até</span>
                                        <input type="date" name="<?= $fim ?>" value="<?= esc($datasForm[$fim] ?? '') ?>" aria-label="<?= esc($rotulo) ?> — fim">
                                    </fieldset>
                                <?php endforeach; ?>
                                <fieldset class="campo-duplo">
                                    <legend>Abertura</legend>
                                    <input type="date" name="abertura" value="<?= esc($datasForm['abertura'] ?? '') ?>" aria-label="Data da abertura">
                                    <span>às</span>
                                    <input type="time" name="horario" value="<?= esc(substr((string) ($datasForm['horario'] ?? ''), 0, 5)) ?>" aria-label="Horário da abertura">
                                </fieldset>
                                <div class="campo">
                                    <label for="tipo_horario">Funcionamento</label>
                                    <select id="tipo_horario" name="tipo_horario">
                                        <option value="">Não definido</option>
                                        <?php foreach (TIPOS_HORARIO as $tipo): ?>
                                            <option value="<?= $tipo ?>" <?= $tipo === ($datasForm['tipo_horario'] ?? '') ? 'selected' : '' ?>><?= esc(rotuloTipoHorario($tipo)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="campo">
                                    <label for="observacoes_agenda">Observações</label>
                                    <input id="observacoes_agenda" type="text" name="observacoes_agenda" value="<?= esc($datasForm['observacoes'] ?? '') ?>">
                                </div>
                                <?php if ($formComErro === 'datas' && $conflitos !== []) { avisoConflito($conflitos); } ?>
                                <div class="form-botoes">
                                    <a href="evento.php?id=<?= $eventoId ?>#datas" class="text-link">Cancelar</a>
                                    <button type="submit" class="primary-button">Salvar datas</button>
                                </div>
                            </form>

                        <?php elseif ($plano->agendaIndisponivel): ?>

                            <p class="lateral-vazio">Não foi possível carregar as datas agora.</p>

                        <?php elseif ($agenda === null): ?>

                            <p class="lateral-vazio">
                                Nenhuma data definida.
                                <?php if ($podeGerenciar): ?>
                                    <a href="evento.php?id=<?= $eventoId ?>&editar=datas#datas" class="text-link">Definir datas</a>
                                <?php endif; ?>
                            </p>

                        <?php else: ?>

                            <dl class="datas">
                                <div class="data-linha montagem">
                                    <dt>Montagem</dt>
                                    <dd><?= esc(intervaloBr($agenda['montagem_inicio'] ?? null, $agenda['montagem_fim'] ?? null)) ?></dd>
                                </div>
                                <div class="data-linha abertura">
                                    <dt>Abertura</dt>
                                    <dd><?= esc(dataHoraBr($agenda['abertura'] ?? null, $agenda['horario'] ?? null, 'Não definida')) ?></dd>
                                </div>
                                <div class="data-linha permanencia">
                                    <dt>Em cartaz</dt>
                                    <dd><?= esc(intervaloBr($agenda['permanencia_inicio'] ?? null, $agenda['permanencia_fim'] ?? null)) ?></dd>
                                </div>
                                <div class="data-linha desmontagem">
                                    <dt>Desmontagem</dt>
                                    <dd><?= esc(intervaloBr($agenda['desmontagem_inicio'] ?? null, $agenda['desmontagem_fim'] ?? null)) ?></dd>
                                </div>
                                <?php if (!empty($agenda['tipo_horario'])): ?>
                                    <div class="data-linha">
                                        <dt>Funcionamento</dt>
                                        <dd><?= esc(rotuloTipoHorario((string) $agenda['tipo_horario'])) ?></dd>
                                    </div>
                                <?php endif; ?>
                            </dl>

                        <?php endif; ?>
                    </article>

                    <article class="panel">
                        <?php
                        $mesCalendario = $periodo['inicio'] ?? ($proximas[0]['data'] ?? null);
                        $linkCalendario = 'agenda.php?evento=' . $eventoId
                            . ($mesCalendario ? '&ano=' . substr((string) $mesCalendario, 0, 4) . '&mes=' . (int) substr((string) $mesCalendario, 5, 2) : '');
                        ?>
                        <div class="panel-header">
                            <div><p class="eyebrow">Acompanhamento</p><h2>Próximos passos</h2></div>
                            <a href="<?= esc($linkCalendario) ?>" class="text-link">Calendário →</a>
                        </div>

                        <?php if ($proximas === []): ?>
                            <p class="lateral-vazio">Nada com data pela frente.</p>
                        <?php else: ?>
                            <ol class="lista-datas">
                                <?php foreach ($proximas as $item): ?>
                                    <li class="estado-<?= esc($item['estado']) ?> grupo-<?= esc($item['grupo']) ?>">
                                        <span class="event-date">
                                            <strong><?= esc(substr($item['data'], 8, 2)) ?></strong>
                                            <span><?= esc(mesAbreviado($item['data'])) ?></span>
                                        </span>
                                        <a href="<?= esc(substr($item['link'], strpos($item['link'], '#'))) ?>" class="lista-datas-texto">
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

                    <article class="panel" id="sobre">
                        <div class="panel-header">
                            <div><p class="eyebrow">Visão geral</p><h2>Sobre a exposição</h2></div>
                            <?php if ($podeGerenciar && !$editarInfo): ?>
                                <a href="evento.php?id=<?= $eventoId ?>&editar=info#sobre" class="text-link">Editar</a>
                            <?php endif; ?>
                        </div>

                        <?php if ($editarInfo): ?>

                            <?php
                            // Após um erro, reabre com o que foi digitado.
                            $info = $formComErro === 'info' ? $_POST : [];
                            $valorInfo = static fn(string $campo): string => trim((string) ($info[$campo] ?? ($evento[$campo] ?? '')));
                            $cursosMarcados = $info !== []
                                ? array_map('intval', (array) ($info['cursos'] ?? []))
                                : array_map(static fn(array $c): int => (int) $c['id'], $cursosDoEvento);
                            ?>
                            <form method="post" action="evento.php?id=<?= $eventoId ?>" class="form-elos form-lateral">
                                <?= \Elos\Frontend\campoCsrf() ?>
                                <input type="hidden" name="acao" value="salvar_informacoes">
                                <?php if ($formComErro === 'info' && $erroForm !== ''): ?>
                                    <p class="form-erro" role="alert"><?= esc($erroForm) ?></p>
                                <?php endif; ?>
                                <div class="campo">
                                    <label for="titulo">Nome da exposição</label>
                                    <input id="titulo" type="text" name="titulo" required value="<?= esc($valorInfo('titulo')) ?>">
                                </div>
                                <?php selectAberto('tipo_evento_id', 'Tipo', $tipos, $valorInfo('tipo_evento_id'), $valorInfo('tipo_evento_id_novo'), 'Novo tipo', 'Ex.: Mostra acadêmica'); ?>
                                <?php selectAberto('responsavel_id', 'Responsável', $responsaveis, $valorInfo('responsavel_id'), $valorInfo('responsavel_id_novo'), 'Novo responsável', 'Nome do responsável', 'campo', extraTipoResponsavel($valorInfo('responsavel_tipo_novo') ?: 'PESSOA')); ?>
                                <?php selectAberto('local_id', 'Local', $locais, $valorInfo('local_id'), $valorInfo('local_id_novo'), 'Novo local', 'Ex.: Auditório do bloco B'); ?>
                                <fieldset class="campo">
                                    <legend>Situação</legend>
                                    <label class="opcao-marcar">
                                        <input type="checkbox" name="cancelada" value="1" <?= ($info !== [] ? ($info['cancelada'] ?? '') === '1' : ($evento['status'] ?? '') === 'CANCELADO') ? 'checked' : '' ?>>
                                        <span>Exposição cancelada</span>
                                    </label>
                                </fieldset>
                                <fieldset class="campo">
                                    <legend>Cursos envolvidos</legend>
                                    <?php if ($cursos !== []): ?>
                                        <div class="opcoes-marcar">
                                            <?php foreach ($cursos as $curso): ?>
                                                <label class="opcao-marcar">
                                                    <input type="checkbox" name="cursos[]" value="<?= (int) $curso['id'] ?>" <?= in_array((int) $curso['id'], $cursosMarcados, true) ? 'checked' : '' ?>>
                                                    <span><?= esc($curso['nome'] ?? '') ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <input type="text" name="cursos_outros" value="<?= esc($info['cursos_outros'] ?? '') ?>" placeholder="Outros cursos (separe por vírgula)" aria-label="Outros cursos envolvidos" class="cursos-outros">
                                </fieldset>
                                <div class="campo">
                                    <label for="descricao">Descrição</label>
                                    <textarea id="descricao" name="descricao" rows="3"><?= esc($valorInfo('descricao')) ?></textarea>
                                </div>
                                <div class="campo">
                                    <label for="observacoes">Observações</label>
                                    <textarea id="observacoes" name="observacoes" rows="2"><?= esc($valorInfo('observacoes')) ?></textarea>
                                </div>
                                <?php if ($formComErro === 'info' && $conflitos !== []) { avisoConflito($conflitos); } ?>
                                <div class="form-botoes">
                                    <a href="evento.php?id=<?= $eventoId ?>#sobre" class="text-link">Cancelar</a>
                                    <button type="submit" class="primary-button">Salvar</button>
                                </div>
                            </form>

                        <?php else: ?>

                            <dl class="sobre">
                                <div><dt>Tipo</dt><dd><?= esc($evento['tipo_evento_nome'] ?? '—') ?></dd></div>
                                <div><dt>Local</dt><dd><?= esc($evento['local_nome'] ?? '—') ?></dd></div>
                                <div><dt>Responsável</dt><dd><?= esc($evento['responsavel_nome'] ?? '—') ?></dd></div>
                                <?php if ($cursosDoEvento !== []): ?>
                                    <div>
                                        <dt>Cursos</dt>
                                        <dd class="chips">
                                            <?php foreach ($cursosDoEvento as $curso): ?>
                                                <span class="chip"><?= esc($curso['nome'] ?? '') ?></span>
                                            <?php endforeach; ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($evento['descricao'])): ?>
                                    <div><dt>Descrição</dt><dd class="texto"><?= nl2br(esc($evento['descricao'])) ?></dd></div>
                                <?php endif; ?>
                                <?php if (!empty($evento['observacoes'])): ?>
                                    <div><dt>Observações</dt><dd class="texto"><?= nl2br(esc($evento['observacoes'])) ?></dd></div>
                                <?php endif; ?>
                            </dl>

                        <?php endif; ?>
                    </article>

                </aside>

            </div>

        <?php endif; ?>

        </div>
    </main>
</div>

<?php if ($podeGerenciar && $evento !== null) { sugestoesDeResponsavel($usuarios); } ?>
<script src="../assets/js/elos.js"></script>
<script>
// Ao abrir "Adicionar necessidade", o foco vai direto para o que escrever.
document.querySelectorAll('details.adicionar').forEach(function (details) {
    details.addEventListener('toggle', function () {
        if (details.open) {
            const campo = details.querySelector('input[name="titulo"]');
            if (campo) {
                campo.focus();
            }
        }
    });
});
</script>

</body>
</html>
