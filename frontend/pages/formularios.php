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

// GESTOR/ADMIN: podem criar/editar formulários (mesma regra do
// backend, AuthorizationService::hasRole — POST/PUT exigem GESTOR
// em formulario_routes.php; não é uma matriz nova).
$podeGerenciarFormularios = in_array($perfilUsuario, ['ADMIN', 'GESTOR'], true);

// Mesma lista de FormularioController::isValidStatus().
const STATUS_FORMULARIO_VALIDOS = ['PENDENTE', 'EM_PREPARACAO', 'ENVIADO', 'ATRASADO', 'CANCELADO'];

// Coluna formularios.tipo é VARCHAR(100).
const TIPO_FORMULARIO_MAX = 100;

/**
 * "Atrasado" não se escolhe: é o que ainda não foi enviado depois da
 * data prevista.
 */
function statusEfetivoFormulario(array $formulario): string
{
    $status = (string) ($formulario['status'] ?? '');

    if (in_array($status, ['ENVIADO', 'CANCELADO'], true)) {
        return $status;
    }

    $previsao = (string) ($formulario['data_previsao'] ?? '');

    if ($previsao !== '' && $previsao < date('Y-m-d')) {
        return 'ATRASADO';
    }

    return $status === 'ATRASADO' ? 'PENDENTE' : $status;
}

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

function formatarDataCurta(?string $data): string
{
    if (!$data) {
        return 'Não definida';
    }

    $timestamp = strtotime($data);

    return $timestamp === false ? $data : date('d/m/Y', $timestamp);
}

function traduzirStatusFormulario(string $status): string
{
    return match ($status) {
        'PENDENTE' => 'Pendente',
        'EM_PREPARACAO' => 'Em preparação',
        'ENVIADO' => 'Enviado',
        'ATRASADO' => 'Atrasado',
        'CANCELADO' => 'Cancelado',
        default => $status,
    };
}

function classeStatusFormulario(string $status): string
{
    return match ($status) {
        'EM_PREPARACAO' => 'formulario-em-preparacao',
        'ENVIADO' => 'formulario-enviado',
        'ATRASADO' => 'formulario-atrasado',
        'CANCELADO' => 'formulario-cancelado',
        default => 'formulario-pendente',
    };
}

/**
 * Monta o corpo enviado para criação/edição a partir do POST,
 * convertendo campos vazios em null (campos opcionais no backend).
 *
 * @return array<string, mixed>
 */
function montarCorpoFormulario(array $post): array
{
    $dataPrevisao = trim((string) ($post['data_previsao'] ?? ''));
    $dataEnvio = trim((string) ($post['data_envio'] ?? ''));
    $observacoes = trim((string) ($post['observacoes'] ?? ''));

    return [
        'tipo' => trim((string) ($post['tipo'] ?? '')),
        'data_previsao' => $dataPrevisao !== '' ? $dataPrevisao : null,
        'data_envio' => $dataEnvio !== '' ? $dataEnvio : null,
        'status' => (string) ($post['status'] ?? ''),
        'observacoes' => $observacoes !== '' ? $observacoes : null,
    ];
}

/**
 * Aceita somente datas reais no formato do <input type="date">
 * (AAAA-MM-DD) — o backend não valida o formato.
 */
function dataValida(?string $data): bool
{
    if ($data === null) {
        return true;
    }

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $partes)) {
        return false;
    }

    return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
}

/**
 * Valida os campos comuns de criação/edição de formulário.
 * Retorna uma mensagem de erro, ou string vazia se estiver tudo certo.
 */
function validarCamposFormulario(array $corpo, int $eventoId): string
{
    if ($eventoId <= 0) {
        return 'Selecione o evento do formulário.';
    }

    if ($corpo['tipo'] === '') {
        return 'Tipo do formulário é obrigatório.';
    }

    if (mb_strlen($corpo['tipo']) > TIPO_FORMULARIO_MAX) {
        return 'Tipo do formulário deve ter no máximo ' . TIPO_FORMULARIO_MAX . ' caracteres.';
    }

    if (!dataValida($corpo['data_previsao'])) {
        return 'Data de previsão inválida.';
    }

    if (!dataValida($corpo['data_envio'])) {
        return 'Data de envio inválida.';
    }

    if (!in_array($corpo['status'], STATUS_FORMULARIO_VALIDOS, true)) {
        return 'Selecione um status válido.';
    }

    return '';
}

$mensagemErro = '';
$mensagemSucesso = '';
$acaoPost = '';

// --- Processa o POST (criar / editar) antes de carregar a listagem,
// com Post/Redirect/Get — mesmo padrão já validado em Transportes. ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acaoPost = (string) ($_POST['acao'] ?? '');

    if ($acaoPost === 'criar' || $acaoPost === 'editar') {
        if (!$podeGerenciarFormularios) {
            $mensagemErro = 'Você não tem permissão para gerenciar formulários.';
        } else {
            $eventoIdPost = (int) ($_POST['evento_id'] ?? 0);
            $idPost = (int) ($_POST['id'] ?? 0);
            $corpo = montarCorpoFormulario($_POST);

            $mensagemErro = ($acaoPost === 'editar' && $idPost <= 0)
                ? 'Não foi possível identificar o formulário a editar.'
                : validarCamposFormulario($corpo, $eventoIdPost);

            if ($mensagemErro === '' && $acaoPost === 'criar') {
                // Evento inexistente faz o backend estourar a FK com um
                // Fatal error em HTTP 200 (que o Api.php leria como
                // sucesso) — por isso conferimos antes de enviar.
                $dadosEventosPost = buscarApiListas('/api/eventos');
                $idsEventos = array_map(
                    static fn (array $e): int => (int) ($e['id'] ?? 0),
                    array_filter($dadosEventosPost['eventos'] ?? [], 'is_array')
                );

                if (!in_array($eventoIdPost, $idsEventos, true)) {
                    $mensagemErro = 'Evento selecionado não encontrado.';
                }
            }

            if ($mensagemErro === '' && $acaoPost === 'editar') {
                // O PUT do backend usa "?? valor existente": enviar null
                // mantém a data antiga. Em vez de fingir que apagou,
                // avisamos a usuária.
                try {
                    $dadosAtual = Api::get('/api/eventos/' . $eventoIdPost . '/formularios/' . $idPost);
                    $atual = is_array($dadosAtual['formulario'] ?? null) ? $dadosAtual['formulario'] : [];

                    foreach (['data_previsao' => 'previsão', 'data_envio' => 'envio'] as $campoData => $rotulo) {
                        if ($corpo[$campoData] === null && !empty($atual[$campoData])) {
                            $mensagemErro = 'A data de ' . $rotulo . ' já registrada não pode ser removida, apenas alterada.';
                            break;
                        }
                    }
                } catch (ApiException $e) {
                    $mensagemErro = $e->getMessage();
                }
            }

            if ($mensagemErro === '') {
                try {
                    if ($acaoPost === 'criar') {
                        Api::post('/api/eventos/' . $eventoIdPost . '/formularios', $corpo);
                        $mensagem = 'Formulário criado com sucesso.';
                    } else {
                        Api::put('/api/eventos/' . $eventoIdPost . '/formularios/' . $idPost, $corpo);
                        $mensagem = 'Formulário atualizado com sucesso.';
                    }

                    $_SESSION['_formularios_flash_sucesso'] = $mensagem;
                    header('Location: formularios.php');
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

if ($mensagemSucesso === '' && isset($_SESSION['_formularios_flash_sucesso'])) {
    $mensagemSucesso = $_SESSION['_formularios_flash_sucesso'];
    unset($_SESSION['_formularios_flash_sucesso']);
}

// --- Carrega eventos e os formulários de cada um pela API ---
$dadosEventos = buscarApiListas('/api/eventos');
$apiIndisponivel = $dadosEventos === null;
$eventos = is_array($dadosEventos) ? ($dadosEventos['eventos'] ?? []) : [];
if (!is_array($eventos)) {
    $eventos = [];
}

// Não existe endpoint "/api/formularios" global — formulários só
// existem aninhados em "/api/eventos/{id}/formularios". Agregamos
// por evento, mesmo padrão já usado em Tarefas/Transportes/Visitas.
$formularios = [];

foreach ($eventos as $evento) {
    if (!is_array($evento) || !isset($evento['id'])) {
        continue;
    }

    $dadosFormulariosEvento = buscarApiListas('/api/eventos/' . (int) $evento['id'] . '/formularios');
    $formulariosEvento = is_array($dadosFormulariosEvento) ? ($dadosFormulariosEvento['formularios'] ?? []) : [];

    if (!is_array($formulariosEvento)) {
        continue;
    }

    foreach ($formulariosEvento as $formulario) {
        if (!is_array($formulario)) {
            continue;
        }

        $formulario['evento_titulo'] = $evento['titulo'] ?? ('Evento #' . $evento['id']);
        $formularios[] = $formulario;
    }
}

usort(
    $formularios,
    static function (array $a, array $b): int {
        $dataA = strtotime((string) ($a['data_previsao'] ?? '9999-12-31'));
        $dataB = strtotime((string) ($b['data_previsao'] ?? '9999-12-31'));

        return $dataA <=> $dataB;
    }
);

// --- Estado do painel (formulário de criação/edição) ---
$painelAberto = (string) ($_GET['painel'] ?? '');
if (!in_array($painelAberto, ['novo', 'editar'], true)) {
    $painelAberto = '';
}

if ($painelAberto !== '' && !$podeGerenciarFormularios) {
    $painelAberto = '';
}

if ($mensagemErro !== '' && $acaoPost === 'criar') {
    $painelAberto = 'novo';
}

if ($mensagemErro !== '' && $acaoPost === 'editar') {
    $painelAberto = 'editar';
}

$formularioEditando = null;

if ($painelAberto === 'editar') {
    $eventoIdEdit = (int) ($_GET['evento_id'] ?? ($_POST['evento_id'] ?? 0));
    $idEdit = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));

    foreach ($formularios as $f) {
        if (
            (int) ($f['evento_id'] ?? 0) === $eventoIdEdit
            && (int) ($f['id'] ?? 0) === $idEdit
        ) {
            $formularioEditando = $f;
            break;
        }
    }

    if ($formularioEditando === null) {
        $painelAberto = '';

        if ($mensagemErro === '') {
            $mensagemErro = 'Formulário não encontrado para edição.';
        }
    }
}

$valoresForm = [
    'evento_id' => '',
    'evento_titulo' => '',
    'id' => '',
    'tipo' => '',
    'data_previsao' => '',
    'data_envio' => '',
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
        $valoresForm['evento_titulo'] = (string) ($formularioEditando['evento_titulo'] ?? '');
    } elseif ($formularioEditando !== null) {
        foreach ($valoresForm as $campo => $default) {
            $valoresForm[$campo] = (string) ($formularioEditando[$campo] ?? $default);
        }
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

    <title>Formulários | ELOS</title>

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
                    placeholder="Buscar formulários..."
                    aria-label="Buscar formulários"
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
                    <p class="eyebrow">DOCUMENTAÇÃO</p>
                    <h1>
                        Formulários
                        <span>Acompanhe previsão e envio dos formulários de cada evento.</span>
                    </h1>
                </div>

                <?php if ($podeGerenciarFormularios && $painelAberto === ''): ?>
                    <a href="?painel=novo" class="primary-button">
                        <span>+</span>
                        Novo formulário
                    </a>
                <?php endif; ?>

            </section>

            <?php if ($painelAberto === 'novo' || $painelAberto === 'editar'): ?>

                <article class="panel formulario-form">

                    <div class="panel-header">
                        <div>
                            <p class="eyebrow">
                                <?= $painelAberto === 'novo' ? 'NOVO FORMULÁRIO' : 'EDITAR FORMULÁRIO' ?>
                            </p>
                            <h2>
                                <?= $painelAberto === 'novo'
                                    ? 'Cadastrar formulário'
                                    : 'Editar formulário #' . (int) $valoresForm['id'] ?>
                            </h2>
                        </div>
                        <a href="formularios.php" class="text-link">Cancelar</a>
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
                                <label for="tipo">Tipo do formulário *</label>
                                <input
                                    id="tipo"
                                    name="tipo"
                                    type="text"
                                    maxlength="<?= TIPO_FORMULARIO_MAX ?>"
                                    value="<?= escapar($valoresForm['tipo']) ?>"
                                    placeholder="Ex.: Autorização de uso de imagem"
                                    required
                                >
                            </div>

                            <div class="field">
                                <label for="status">Status *</label>
                                <select id="status" name="status" required>
                                    <?php foreach (array_diff(STATUS_FORMULARIO_VALIDOS, ['ATRASADO']) as $statusOpcao): ?>
                                        <option
                                            value="<?= $statusOpcao ?>"
                                            <?= $statusOpcao === $valoresForm['status'] ? 'selected' : '' ?>
                                        >
                                            <?= escapar(traduzirStatusFormulario($statusOpcao)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="data_previsao">Data de previsão</label>
                                <input
                                    id="data_previsao"
                                    name="data_previsao"
                                    type="date"
                                    value="<?= escapar($valoresForm['data_previsao']) ?>"
                                >
                            </div>

                            <div class="field">
                                <label for="data_envio">Data de envio</label>
                                <input
                                    id="data_envio"
                                    name="data_envio"
                                    type="date"
                                    value="<?= escapar($valoresForm['data_envio']) ?>"
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
                            <a href="formularios.php" class="secondary-button">Cancelar</a>
                            <button type="submit" class="primary-button">
                                <?= $painelAberto === 'novo' ? 'Criar formulário' : 'Salvar alterações' ?>
                            </button>
                        </div>

                    </form>

                </article>

            <?php endif; ?>

            <article class="panel">

                <div class="panel-header">
                    <div>
                        <p class="eyebrow">TODOS OS FORMULÁRIOS</p>
                        <h2>Lista de formulários</h2>
                    </div>
                    <span class="panel-count"><?= count($formularios) ?></span>
                </div>

                <?php if ($formularios === []): ?>

                    <div class="empty-state">
                        <div class="empty-icon">▤</div>
                        <p>
                            <?= $apiIndisponivel
                                ? 'Não foi possível carregar os formulários agora.'
                                : 'Nenhum formulário cadastrado.' ?>
                        </p>
                    </div>

                <?php else: ?>

                    <div class="listas-table-wrapper">

                        <table class="listas-table">

                            <thead>
                                <tr>
                                    <th>Evento</th>
                                    <th>Tipo</th>
                                    <th>Data de previsão</th>
                                    <th>Data de envio</th>
                                    <th>Status</th>
                                    <th>Observações</th>
                                    <?php if ($podeGerenciarFormularios): ?>
                                        <th>Ações</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($formularios as $formulario): ?>

                                    <tr
                                        class="searchable-item"
                                        data-search="<?= escapar(
                                            ($formulario['evento_titulo'] ?? '')
                                            . ' '
                                            . ($formulario['tipo'] ?? '')
                                            . ' '
                                            . ($formulario['observacoes'] ?? '')
                                        ) ?>"
                                    >
                                        <td><a href="evento.php?id=<?= (int) ($formulario['evento_id'] ?? 0) ?>" class="evento-link"><?= escapar($formulario['evento_titulo'] ?? '—') ?></a></td>

                                        <td class="listas-titulo">
                                            <strong><?= escapar($formulario['tipo'] ?? 'Não informado') ?></strong>
                                        </td>

                                        <td><?= escapar(formatarDataCurta($formulario['data_previsao'] ?? null)) ?></td>

                                        <td><?= escapar(formatarDataCurta($formulario['data_envio'] ?? null)) ?></td>

                                        <td>
                                            <span class="status-badge <?= escapar(classeStatusFormulario(statusEfetivoFormulario($formulario))) ?>">
                                                <?= escapar(traduzirStatusFormulario(statusEfetivoFormulario($formulario))) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if (!empty($formulario['observacoes'])): ?>
                                                <?= nl2br(escapar($formulario['observacoes'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <?php if ($podeGerenciarFormularios): ?>
                                            <td>
                                                <a
                                                    class="text-link"
                                                    href="?painel=editar&evento_id=<?= (int) ($formulario['evento_id'] ?? 0) ?>&id=<?= (int) ($formulario['id'] ?? 0) ?>"
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
