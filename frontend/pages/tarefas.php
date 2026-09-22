<?php

declare(strict_types=1);

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;
use Elos\Frontend\MenuLateral;

require_once __DIR__ . '/../src/Api.php';
require_once __DIR__ . '/../src/MenuLateral.php';

\Elos\Frontend\iniciarSessao();

$usuario = $_SESSION['usuario'] ?? null;

if (!is_array($usuario) || empty($usuario['id'])) {
    header('Location: login.php');
    exit();
}

$usuarioId = (int) $usuario['id'];
$nomeUsuario = (string) ($usuario['nome'] ?? 'Usuário');
$perfilUsuario = (string) ($usuario['perfil'] ?? 'COLABORADOR');
$primeiroNome = explode(' ', trim($nomeUsuario))[0] ?: $nomeUsuario;

// GESTOR/ADMIN: podem criar/editar tarefas (mesma regra do backend,
// AuthorizationService::hasRole — não é uma matriz nova).
$podeGerenciarTarefas = in_array($perfilUsuario, ['ADMIN', 'GESTOR'], true);

const PRIORIDADES_VALIDAS = ['BAIXA', 'MEDIA', 'ALTA'];
const STATUS_VALIDOS = ['PENDENTE', 'EM_ANDAMENTO', 'CONCLUIDA', 'BLOQUEADA', 'CANCELADA'];

function escapar(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function buscarApiTarefas(string $path): ?array
{
    try {
        return Api::get($path);
    } catch (ApiException) {
        return null;
    }
}

function formatarData(?string $data): string
{
    if (!$data) {
        return 'Sem prazo';
    }

    $timestamp = strtotime($data);

    return $timestamp === false
        ? $data
        : date('d/m/Y', $timestamp);
}

function traduzirStatusTarefa(string $status): string
{
    return match ($status) {
        'PENDENTE' => 'Pendente',
        'EM_ANDAMENTO' => 'Em andamento',
        'CONCLUIDA' => 'Concluída',
        'BLOQUEADA' => 'Bloqueada',
        'CANCELADA' => 'Cancelada',
        default => $status,
    };
}

function classeStatusTarefa(string $status): string
{
    return match ($status) {
        'PENDENTE' => 'tarefa-pendente',
        'EM_ANDAMENTO' => 'tarefa-andamento',
        'CONCLUIDA' => 'tarefa-concluida',
        'BLOQUEADA' => 'tarefa-bloqueada',
        'CANCELADA' => 'tarefa-cancelada',
        default => 'tarefa-pendente',
    };
}

function traduzirPrioridade(string $prioridade): string
{
    return match ($prioridade) {
        'ALTA' => 'Alta',
        'MEDIA' => 'Média',
        'BAIXA' => 'Baixa',
        default => $prioridade,
    };
}

function classePrioridade(string $prioridade): string
{
    return match ($prioridade) {
        'ALTA' => 'priority-alta',
        'MEDIA' => 'priority-media',
        default => 'priority-baixa',
    };
}

/**
 * Monta o corpo enviado para criação/edição a partir do POST,
 * convertendo campos vazios em null (campos opcionais no backend).
 *
 * @return array<string, mixed>
 */
function montarCorpoTarefa(array $post): array
{
    $descricao = trim((string) ($post['descricao'] ?? ''));
    $prazo = trim((string) ($post['prazo'] ?? ''));
    $observacoes = trim((string) ($post['observacoes'] ?? ''));
    $usuarioResponsavel = trim((string) ($post['usuario_responsavel_id'] ?? ''));
    $categoria = trim((string) ($post['categoria_id'] ?? ''));
    $etapa = trim((string) ($post['etapa_id'] ?? ''));

    return [
        // Etapa é opcional (necessidades do checklist do evento não têm etapa).
        'etapa_id' => $etapa !== '' ? (int) $etapa : null,
        'titulo' => trim((string) ($post['titulo'] ?? '')),
        'descricao' => $descricao !== '' ? $descricao : null,
        'prazo' => $prazo !== '' ? $prazo : null,
        'prioridade' => (string) ($post['prioridade'] ?? ''),
        'status' => (string) ($post['status'] ?? ''),
        'observacoes' => $observacoes !== '' ? $observacoes : null,
        'usuario_responsavel_id' => $usuarioResponsavel !== '' ? (int) $usuarioResponsavel : null,
        'categoria_id' => $categoria !== '' ? (int) $categoria : null,
    ];
}

/**
 * Valida os campos comuns de criação/edição de tarefa.
 * Retorna uma mensagem de erro, ou string vazia se estiver tudo certo.
 */
function validarCamposTarefa(array $corpo, int $eventoId): string
{
    if ($eventoId <= 0) {
        return 'Selecione o evento da tarefa.';
    }

    if ($corpo['titulo'] === '') {
        return 'Título é obrigatório.';
    }

    if (!in_array($corpo['prioridade'], PRIORIDADES_VALIDAS, true)) {
        return 'Selecione uma prioridade válida.';
    }

    if (!in_array($corpo['status'], STATUS_VALIDOS, true)) {
        return 'Selecione um status válido.';
    }

    return '';
}

$mensagemErro = '';
$mensagemSucesso = '';
$acaoPost = '';

// --- Processa o POST (criar / editar / alterar status) antes de
// carregar a listagem, para que o resultado já apareça atualizado
// na mesma resposta, sem precisar logar novamente. ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acaoPost = (string) ($_POST['acao'] ?? '');

    if ($acaoPost === 'criar') {
        if (!$podeGerenciarTarefas) {
            $mensagemErro = 'Você não tem permissão para criar tarefas.';
        } else {
            $eventoIdPost = (int) ($_POST['evento_id'] ?? 0);
            $corpo = montarCorpoTarefa($_POST);
            $mensagemErro = validarCamposTarefa($corpo, $eventoIdPost);

            if ($mensagemErro === '') {
                try {
                    Api::post('/api/eventos/' . $eventoIdPost . '/tarefas', $corpo);

                    // Post/Redirect/Get: sem isso, o POST continua em
                    // "?painel=novo" (herdado de action=""), o painel
                    // de criação permanece aberto e o formulário é
                    // repopulado com o que acabou de ser enviado — a
                    // tarefa é criada, mas a tela parece não ter feito
                    // nada.
                    $_SESSION['_tarefas_flash_sucesso'] = 'Tarefa criada com sucesso.';
                    header('Location: tarefas.php');
                    exit();
                } catch (ApiException $e) {
                    $mensagemErro = $e->getMessage();
                }
            }
        }
    } elseif ($acaoPost === 'editar') {
        if (!$podeGerenciarTarefas) {
            $mensagemErro = 'Você não tem permissão para editar tarefas.';
        } else {
            $eventoIdPost = (int) ($_POST['evento_id'] ?? 0);
            $idPost = (int) ($_POST['id'] ?? 0);
            $corpo = montarCorpoTarefa($_POST);
            $mensagemErro = $idPost <= 0
                ? 'Não foi possível identificar a tarefa a editar.'
                : validarCamposTarefa($corpo, $eventoIdPost);

            if ($mensagemErro === '') {
                try {
                    Api::put('/api/eventos/' . $eventoIdPost . '/tarefas/' . $idPost, $corpo);
                    $mensagemSucesso = 'Tarefa atualizada com sucesso.';
                } catch (ApiException $e) {
                    $mensagemErro = $e->getMessage();
                }
            }
        }
    } elseif ($acaoPost === 'status') {
        $eventoIdPost = (int) ($_POST['evento_id'] ?? 0);
        $idPost = (int) ($_POST['id'] ?? 0);
        $statusPost = (string) ($_POST['status'] ?? '');

        if ($eventoIdPost <= 0 || $idPost <= 0) {
            $mensagemErro = 'Não foi possível identificar a tarefa.';
        } elseif (!in_array($statusPost, STATUS_VALIDOS, true)) {
            $mensagemErro = 'Selecione um status válido.';
        } else {
            try {
                Api::put(
                    '/api/eventos/' . $eventoIdPost . '/tarefas/' . $idPost . '/status',
                    ['status' => $statusPost]
                );
                $mensagemSucesso = 'Status atualizado com sucesso.';
            } catch (ApiException $e) {
                $mensagemErro = $e->statusCode === 403
                    ? 'Você só pode alterar o status de tarefas atribuídas a você.'
                    : $e->getMessage();
            }
        }
    } else {
        $mensagemErro = 'Ação inválida.';
    }
}

if ($mensagemSucesso === '' && isset($_SESSION['_tarefas_flash_sucesso'])) {
    $mensagemSucesso = $_SESSION['_tarefas_flash_sucesso'];
    unset($_SESSION['_tarefas_flash_sucesso']);
}

// --- Carrega dados de referência pela API (nunca fixos no HTML/JS) ---
$dadosEventos = buscarApiTarefas('/api/eventos');
$apiIndisponivel = $dadosEventos === null;
$eventos = is_array($dadosEventos) ? ($dadosEventos['eventos'] ?? []) : [];
if (!is_array($eventos)) {
    $eventos = [];
}

$dadosEtapas = buscarApiTarefas('/api/etapas');
$etapas = is_array($dadosEtapas) ? ($dadosEtapas['etapas'] ?? []) : [];
if (!is_array($etapas)) {
    $etapas = [];
}

$dadosCategorias = buscarApiTarefas('/api/categorias-tarefa');
$categorias = is_array($dadosCategorias) ? ($dadosCategorias['categorias_tarefa'] ?? []) : [];
if (!is_array($categorias)) {
    $categorias = [];
}

$dadosUsuarios = buscarApiTarefas('/api/usuarios');
$usuariosDisponiveis = is_array($dadosUsuarios) ? ($dadosUsuarios['usuarios'] ?? []) : [];
if (!is_array($usuariosDisponiveis)) {
    $usuariosDisponiveis = [];
}

// Não existe endpoint "/api/tarefas" global — tarefas só existem
// aninhadas em "/api/eventos/{id}/tarefas". Para listar todas,
// agregamos por evento (mesma regra de autorização de cada chamada).
$tarefas = [];

foreach ($eventos as $evento) {
    if (!is_array($evento) || !isset($evento['id'])) {
        continue;
    }

    $dadosTarefasEvento = buscarApiTarefas('/api/eventos/' . (int) $evento['id'] . '/tarefas');
    $tarefasEvento = is_array($dadosTarefasEvento) ? ($dadosTarefasEvento['tarefas'] ?? []) : [];

    if (!is_array($tarefasEvento)) {
        continue;
    }

    foreach ($tarefasEvento as $tarefa) {
        if (!is_array($tarefa)) {
            continue;
        }

        $tarefa['evento_titulo'] = $evento['titulo'] ?? ('Evento #' . $evento['id']);
        $tarefas[] = $tarefa;
    }
}

usort(
    $tarefas,
    static function (array $a, array $b): int {
        $prazoA = strtotime((string) ($a['prazo'] ?? '9999-12-31'));
        $prazoB = strtotime((string) ($b['prazo'] ?? '9999-12-31'));

        return $prazoA <=> $prazoB;
    }
);

$filtroEventoId = (int) ($_GET['evento_id'] ?? 0);

if ($filtroEventoId > 0) {
    $tarefas = array_values(
        array_filter(
            $tarefas,
            static fn(array $t): bool => (int) ($t['evento_id'] ?? 0) === $filtroEventoId
        )
    );
}

// --- Estado do painel (formulário) ---
$painelAberto = (string) ($_GET['painel'] ?? '');
if (!in_array($painelAberto, ['novo', 'editar'], true)) {
    $painelAberto = '';
}

if ($painelAberto !== '' && !$podeGerenciarTarefas) {
    $painelAberto = '';
}

if ($mensagemErro !== '' && $acaoPost === 'criar') {
    $painelAberto = 'novo';
}

if ($mensagemErro !== '' && $acaoPost === 'editar') {
    $painelAberto = 'editar';
}

$tarefaEditando = null;

if ($painelAberto === 'editar') {
    $eventoIdEdit = (int) ($_GET['evento_id'] ?? ($_POST['evento_id'] ?? 0));
    $idEdit = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));

    foreach ($tarefas as $t) {
        if (
            (int) ($t['evento_id'] ?? 0) === $eventoIdEdit
            && (int) ($t['id'] ?? 0) === $idEdit
        ) {
            $tarefaEditando = $t;
            break;
        }
    }

    if ($tarefaEditando === null) {
        $painelAberto = '';

        if ($mensagemErro === '') {
            $mensagemErro = 'Tarefa não encontrada para edição.';
        }
    }
}

// Valores exibidos no formulário: o que acabou de ser digitado
// (se a validação falhou) ou os dados atuais da tarefa em edição.
$valoresForm = [
    'evento_id' => '',
    'evento_titulo' => '',
    'id' => '',
    'titulo' => '',
    'descricao' => '',
    'usuario_responsavel_id' => '',
    'etapa_id' => '',
    'categoria_id' => '',
    'prazo' => '',
    'prioridade' => '',
    'status' => 'PENDENTE',
    'observacoes' => '',
];

if ($painelAberto === 'novo' && $acaoPost === 'criar') {
    foreach ($valoresForm as $campo => $default) {
        $valoresForm[$campo] = (string) ($_POST[$campo] ?? $default);
    }
} elseif ($painelAberto === 'editar') {
    if ($acaoPost === 'editar') {
        foreach ($valoresForm as $campo => $default) {
            $valoresForm[$campo] = (string) ($_POST[$campo] ?? $default);
        }
        $valoresForm['evento_titulo'] = (string) ($tarefaEditando['evento_titulo'] ?? '');
    } elseif ($tarefaEditando !== null) {
        $valoresForm = [
            'evento_id' => (string) ($tarefaEditando['evento_id'] ?? ''),
            'evento_titulo' => (string) ($tarefaEditando['evento_titulo'] ?? ''),
            'id' => (string) ($tarefaEditando['id'] ?? ''),
            'titulo' => (string) ($tarefaEditando['titulo'] ?? ''),
            'descricao' => (string) ($tarefaEditando['descricao'] ?? ''),
            'usuario_responsavel_id' => $tarefaEditando['usuario_responsavel_id'] !== null
                ? (string) $tarefaEditando['usuario_responsavel_id']
                : '',
            'etapa_id' => (string) ($tarefaEditando['etapa_id'] ?? ''),
            'categoria_id' => $tarefaEditando['categoria_id'] !== null
                ? (string) $tarefaEditando['categoria_id']
                : '',
            'prazo' => (string) ($tarefaEditando['prazo'] ?? ''),
            'prioridade' => (string) ($tarefaEditando['prioridade'] ?? ''),
            'status' => (string) ($tarefaEditando['status'] ?? ''),
            'observacoes' => (string) ($tarefaEditando['observacoes'] ?? ''),
        ];
    }
}

$etapasOrdenadas = $etapas;
usort(
    $etapasOrdenadas,
    static fn(array $a, array $b): int => (int) ($a['ordem'] ?? 0) <=> (int) ($b['ordem'] ?? 0)
);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Tarefas | ELOS</title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/tarefas.css">
</head>

<body>

<div class="app-shell">

    <?php MenuLateral::renderizar('eventos', false, $nomeUsuario, $primeiroNome, $perfilUsuario); ?>

    <main class="main-content">

        <header class="topbar">

            <div class="topbar-search">

                <span class="search-icon">⌕</span>

                <input
                    type="search"
                    id="tarefasSearch"
                    placeholder="Buscar tarefas..."
                    aria-label="Buscar tarefas"
                >

            </div>

            <div class="topbar-actions">

                <div class="topbar-user">

                    <div class="user-avatar small">
                        <?= escapar(mb_strtoupper(mb_substr($nomeUsuario, 0, 1))) ?>
                    </div>

                    <div>
                        <strong><?= escapar($primeiroNome) ?></strong>
                        <span><?= escapar($perfilUsuario) ?></span>
                    </div>

                </div>

            </div>

        </header>

        <div class="content-wrapper">

            <?php if ($apiIndisponivel): ?>

                <div class="system-message" role="alert">
                    <span class="system-message-icon" aria-hidden="true">!</span>
                    <div class="system-message-body">
                        <strong>Não foi possível falar com a API do ELOS.</strong>
                        <p>Os dados desta tela podem estar desatualizados.</p>
                    </div>
                </div>

            <?php endif; ?>

            <?php if ($mensagemSucesso !== ''): ?>

                <div class="system-message success" role="status">
                    <span class="system-message-icon" aria-hidden="true">✓</span>
                    <div class="system-message-body">
                        <strong><?= escapar($mensagemSucesso) ?></strong>
                    </div>
                </div>

            <?php endif; ?>

            <?php if ($mensagemErro !== '' && $painelAberto === ''): ?>

                <div class="system-message" role="alert">
                    <span class="system-message-icon" aria-hidden="true">!</span>
                    <div class="system-message-body">
                        <strong><?= escapar($mensagemErro) ?></strong>
                    </div>
                </div>

            <?php endif; ?>

            <section class="welcome-section">

                <div>
                    <p class="eyebrow">GESTÃO DE ATIVIDADES</p>
                    <h1>
                        Tarefas
                        <span>Acompanhe e organize o trabalho de cada evento.</span>
                    </h1>
                </div>

                <?php if ($podeGerenciarTarefas && $painelAberto === ''): ?>
                    <a href="?painel=novo" class="primary-button">
                        <span>+</span>
                        Nova tarefa
                    </a>
                <?php endif; ?>

            </section>

            <?php if ($painelAberto === 'novo' || $painelAberto === 'editar'): ?>

                <article class="panel tarefa-form">

                    <div class="panel-header">
                        <div>
                            <p class="eyebrow">
                                <?= $painelAberto === 'novo' ? 'NOVA TAREFA' : 'EDITAR TAREFA' ?>
                            </p>
                            <h2>
                                <?= $painelAberto === 'novo'
                                    ? 'Cadastrar tarefa'
                                    : 'Editar tarefa #' . (int) $valoresForm['id'] ?>
                            </h2>
                        </div>
                        <a href="tarefas.php" class="text-link">Cancelar</a>
                    </div>

                    <?php if ($mensagemErro !== ''): ?>
                        <div class="system-message" role="alert">
                            <span class="system-message-icon" aria-hidden="true">!</span>
                            <div class="system-message-body">
                                <strong><?= escapar($mensagemErro) ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <?= \Elos\Frontend\campoCsrf() ?>

                        <input type="hidden" name="acao" value="<?= $painelAberto === 'novo' ? 'criar' : 'editar' ?>">

                        <?php if ($painelAberto === 'editar'): ?>
                            <input type="hidden" name="id" value="<?= escapar($valoresForm['id']) ?>">
                        <?php endif; ?>

                        <div class="form-grid">

                            <div class="field field-full">
                                <label for="evento_id">Evento *</label>

                                <?php if ($painelAberto === 'novo'): ?>

                                    <select id="evento_id" name="evento_id" required>
                                        <option value="">Selecione o evento</option>
                                        <?php foreach ($eventos as $evento): ?>
                                            <option
                                                value="<?= (int) $evento['id'] ?>"
                                                <?= (string) $evento['id'] === $valoresForm['evento_id'] ? 'selected' : '' ?>
                                            >
                                                <?= escapar($evento['titulo'] ?? ('Evento #' . $evento['id'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php else: ?>

                                    <div class="field-static">
                                        <?= escapar($valoresForm['evento_titulo']) ?>
                                    </div>
                                    <input type="hidden" name="evento_id" value="<?= escapar($valoresForm['evento_id']) ?>">

                                <?php endif; ?>

                            </div>

                            <div class="field field-full">
                                <label for="titulo">Título *</label>
                                <input
                                    id="titulo"
                                    name="titulo"
                                    type="text"
                                    value="<?= escapar($valoresForm['titulo']) ?>"
                                    placeholder="Ex.: Confirmar transporte de obras"
                                    required
                                >
                            </div>

                            <div class="field field-full">
                                <label for="descricao">Descrição</label>
                                <textarea
                                    id="descricao"
                                    name="descricao"
                                    placeholder="Detalhes da tarefa (opcional)"
                                ><?= escapar($valoresForm['descricao']) ?></textarea>
                            </div>

                            <div class="field">
                                <label for="usuario_responsavel_id">Responsável</label>
                                <select id="usuario_responsavel_id" name="usuario_responsavel_id">
                                    <option value="">Sem responsável definido</option>
                                    <?php foreach ($usuariosDisponiveis as $usuarioOpcao): ?>
                                        <option
                                            value="<?= (int) $usuarioOpcao['id'] ?>"
                                            <?= (string) $usuarioOpcao['id'] === $valoresForm['usuario_responsavel_id'] ? 'selected' : '' ?>
                                        >
                                            <?= escapar($usuarioOpcao['nome'] ?? '') ?>
                                            (<?= escapar($usuarioOpcao['perfil'] ?? '') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="etapa_id">Etapa</label>
                                <select id="etapa_id" name="etapa_id">
                                    <option value="">Sem etapa</option>
                                    <?php foreach ($etapasOrdenadas as $etapa): ?>
                                        <option
                                            value="<?= (int) $etapa['id'] ?>"
                                            <?= (string) $etapa['id'] === $valoresForm['etapa_id'] ? 'selected' : '' ?>
                                        >
                                            <?= escapar($etapa['nome'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="categoria_id">Categoria</label>
                                <select id="categoria_id" name="categoria_id">
                                    <option value="">Sem categoria</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option
                                            value="<?= (int) $categoria['id'] ?>"
                                            <?= (string) $categoria['id'] === $valoresForm['categoria_id'] ? 'selected' : '' ?>
                                        >
                                            <?= escapar($categoria['nome'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="prazo">Prazo</label>
                                <input
                                    id="prazo"
                                    name="prazo"
                                    type="date"
                                    value="<?= escapar($valoresForm['prazo']) ?>"
                                >
                            </div>

                            <div class="field">
                                <label for="prioridade">Prioridade *</label>
                                <select id="prioridade" name="prioridade" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (PRIORIDADES_VALIDAS as $prioridadeOpcao): ?>
                                        <option
                                            value="<?= $prioridadeOpcao ?>"
                                            <?= $prioridadeOpcao === $valoresForm['prioridade'] ? 'selected' : '' ?>
                                        >
                                            <?= escapar(traduzirPrioridade($prioridadeOpcao)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="status">Status *</label>
                                <select id="status" name="status" required>
                                    <?php foreach (STATUS_VALIDOS as $statusOpcao): ?>
                                        <option
                                            value="<?= $statusOpcao ?>"
                                            <?= $statusOpcao === $valoresForm['status'] ? 'selected' : '' ?>
                                        >
                                            <?= escapar(traduzirStatusTarefa($statusOpcao)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field field-full">
                                <label for="observacoes">Observações</label>
                                <textarea
                                    id="observacoes"
                                    name="observacoes"
                                    placeholder="Observações adicionais (opcional)"
                                ><?= escapar($valoresForm['observacoes']) ?></textarea>
                            </div>

                        </div>

                        <div class="form-actions">
                            <a href="tarefas.php" class="secondary-button">Cancelar</a>
                            <button type="submit" class="primary-button">
                                <?= $painelAberto === 'novo' ? 'Criar tarefa' : 'Salvar alterações' ?>
                            </button>
                        </div>

                    </form>

                </article>

            <?php endif; ?>

            <?php if (count($eventos) > 1): ?>

                <form method="get" action="" class="filter-bar">

                    <div class="filter-field">
                        <label for="filtroEvento">Evento</label>
                        <select id="filtroEvento" name="evento_id">
                            <option value="0">Todos os eventos</option>
                            <?php foreach ($eventos as $evento): ?>
                                <option
                                    value="<?= (int) $evento['id'] ?>"
                                    <?= $filtroEventoId === (int) $evento['id'] ? 'selected' : '' ?>
                                >
                                    <?= escapar($evento['titulo'] ?? ('Evento #' . $evento['id'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="secondary-button">Filtrar →</button>

                    <?php if ($filtroEventoId > 0): ?>
                        <a href="tarefas.php" class="text-link">Limpar filtro</a>
                    <?php endif; ?>

                </form>

            <?php endif; ?>

            <article class="panel">

                <div class="panel-header">
                    <div>
                        <p class="eyebrow">TODAS AS TAREFAS</p>
                        <h2>Lista de tarefas</h2>
                    </div>
                    <span class="panel-count"><?= count($tarefas) ?></span>
                </div>

                <?php if ($tarefas === []): ?>

                    <div class="empty-state">
                        <div class="empty-icon">✓</div>
                        <p>
                            <?= $apiIndisponivel
                                ? 'Não foi possível carregar as tarefas agora.'
                                : 'Nenhuma tarefa encontrada.' ?>
                        </p>
                    </div>

                <?php else: ?>

                    <div class="tarefas-table-wrapper">

                        <table class="tarefas-table">

                            <thead>
                                <tr>
                                    <th>Evento</th>
                                    <th>Tarefa</th>
                                    <th>Responsável</th>
                                    <th>Etapa</th>
                                    <th>Categoria</th>
                                    <th>Prazo</th>
                                    <th>Prioridade</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($tarefas as $tarefa): ?>

                                    <?php
                                    $responsavelId = $tarefa['usuario_responsavel_id'] ?? null;
                                    $ehResponsavel = $responsavelId !== null
                                        && (int) $responsavelId === $usuarioId;
                                    $podeAlterarStatus = $podeGerenciarTarefas || $ehResponsavel;
                                    $status = (string) ($tarefa['status'] ?? '');

                                    // Atraso é calculado aqui, na exibição — não altera nem
                                    // deriva a prioridade armazenada na tarefa.
                                    $prazoAtrasado = !empty($tarefa['prazo'])
                                        && (string) $tarefa['prazo'] < date('Y-m-d')
                                        && !in_array($status, ['CONCLUIDA', 'CANCELADA'], true);
                                    ?>

                                    <tr
                                        class="searchable-item"
                                        data-search="<?= escapar(
                                            ($tarefa['titulo'] ?? '')
                                            . ' '
                                            . ($tarefa['evento_titulo'] ?? '')
                                            . ' '
                                            . ($tarefa['usuario_responsavel_nome'] ?? '')
                                        ) ?>"
                                    >
                                        <td><a href="evento.php?id=<?= (int) ($tarefa['evento_id'] ?? 0) ?>" class="evento-link"><?= escapar($tarefa['evento_titulo'] ?? '—') ?></a></td>

                                        <td class="tarefa-titulo">
                                            <strong><?= escapar($tarefa['titulo'] ?? 'Sem título') ?></strong>
                                            <?php if (!empty($tarefa['descricao'])): ?>
                                                <span><?= escapar($tarefa['descricao']) ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <td><?= escapar($tarefa['responsavel_exibicao'] ?? $tarefa['usuario_responsavel_nome'] ?? '—') ?></td>

                                        <td><?= escapar($tarefa['etapa_nome'] ?? '—') ?></td>

                                        <td><?= escapar($tarefa['categoria_nome'] ?? '—') ?></td>

                                        <td>
                                            <?php if ($prazoAtrasado): ?>
                                                <span class="prazo-atrasado">
                                                    <?= escapar(formatarData($tarefa['prazo'] ?? null)) ?>
                                                    <small>Atrasada</small>
                                                </span>
                                            <?php else: ?>
                                                <?= escapar(formatarData($tarefa['prazo'] ?? null)) ?>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="priority-wrapper">
                                                <strong class="<?= escapar(classePrioridade((string) ($tarefa['prioridade'] ?? ''))) ?>">
                                                    <?= escapar(traduzirPrioridade((string) ($tarefa['prioridade'] ?? ''))) ?>
                                                </strong>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= escapar(classeStatusTarefa($status)) ?>">
                                                <?= escapar(traduzirStatusTarefa($status)) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="table-actions">

                                                <?php if ($podeGerenciarTarefas): ?>
                                                    <a
                                                        class="text-link"
                                                        href="?painel=editar&evento_id=<?= (int) ($tarefa['evento_id'] ?? 0) ?>&id=<?= (int) ($tarefa['id'] ?? 0) ?>"
                                                    >
                                                        Editar
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($podeAlterarStatus): ?>

                                                    <form method="post" action="" class="status-form">
                                                        <?= \Elos\Frontend\campoCsrf() ?>
                                                        <input type="hidden" name="acao" value="status">
                                                        <input type="hidden" name="evento_id" value="<?= (int) ($tarefa['evento_id'] ?? 0) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) ($tarefa['id'] ?? 0) ?>">

                                                        <select name="status">
                                                            <?php foreach (STATUS_VALIDOS as $statusOpcao): ?>
                                                                <option
                                                                    value="<?= $statusOpcao ?>"
                                                                    <?= $statusOpcao === $status ? 'selected' : '' ?>
                                                                >
                                                                    <?= escapar(traduzirStatusTarefa($statusOpcao)) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>

                                                        <button type="submit" class="link-button">
                                                            Atualizar
                                                        </button>
                                                    </form>

                                                <?php else: ?>

                                                    <span class="text-muted">Somente visualização</span>

                                                <?php endif; ?>

                                            </div>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </article>

        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('tarefasSearch');

    if (!searchInput) {
        return;
    }

    const items = document.querySelectorAll('.searchable-item');

    searchInput.addEventListener('input', function () {
        const termo = searchInput.value.trim().toLowerCase();

        items.forEach(function (item) {
            const texto = item.dataset.search.toLowerCase();

            item.style.display = !termo || texto.includes(termo)
                ? ''
                : 'none';
        });
    });
});
</script>

</body>
</html>
