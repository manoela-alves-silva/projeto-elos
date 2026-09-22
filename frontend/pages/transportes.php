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

$nomeUsuario = (string) ($usuario['nome'] ?? 'Usuário');
$perfilUsuario = (string) ($usuario['perfil'] ?? 'COLABORADOR');
$primeiroNome = explode(' ', trim($nomeUsuario))[0] ?: $nomeUsuario;

// GESTOR/ADMIN: podem criar/editar transportes (mesma regra do
// backend, AuthorizationService::hasRole — POST/PUT exigem GESTOR
// em transporte_routes.php; não é uma matriz nova).
$podeGerenciarTransportes = in_array($perfilUsuario, ['ADMIN', 'GESTOR'], true);

const TIPOS_TRANSPORTE = ['OBRAS', 'MATERIAIS', 'EQUIPAMENTOS', 'DEVOLUCAO', 'OUTRO'];
const STATUS_TRANSPORTE_VALIDOS = ['NAO_SOLICITADO', 'SOLICITADO', 'AGENDADO', 'REALIZADO', 'CANCELADO'];

function escapar(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function buscarApiListas(string $path): ?array
{
    try {
        return Api::get($path);
    } catch (ApiException) {
        return null;
    }
}

function formatarDataHora(?string $data, ?string $horario): string
{
    if (!$data) {
        return 'Data não definida';
    }

    $timestamp = strtotime($data);
    $dataFormatada = $timestamp === false ? $data : date('d/m/Y', $timestamp);

    if ($horario) {
        return $dataFormatada . ' às ' . substr($horario, 0, 5);
    }

    return $dataFormatada;
}

function traduzirTipoTransporte(string $tipo): string
{
    return match ($tipo) {
        'OBRAS' => 'Obras',
        'MATERIAIS' => 'Materiais',
        'EQUIPAMENTOS' => 'Equipamentos',
        'DEVOLUCAO' => 'Devolução',
        'OUTRO' => 'Outro',
        default => $tipo,
    };
}

function traduzirStatusTransporte(string $status): string
{
    return match ($status) {
        'NAO_SOLICITADO' => 'Não solicitado',
        'SOLICITADO' => 'Solicitado',
        'AGENDADO' => 'Agendado',
        'REALIZADO' => 'Realizado',
        'CANCELADO' => 'Cancelado',
        default => $status,
    };
}

function classeStatusTransporte(string $status): string
{
    return match ($status) {
        'SOLICITADO' => 'transporte-solicitado',
        'AGENDADO' => 'transporte-agendado',
        'REALIZADO' => 'transporte-realizado',
        'CANCELADO' => 'transporte-cancelado',
        default => 'transporte-nao-solicitado',
    };
}

/**
 * Monta o corpo enviado para criação/edição a partir do POST,
 * convertendo campos vazios em null (campos opcionais no backend).
 *
 * @return array<string, mixed>
 */
function montarCorpoTransporte(array $post): array
{
    $origem = trim((string) ($post['origem'] ?? ''));
    $destino = trim((string) ($post['destino'] ?? ''));
    $dataSolicitacao = trim((string) ($post['data_solicitacao'] ?? ''));
    $dataTransporte = trim((string) ($post['data_transporte'] ?? ''));
    $horario = trim((string) ($post['horario'] ?? ''));
    $observacoes = trim((string) ($post['observacoes'] ?? ''));

    return [
        'tipo' => (string) ($post['tipo'] ?? ''),
        'origem' => $origem,
        'destino' => $destino,
        'data_solicitacao' => $dataSolicitacao !== '' ? $dataSolicitacao : null,
        'data_transporte' => $dataTransporte !== '' ? $dataTransporte : null,
        'horario' => $horario !== '' ? $horario : null,
        'status' => (string) ($post['status'] ?? ''),
        'observacoes' => $observacoes !== '' ? $observacoes : null,
    ];
}

/**
 * Valida os campos comuns de criação/edição de transporte.
 * Retorna uma mensagem de erro, ou string vazia se estiver tudo certo.
 */
function validarCamposTransporte(array $corpo, int $eventoId): string
{
    if ($eventoId <= 0) {
        return 'Selecione o evento do transporte.';
    }

    if (!in_array($corpo['tipo'], TIPOS_TRANSPORTE, true)) {
        return 'Selecione um tipo de transporte válido.';
    }

    if ($corpo['origem'] === '') {
        return 'Origem é obrigatória.';
    }

    if ($corpo['destino'] === '') {
        return 'Destino é obrigatório.';
    }

    if (!in_array($corpo['status'], STATUS_TRANSPORTE_VALIDOS, true)) {
        return 'Selecione um status válido.';
    }

    return '';
}

$mensagemErro = '';
$mensagemSucesso = '';
$acaoPost = '';

// --- Processa o POST (criar / editar) antes de carregar a listagem,
// já com Post/Redirect/Get: o formulário usa action="", que reenvia
// para a URL atual incluindo a querystring (?painel=novo) — sem o
// redirect no sucesso, o painel ficaria preso aberto (mesmo problema
// já corrigido em "Nova tarefa"). ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acaoPost = (string) ($_POST['acao'] ?? '');

    if ($acaoPost === 'criar' || $acaoPost === 'editar') {
        if (!$podeGerenciarTransportes) {
            $mensagemErro = 'Você não tem permissão para gerenciar transportes.';
        } else {
            $eventoIdPost = (int) ($_POST['evento_id'] ?? 0);
            $idPost = (int) ($_POST['id'] ?? 0);
            $corpo = montarCorpoTransporte($_POST);

            $mensagemErro = ($acaoPost === 'editar' && $idPost <= 0)
                ? 'Não foi possível identificar o transporte a editar.'
                : validarCamposTransporte($corpo, $eventoIdPost);

            if ($mensagemErro === '') {
                try {
                    if ($acaoPost === 'criar') {
                        Api::post('/api/eventos/' . $eventoIdPost . '/transportes', $corpo);
                        $mensagem = 'Transporte criado com sucesso.';
                    } else {
                        Api::put('/api/eventos/' . $eventoIdPost . '/transportes/' . $idPost, $corpo);
                        $mensagem = 'Transporte atualizado com sucesso.';
                    }

                    $_SESSION['_transportes_flash_sucesso'] = $mensagem;
                    header('Location: transportes.php');
                    exit();
                } catch (ApiException $e) {
                    $mensagemErro = $e->getMessage();
                }
            }
        }
    } else {
        $mensagemErro = 'Ação inválida.';
    }
}

if ($mensagemSucesso === '' && isset($_SESSION['_transportes_flash_sucesso'])) {
    $mensagemSucesso = $_SESSION['_transportes_flash_sucesso'];
    unset($_SESSION['_transportes_flash_sucesso']);
}

// --- Carrega eventos e os transportes de cada um pela API ---
$dadosEventos = buscarApiListas('/api/eventos');
$apiIndisponivel = $dadosEventos === null;
$eventos = is_array($dadosEventos) ? ($dadosEventos['eventos'] ?? []) : [];
if (!is_array($eventos)) {
    $eventos = [];
}

// Não existe endpoint "/api/transportes" global — assim como em
// Tarefas, transportes só existem aninhados em
// "/api/eventos/{id}/transportes". Agregamos por evento.
$transportes = [];

foreach ($eventos as $evento) {
    if (!is_array($evento) || !isset($evento['id'])) {
        continue;
    }

    $dadosTransportesEvento = buscarApiListas('/api/eventos/' . (int) $evento['id'] . '/transportes');
    $transportesEvento = is_array($dadosTransportesEvento) ? ($dadosTransportesEvento['transportes'] ?? []) : [];

    if (!is_array($transportesEvento)) {
        continue;
    }

    foreach ($transportesEvento as $transporte) {
        if (!is_array($transporte)) {
            continue;
        }

        $transporte['evento_titulo'] = $evento['titulo'] ?? ('Evento #' . $evento['id']);
        $transportes[] = $transporte;
    }
}

usort(
    $transportes,
    static function (array $a, array $b): int {
        $dataA = strtotime((string) ($a['data_transporte'] ?? '9999-12-31'));
        $dataB = strtotime((string) ($b['data_transporte'] ?? '9999-12-31'));

        return $dataA <=> $dataB;
    }
);

// --- Estado do painel (formulário de criação/edição) ---
$painelAberto = (string) ($_GET['painel'] ?? '');
if (!in_array($painelAberto, ['novo', 'editar'], true)) {
    $painelAberto = '';
}

if ($painelAberto !== '' && !$podeGerenciarTransportes) {
    $painelAberto = '';
}

if ($mensagemErro !== '' && $acaoPost === 'criar') {
    $painelAberto = 'novo';
}

if ($mensagemErro !== '' && $acaoPost === 'editar') {
    $painelAberto = 'editar';
}

$transporteEditando = null;

if ($painelAberto === 'editar') {
    $eventoIdEdit = (int) ($_GET['evento_id'] ?? ($_POST['evento_id'] ?? 0));
    $idEdit = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));

    foreach ($transportes as $t) {
        if (
            (int) ($t['evento_id'] ?? 0) === $eventoIdEdit
            && (int) ($t['id'] ?? 0) === $idEdit
        ) {
            $transporteEditando = $t;
            break;
        }
    }

    if ($transporteEditando === null) {
        $painelAberto = '';

        if ($mensagemErro === '') {
            $mensagemErro = 'Transporte não encontrado para edição.';
        }
    }
}

$valoresForm = [
    'evento_id' => '',
    'evento_titulo' => '',
    'id' => '',
    'tipo' => '',
    'origem' => '',
    'destino' => '',
    'data_solicitacao' => '',
    'data_transporte' => '',
    'horario' => '',
    'status' => 'SOLICITADO',
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
        $valoresForm['evento_titulo'] = (string) ($transporteEditando['evento_titulo'] ?? '');
    } elseif ($transporteEditando !== null) {
        $valoresForm = [
            'evento_id' => (string) ($transporteEditando['evento_id'] ?? ''),
            'evento_titulo' => (string) ($transporteEditando['evento_titulo'] ?? ''),
            'id' => (string) ($transporteEditando['id'] ?? ''),
            'tipo' => (string) ($transporteEditando['tipo'] ?? ''),
            'origem' => (string) ($transporteEditando['origem'] ?? ''),
            'destino' => (string) ($transporteEditando['destino'] ?? ''),
            'data_solicitacao' => (string) ($transporteEditando['data_solicitacao'] ?? ''),
            'data_transporte' => (string) ($transporteEditando['data_transporte'] ?? ''),
            'horario' => (string) ($transporteEditando['horario'] ?? ''),
            'status' => (string) ($transporteEditando['status'] ?? ''),
            'observacoes' => (string) ($transporteEditando['observacoes'] ?? ''),
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Transportes | ELOS</title>

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
    <link rel="stylesheet" href="../assets/css/listas.css">
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
                    id="listaSearch"
                    placeholder="Buscar transportes..."
                    aria-label="Buscar transportes"
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
                    <p class="eyebrow">LOGÍSTICA</p>
                    <h1>
                        Transportes
                        <span>Obras, materiais, equipamentos e devoluções de cada evento.</span>
                    </h1>
                </div>

                <?php if ($podeGerenciarTransportes && $painelAberto === ''): ?>
                    <a href="?painel=novo" class="primary-button">
                        <span>+</span>
                        Novo transporte
                    </a>
                <?php endif; ?>

            </section>

            <?php if ($painelAberto === 'novo' || $painelAberto === 'editar'): ?>

                <article class="panel transporte-form">

                    <div class="panel-header">
                        <div>
                            <p class="eyebrow">
                                <?= $painelAberto === 'novo' ? 'NOVO TRANSPORTE' : 'EDITAR TRANSPORTE' ?>
                            </p>
                            <h2>
                                <?= $painelAberto === 'novo'
                                    ? 'Cadastrar transporte'
                                    : 'Editar transporte #' . (int) $valoresForm['id'] ?>
                            </h2>
                        </div>
                        <a href="transportes.php" class="text-link">Cancelar</a>
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

                            <div class="field">
                                <label for="tipo">Tipo/operação *</label>
                                <select id="tipo" name="tipo" required>
                                    <option value="">Selecione</option>
                                    <?php foreach (TIPOS_TRANSPORTE as $tipoOpcao): ?>
                                        <option
                                            value="<?= $tipoOpcao ?>"
                                            <?= $tipoOpcao === $valoresForm['tipo'] ? 'selected' : '' ?>
                                        >
                                            <?= escapar(traduzirTipoTransporte($tipoOpcao)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="status">Status *</label>
                                <select id="status" name="status" required>
                                    <?php foreach (STATUS_TRANSPORTE_VALIDOS as $statusOpcao): ?>
                                        <option
                                            value="<?= $statusOpcao ?>"
                                            <?= $statusOpcao === $valoresForm['status'] ? 'selected' : '' ?>
                                        >
                                            <?= escapar(traduzirStatusTransporte($statusOpcao)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="origem">Origem *</label>
                                <input
                                    id="origem"
                                    name="origem"
                                    type="text"
                                    value="<?= escapar($valoresForm['origem']) ?>"
                                    placeholder="Ex.: Depósito Central"
                                    required
                                >
                            </div>

                            <div class="field">
                                <label for="destino">Destino *</label>
                                <input
                                    id="destino"
                                    name="destino"
                                    type="text"
                                    value="<?= escapar($valoresForm['destino']) ?>"
                                    placeholder="Ex.: Galeria de Arte La Salle"
                                    required
                                >
                            </div>

                            <div class="field">
                                <label for="data_solicitacao">Data da solicitação</label>
                                <input
                                    id="data_solicitacao"
                                    name="data_solicitacao"
                                    type="date"
                                    value="<?= escapar($valoresForm['data_solicitacao']) ?>"
                                >
                            </div>

                            <div class="field">
                                <label for="data_transporte">Data do transporte</label>
                                <input
                                    id="data_transporte"
                                    name="data_transporte"
                                    type="date"
                                    value="<?= escapar($valoresForm['data_transporte']) ?>"
                                >
                            </div>

                            <div class="field">
                                <label for="horario">Horário</label>
                                <input
                                    id="horario"
                                    name="horario"
                                    type="time"
                                    value="<?= escapar(substr($valoresForm['horario'], 0, 5)) ?>"
                                >
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
                            <a href="transportes.php" class="secondary-button">Cancelar</a>
                            <button type="submit" class="primary-button">
                                <?= $painelAberto === 'novo' ? 'Criar transporte' : 'Salvar alterações' ?>
                            </button>
                        </div>

                    </form>

                </article>

            <?php endif; ?>

            <article class="panel">

                <div class="panel-header">
                    <div>
                        <p class="eyebrow">TODOS OS TRANSPORTES</p>
                        <h2>Lista de transportes</h2>
                    </div>
                    <span class="panel-count"><?= count($transportes) ?></span>
                </div>

                <?php if ($transportes === []): ?>

                    <div class="empty-state">
                        <div class="empty-icon">▱</div>
                        <p>
                            <?= $apiIndisponivel
                                ? 'Não foi possível carregar os transportes agora.'
                                : 'Nenhum transporte cadastrado.' ?>
                        </p>
                    </div>

                <?php else: ?>

                    <div class="listas-table-wrapper">

                        <table class="listas-table">

                            <thead>
                                <tr>
                                    <th>Evento</th>
                                    <th>Operação</th>
                                    <th>Origem → Destino</th>
                                    <th>Data de solicitação</th>
                                    <th>Data/horário do transporte</th>
                                    <th>Status</th>
                                    <?php if ($podeGerenciarTransportes): ?>
                                        <th>Ações</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($transportes as $transporte): ?>

                                    <tr
                                        class="searchable-item"
                                        data-search="<?= escapar(
                                            ($transporte['evento_titulo'] ?? '')
                                            . ' '
                                            . ($transporte['origem'] ?? '')
                                            . ' '
                                            . ($transporte['destino'] ?? '')
                                        ) ?>"
                                    >
                                        <td><a href="evento.php?id=<?= (int) ($transporte['evento_id'] ?? 0) ?>" class="evento-link"><?= escapar($transporte['evento_titulo'] ?? '—') ?></a></td>

                                        <td class="listas-titulo">
                                            <strong><?= escapar(traduzirTipoTransporte((string) ($transporte['tipo'] ?? ''))) ?></strong>
                                        </td>

                                        <td><?= escapar(($transporte['origem'] ?? '—') . ' → ' . ($transporte['destino'] ?? '—')) ?></td>

                                        <td><?= escapar(formatarDataHora($transporte['data_solicitacao'] ?? null, null)) ?></td>

                                        <td><?= escapar(formatarDataHora($transporte['data_transporte'] ?? null, $transporte['horario'] ?? null)) ?></td>

                                        <td>
                                            <span class="status-badge <?= escapar(classeStatusTransporte((string) ($transporte['status'] ?? ''))) ?>">
                                                <?= escapar(traduzirStatusTransporte((string) ($transporte['status'] ?? ''))) ?>
                                            </span>
                                        </td>

                                        <?php if ($podeGerenciarTransportes): ?>
                                            <td>
                                                <a
                                                    class="text-link"
                                                    href="?painel=editar&evento_id=<?= (int) ($transporte['evento_id'] ?? 0) ?>&id=<?= (int) ($transporte['id'] ?? 0) ?>"
                                                >
                                                    Editar
                                                </a>
                                            </td>
                                        <?php endif; ?>

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
    const searchInput = document.getElementById('listaSearch');

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
