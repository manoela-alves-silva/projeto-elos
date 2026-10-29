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
use Elos\Frontend\Fases;
use Elos\Frontend\Identidade;
use Elos\Frontend\MenuLateral;
use Elos\Frontend\Planejamento;

use function Elos\Frontend\apiGet;
use function Elos\Frontend\slugDaExposicao;
use function Elos\Frontend\apiLista;
use function Elos\Frontend\avisoConflito;
use function Elos\Frontend\conflitosNoLocal;
use function Elos\Frontend\classePrioridade;
use function Elos\Frontend\classeStatusEvento;
use function Elos\Frontend\dataBr;
use function Elos\Frontend\dataValida;
use function Elos\Frontend\erroNasDatas;
use function Elos\Frontend\dataHoraBr;
use function Elos\Frontend\diasAte;
use function Elos\Frontend\distanciaRelativa;
use function Elos\Frontend\esc;
use function Elos\Frontend\exposicaoDoEndereco;
use function Elos\Frontend\linkExposicao;
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
use function Elos\Frontend\preCarregar;
use function Elos\Frontend\usuarioLogado;

use const Elos\Frontend\PRIORIDADES;

require_once __DIR__ . '/../src/Planejamento.php';
require_once __DIR__ . '/../src/Identidade.php';

// Tudo que a página usa em dois pedidos à API (antes eram uns 12):
// primeiro a lista de exposições (para achar a exposição pelo nome do
// endereço), depois tudo dela.
preCarregar(['/api/eventos']);
$idPedido = exposicaoDoEndereco();
if ($idPedido > 0) {
    $base = '/api/eventos/' . $idPedido;
    $listasDeGestor = in_array($_SESSION['usuario']['perfil'] ?? '', ['GESTOR', 'ADMIN'], true)
        ? ['/api/categorias-tarefa', '/api/tipos-evento', '/api/responsaveis', '/api/locais', '/api/cursos']
        : [];
    preCarregar([$base, $base . '/agenda', $base . '/tarefas', $base . '/cursos', $base . '/anexos', $base . '/historico', ...$listasDeGestor]);
}

$usuario = usuarioLogado('entrar');
$podeGerenciar = $usuario['podeGerenciar'];

/**
 * Endereço da exposição mantendo o filtro do checklist (?fase= ou
 * ?prazo=), para que marcar ou editar um item não tire o filtro.
 */
function enderecoComFiltro(int $eventoId): string
{
    $params = ['id' => $eventoId];

    foreach (['fase', 'prazo'] as $chave) {
        $valor = (string) ($_GET[$chave] ?? '');
        if (preg_match('/^[a-z0-9-]{1,60}$/', $valor) === 1) {
            $params[$chave] = $valor;
        }
    }

    unset($params['id']);

    return linkExposicao($eventoId, $params);
}

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
        $valores['prazo'] !== '' && !dataValida($valores['prazo']) => 'Essa data não existe. Confira o dia e o mês.',
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

$eventoId = exposicaoDoEndereco();
$erroForm = '';         // mensagem do formulário que falhou
$formComErro = '';      // qual reabrir: nova | item-N | info | datas | geral
$valoresNova = array_fill_keys(CAMPOS_NECESSIDADE, '') + [];
$valoresNova['prioridade'] = 'MEDIA';
$valoresItem = [];
$conflitos = [];        // local ocupado: reabre o formulário com o aviso

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $eventoId > 0) {
    $acao = (string) ($_POST['acao'] ?? '');
    $tarefaId = (int) ($_POST['tarefa_id'] ?? 0);

    try {
        if ($acao === 'marcar_necessidade') {
            // Rota só de status: GESTOR marca qualquer item; COLABORADOR
            // só os atribuídos a ele (o backend garante).
            $novoStatus = ($_POST['novo_status'] ?? '') === 'CONCLUIDA' ? 'CONCLUIDA' : 'PENDENTE';
            Api::put('/api/eventos/' . $eventoId . '/tarefas/' . $tarefaId . '/status', ['status' => $novoStatus]);
            flash('evento', $novoStatus === 'CONCLUIDA' ? 'Marcado como concluído.' : 'Voltou para pendente.');
            redirecionar(enderecoComFiltro($eventoId) . '#item-' . $tarefaId);
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

                // Toda necessidade nasce PENDENTE.
                $resposta = Api::post(
                    '/api/eventos/' . $eventoId . '/tarefas',
                    $lida['corpo'] + ['descricao' => null, 'status' => 'PENDENTE']
                );
                flash('evento', 'Adicionado: ' . $lida['valores']['titulo'] . '.');
                redirecionar(enderecoComFiltro($eventoId) . '#item-' . (int) ($resposta['tarefa']['id'] ?? 0));

            case 'editar_necessidade':
                $lida = lerNecessidade($_POST);
                $valoresItem = $lida['valores'];

                if ($lida['corpo'] === null) {
                    $erroForm = $lida['erro'];
                    $formComErro = 'item-' . $tarefaId;
                    break;
                }

                // Sem status no corpo: o backend mantém o atual.
                Api::put('/api/eventos/' . $eventoId . '/tarefas/' . $tarefaId, $lida['corpo']);
                flash('evento', 'Alterações salvas.');
                redirecionar(enderecoComFiltro($eventoId) . '#item-' . $tarefaId);

            case 'remover_necessidade':
                Api::delete('/api/eventos/' . $eventoId . '/tarefas/' . $tarefaId);
                flash('evento', 'Removido do planejamento.');
                redirecionar(enderecoComFiltro($eventoId) . '#necessidades');

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

                $erroForm = erroNasDatas($corpo);

                if ($erroForm !== '') {
                    $formComErro = 'datas';
                    break;
                }

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
                redirecionar(enderecoComFiltro($eventoId) . '#datas');

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
                redirecionar(enderecoComFiltro($eventoId));

            case 'salvar_identidade':
                // Capa e cor. A imagem é guardada aqui no frontend; a API
                // guarda só o nome do arquivo.
                $atual = Api::get('/api/eventos/' . $eventoId)['evento'] ?? [];
                $capa = $atual['imagem_capa'] ?? null;
                $cor = strtolower(trim((string) ($_POST['cor'] ?? '')));
                $corAutomatica = ($_POST['cor_automatica'] ?? '') === '1';
                $novaCapa = null;

                if (!empty($_FILES['capa']['name'])) {
                    $envio = Identidade::salvarEnvio($_FILES['capa']);
                    if ($envio['erro'] !== '') {
                        $erroForm = $envio['erro'];
                        $formComErro = 'identidade';
                        break;
                    }
                    $novaCapa = $envio['nome'];
                    $capa = $novaCapa;
                    // Cor tirada da imagem, a não ser que alguém tenha escolhido outra agora.
                    if (($_POST['cor_mudou'] ?? '') !== '1' && $envio['cor'] !== null) {
                        $cor = $envio['cor'];
                        $corAutomatica = false;
                    }
                } elseif (($_POST['remover_capa'] ?? '') === '1') {
                    $capa = null;
                }

                if ($corAutomatica) {
                    $cor = '';
                }
                if ($cor !== '' && preg_match('/^#[0-9a-f]{6}$/', $cor) !== 1) {
                    Identidade::apagarArquivo($novaCapa);
                    $erroForm = 'Cor inválida.';
                    $formComErro = 'identidade';
                    break;
                }

                try {
                    $resposta = Api::put('/api/eventos/' . $eventoId . '/identidade', [
                        'imagem_capa' => Identidade::nomeValido($capa) ? $capa : null,
                        'cor' => $cor !== '' ? $cor : null,
                    ]);
                } catch (ApiException $e) {
                    Identidade::apagarArquivo($novaCapa);
                    throw $e;
                }

                // A capa antiga só sai do disco depois que o banco aceitou a troca.
                $anterior = $resposta['capa_anterior'] ?? null;
                if ($anterior !== null && $anterior !== $capa) {
                    Identidade::apagarArquivo($anterior);
                }

                flash('evento', 'Identidade visual salva.');
                redirecionar(enderecoComFiltro($eventoId));

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
            'salvar_identidade' => 'identidade',
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
    $erroEvento = defined('ELOS_SLUG_EXPOSICAO') ? 'Exposição não encontrada. Ela pode ter mudado de nome: procure na lista de exposições.' : 'Nenhuma exposição foi informada.';
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

    // Checklist por fase (a partir da categoria de cada item).
    $fases = Fases::agrupar($plano->necessidades ?? []);

    // Prazos que pedem atenção, para o filtro rápido.
    $urgentes = ['atrasadas' => [], 'semana' => []];
    foreach ($plano->necessidades ?? [] as $tarefa) {
        $estado = Planejamento::estadoNecessidade($tarefa);
        if ($estado === 'atrasado') {
            $urgentes['atrasadas'][] = $tarefa;
        } elseif ($estado === 'pendente' && !empty($tarefa['prazo']) && diasAte((string) $tarefa['prazo']) <= 7) {
            $urgentes['semana'][] = $tarefa;
        }
    }

    // Filtro escolhido: uma fase (?fase=) ou um prazo (?prazo=).
    $filtroFase = (string) ($_GET['fase'] ?? '');
    $filtroPrazo = (string) ($_GET['prazo'] ?? '');
    if (!in_array($filtroFase, array_column($fases, 'chave'), true)) {
        $filtroFase = '';
    }
    if (!isset($urgentes[$filtroPrazo]) || $urgentes[$filtroPrazo] === []) {
        $filtroPrazo = '';
    }
    $fasesVisiveis = $filtroFase === ''
        ? $fases
        : array_values(array_filter($fases, static fn(array $f): bool => $f['chave'] === $filtroFase));

    // Um item com erro no formulário de edição precisa ficar visível.
    if ($formComErro !== '' && str_starts_with($formComErro, 'item-')) {
        $filtroFase = $filtroPrazo = '';
        $fasesVisiveis = $fases;
    }

    $linkFiltro = static fn(array $params): string =>
        linkExposicao($eventoId, $params, '#necessidades');

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
    $editarIdentidade = $podeGerenciar && (($_GET['editar'] ?? '') === 'identidade' || $formComErro === 'identidade');
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
function itemChecklist(array $tarefa, int $eventoId, bool $podeGerenciar, int $usuarioId, string $formComErro, array $valoresItem, string $erroForm, array $categorias, array $usuarios, string $nomeDaFase = ''): void
{
    // Dentro do bloco da fase, a etiqueta só aparece se disser algo a mais.
    $categoriaNome = (string) ($tarefa['categoria_nome'] ?? '');
    $mostrarCategoria = $nomeDaFase === '' || mb_strtolower(trim($categoriaNome)) !== mb_strtolower($nomeDaFase);
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

        <form method="post" action="<?= esc(enderecoComFiltro($eventoId)) ?>" class="item-check">
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
                <?php if ($mostrarCategoria): ?>
                    <span class="categoria" style="<?= Fases::estiloCor(Fases::daCategoria($categoriaNome)['etapa']) ?>"><?= esc($categoriaNome !== '' ? $categoriaNome : 'Sem categoria') ?></span>
                <?php endif; ?>
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
                        <form method="post" action="<?= esc(enderecoComFiltro($eventoId)) ?>" class="form-elos form-item">
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
                        <form method="post" action="<?= esc(enderecoComFiltro($eventoId)) ?>" class="confirmar-remocao">
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
    <!-- O endereço tem o nome da exposição (/exposicao/nome): os links
         relativos continuam valendo a partir da raiz do site. -->
    <base href="<?= esc(\Elos\Frontend\raizDoSite()) ?>/">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($tituloPagina) ?> | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/base.css?v=2026-10-29">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=2026-10-29">
    <link rel="stylesheet" href="../assets/css/planejamento.css?v=2026-10-29">
    <link rel="stylesheet" href="../assets/css/evento.css?v=2026-10-29">
</head>
<body>

<div class="app-shell">

    <?php MenuLateral::renderizar('eventos', false, $usuario['nome'], $usuario['primeiroNome'], $usuario['perfil']); ?>

    <main class="main-content">

        <header class="topbar">
            <a href="exposicoes" class="voltar">← Exposições</a>
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
                    <a href="exposicoes" class="primary-button">Ver exposições</a>
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
            <section class="expo-cabecalho identidade<?= Identidade::urlCapa($evento) !== null ? ' com-capa' : '' ?>" style="<?= esc(Identidade::estilo($evento)) ?>">

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
                        <a href="relatorio?evento=<?= esc(slugDaExposicao($eventoId)) ?>" class="botao-secundario">⎙ Relatório para imprimir</a>
                        <?php if ($podeGerenciar): ?>
                            <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'info'], 'sobre')) ?>" class="botao-secundario">✎ Editar informações</a>
                            <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'identidade'], 'identidade')) ?>" class="botao-secundario">◐ Capa e cor</a>
                            <a href="<?= esc(linkExposicao($eventoId, [], '', 'apagar-exposicao')) ?>" class="botao-secundario botao-apagar">Apagar exposição</a>
                        <?php endif; ?>
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

            <?php if ($editarIdentidade): ?>
                <?php $temCapa = Identidade::urlCapa($evento) !== null; ?>
                <article class="panel painel-identidade" id="identidade">
                    <div class="panel-header">
                        <div><p class="eyebrow">Identidade visual</p><h2>Capa e cor da exposição</h2></div>
                        <a href="<?= esc(linkExposicao($eventoId)) ?>" class="text-link">Cancelar</a>
                    </div>
                    <form method="post" action="<?= esc(linkExposicao($eventoId)) ?>" enctype="multipart/form-data" class="form-elos form-identidade">
                        <?= \Elos\Frontend\campoCsrf() ?>
                        <input type="hidden" name="acao" value="salvar_identidade">
                        <input type="hidden" name="cor_mudou" value="0" class="js-cor-mudou">
                        <?php if ($formComErro === 'identidade' && $erroForm !== ''): ?>
                            <p class="form-erro" role="alert"><?= esc($erroForm) ?></p>
                        <?php endif; ?>

                        <div class="identidade-campos">
                            <div class="campo">
                                <label for="capa">Imagem de capa</label>
                                <input type="file" id="capa" name="capa" accept="image/jpeg,image/png,image/webp">
                                <p class="dica">JPG, PNG ou WebP, até 8 MB. Imagens largas (paisagem) ficam melhores. Ela aparece à direita dos cards e some num degradê.</p>
                                <?php if ($temCapa): ?>
                                    <label class="opcao"><input type="checkbox" name="remover_capa" value="1"> Remover a capa atual</label>
                                <?php endif; ?>
                            </div>

                            <div class="campo">
                                <label for="cor">Cor da exposição</label>
                                <div class="cor-linha">
                                    <input type="color" id="cor" name="cor" value="<?= esc(Identidade::cor($evento)) ?>" class="js-cor">
                                    <span class="dica">Usada nos detalhes do card. Ao enviar uma imagem, o ELOS sugere uma cor tirada dela.</span>
                                </div>
                                <?php if (!empty($evento['cor'])): ?>
                                    <label class="opcao"><input type="checkbox" name="cor_automatica" value="1"> Voltar para a cor automática</label>
                                <?php endif; ?>
                            </div>

                            <div class="identidade-previa" style="<?= esc(Identidade::estilo($evento)) ?>" aria-hidden="true">
                                <span class="previa-rotulo">Prévia do card</span>
                                <span class="previa-titulo"><?= esc($tituloPagina) ?></span>
                                <span class="previa-numeros"><i></i><i></i><i></i></span>
                            </div>
                        </div>

                        <div class="form-botoes">
                            <p class="dica">Só muda a aparência. Nenhum dado da exposição é alterado.</p>
                            <button type="submit" class="primary-button">Salvar</button>
                        </div>
                    </form>
                </article>
                <script>
                    // Escolher uma cor à mão impede que a cor da imagem a substitua.
                    document.getElementById('capa').addEventListener('change', function () {
                        if (this.files && this.files[0]) {
                            document.querySelector('.identidade-previa').style.setProperty('--expo-capa', 'url("' + URL.createObjectURL(this.files[0]) + '")');
                        }
                    });
                    document.querySelector('.js-cor').addEventListener('input', function () {
                        document.querySelector('.js-cor-mudou').value = '1';
                        document.querySelector('.identidade-previa').style.setProperty('--expo-cor', this.value);
                    });
                </script>
            <?php endif; ?>

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

                                <form method="post" action="<?= esc(enderecoComFiltro($eventoId)) ?>" class="form-elos form-adicionar">
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

                            <!-- FILTROS: por prazo e por fase -->
                            <nav class="filtros-fase" aria-label="Filtrar o checklist">
                                <a href="<?= esc($linkFiltro([])) ?>" class="filtro<?= $filtroFase === '' && $filtroPrazo === '' ? ' ativo' : '' ?>">
                                    Todas <span><?= $resumo['pendentes'] ?></span>
                                </a>
                                <?php if ($urgentes['atrasadas'] !== []): ?>
                                    <a href="<?= esc($linkFiltro(['prazo' => 'atrasadas'])) ?>" class="filtro filtro-atrasadas<?= $filtroPrazo === 'atrasadas' ? ' ativo' : '' ?>">
                                        Atrasadas <span><?= count($urgentes['atrasadas']) ?></span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($urgentes['semana'] !== []): ?>
                                    <a href="<?= esc($linkFiltro(['prazo' => 'semana'])) ?>" class="filtro filtro-semana<?= $filtroPrazo === 'semana' ? ' ativo' : '' ?>">
                                        Próximos 7 dias <span><?= count($urgentes['semana']) ?></span>
                                    </a>
                                <?php endif; ?>
                                <?php foreach (array_keys(Fases::ROTULOS_ETAPA) as $etapa): ?>
                                    <?php $fasesDaEtapa = array_filter($fases, static fn(array $f): bool => $f['etapa'] === $etapa); ?>
                                    <?php if ($fasesDaEtapa === []) { continue; } ?>
                                    <div class="filtros-etapa" style="<?= Fases::estiloCor($etapa) ?>">
                                        <span class="filtros-etapa-nome"><?= esc(Fases::ROTULOS_ETAPA[$etapa]) ?></span>
                                        <div class="filtros-etapa-lista">
                                        <?php foreach ($fasesDaEtapa as $fase): ?>
                                            <a
                                                href="<?= esc($linkFiltro(['fase' => $fase['chave']])) ?>"
                                                class="filtro filtro-fase<?= $filtroFase === $fase['chave'] ? ' ativo' : '' ?><?= $fase['pendentes'] === 0 ? ' completa' : '' ?>"
                                                <?= $filtroFase === $fase['chave'] ? 'aria-current="true"' : '' ?>
                                            >
                                                <i aria-hidden="true"></i><?= esc($fase['rotulo']) ?>
                                                <span title="<?= $fase['pendentes'] ?> pendente(s)"><?= $fase['pendentes'] === 0 ? '✓' : $fase['pendentes'] ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </nav>

                            <?php if ($filtroPrazo !== ''): ?>

                                <!-- FILTRO POR PRAZO: lista única, por data -->
                                <section class="bloco-fase bloco-prazo">
                                    <h3 class="fase-cabecalho">
                                        <span class="fase-nome"><?= $filtroPrazo === 'atrasadas' ? 'Atrasadas' : 'Nos próximos 7 dias' ?></span>
                                        <a href="<?= esc($linkFiltro([])) ?>" class="text-link">Ver todas as fases</a>
                                    </h3>
                                    <ul class="checklist">
                                        <?php foreach ($urgentes[$filtroPrazo] as $tarefa): ?>
                                            <?php itemChecklist($tarefa, $eventoId, $podeGerenciar, $usuario['id'], $formComErro, $valoresItem, $erroForm, $categorias, $usuarios); ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </section>

                            <?php else: ?>

                                <?php foreach ($fasesVisiveis as $fase): ?>
                                    <?php
                                    $pendentesFase = array_values(array_filter($fase['itens'], static fn(array $t): bool => !in_array(Planejamento::estadoNecessidade($t), ['concluido', 'cancelado'], true)));
                                    $feitasFase = array_values(array_filter($fase['itens'], static fn(array $t): bool => in_array(Planejamento::estadoNecessidade($t), ['concluido', 'cancelado'], true)));
                                    $abrirFeitas = $filtroFase !== '' || ($pendentesFase === [] && $feitasFase !== []);
                                    foreach ($feitasFase as $tarefa) {
                                        $abrirFeitas = $abrirFeitas || $formComErro === 'item-' . (int) $tarefa['id'];
                                    }
                                    $percentualFase = $fase['total'] > 0 ? (int) round($fase['feitas'] * 100 / $fase['total']) : 0;
                                    ?>
                                    <section class="bloco-fase" id="fase-<?= esc($fase['chave']) ?>" style="<?= Fases::estiloCor($fase['etapa']) ?>">
                                        <h3 class="fase-cabecalho">
                                            <span class="fase-etapa"><?= esc(Fases::ROTULOS_ETAPA[$fase['etapa']] ?? '') ?></span>
                                            <span class="fase-nome"><?= esc($fase['rotulo']) ?></span>
                                            <span class="fase-progresso" title="<?= $percentualFase ?>% feito">
                                                <span class="fase-barra"><span style="width: <?= $percentualFase ?>%"></span></span>
                                                <?= $fase['feitas'] ?> de <?= $fase['total'] ?>
                                            </span>
                                        </h3>

                                        <?php if ($pendentesFase !== []): ?>
                                            <ul class="checklist">
                                                <?php foreach ($pendentesFase as $tarefa): ?>
                                                    <?php itemChecklist($tarefa, $eventoId, $podeGerenciar, $usuario['id'], $formComErro, $valoresItem, $erroForm, $categorias, $usuarios, $fase['rotulo']); ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="fase-completa">✓ Tudo resolvido nesta fase.</p>
                                        <?php endif; ?>

                                        <?php if ($feitasFase !== []): ?>
                                            <details class="grupo-feito" <?= $abrirFeitas ? 'open' : '' ?>>
                                                <summary class="grupo-titulo">Resolvidas <span><?= count($feitasFase) ?></span></summary>
                                                <ul class="checklist">
                                                    <?php foreach ($feitasFase as $tarefa): ?>
                                                        <?php itemChecklist($tarefa, $eventoId, $podeGerenciar, $usuario['id'], $formComErro, $valoresItem, $erroForm, $categorias, $usuarios, $fase['rotulo']); ?>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                    </section>
                                <?php endforeach; ?>

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
                                <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'datas'], 'datas')) ?>" class="text-link">Editar</a>
                            <?php endif; ?>
                        </div>

                        <?php if ($editarDatas): ?>

                            <form method="post" action="<?= esc(linkExposicao($eventoId)) ?>" class="form-elos form-lateral">
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
                                    <a href="<?= esc(linkExposicao($eventoId, [], 'datas')) ?>" class="text-link">Cancelar</a>
                                    <button type="submit" class="primary-button">Salvar datas</button>
                                </div>
                            </form>

                        <?php elseif ($plano->agendaIndisponivel): ?>

                            <p class="lateral-vazio">Não foi possível carregar as datas agora.</p>

                        <?php elseif ($agenda === null): ?>

                            <p class="lateral-vazio">
                                Nenhuma data definida.
                                <?php if ($podeGerenciar): ?>
                                    <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'datas'], 'datas')) ?>" class="text-link">Definir datas</a>
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
                        $linkCalendario = 'calendario?evento=' . rawurlencode(slugDaExposicao($eventoId))
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
                                        <a href="<?= esc(linkExposicao($eventoId, [], substr($item['link'], strpos($item['link'], '#') ?: strlen($item['link'])))) ?>" class="lista-datas-texto">
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
                                <a href="<?= esc(linkExposicao($eventoId, ['editar' => 'info'], 'sobre')) ?>" class="text-link">Editar</a>
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
                            <form method="post" action="<?= esc(linkExposicao($eventoId)) ?>" class="form-elos form-lateral">
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
                                    <a href="<?= esc(linkExposicao($eventoId, [], 'sobre')) ?>" class="text-link">Cancelar</a>
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
<script src="../assets/js/elos.js?v=2026-10-29"></script>
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
