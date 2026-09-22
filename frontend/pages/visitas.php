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

// --- Carrega eventos e as visitas de cada um pela API ---
$dadosEventos = buscarApiListas('/api/eventos');
$apiIndisponivel = $dadosEventos === null;
$eventos = is_array($dadosEventos) ? ($dadosEventos['eventos'] ?? []) : [];
if (!is_array($eventos)) {
    $eventos = [];
}

// Não existe endpoint "/api/visitas" global — visitas só existem
// aninhadas em "/api/eventos/{id}/visitas". Agregamos por evento,
// mesmo padrão já usado em Tarefas/Transportes.
$visitas = [];

foreach ($eventos as $evento) {
    if (!is_array($evento) || !isset($evento['id'])) {
        continue;
    }

    $dadosVisitasEvento = buscarApiListas('/api/eventos/' . (int) $evento['id'] . '/visitas');
    $visitasEvento = is_array($dadosVisitasEvento) ? ($dadosVisitasEvento['visitas'] ?? []) : [];

    if (!is_array($visitasEvento)) {
        continue;
    }

    foreach ($visitasEvento as $visita) {
        if (!is_array($visita)) {
            continue;
        }

        $visita['evento_titulo'] = $evento['titulo'] ?? ('Evento #' . $evento['id']);
        $visitas[] = $visita;
    }
}

usort(
    $visitas,
    static function (array $a, array $b): int {
        $dataA = strtotime((string) ($a['data'] ?? '9999-12-31'));
        $dataB = strtotime((string) ($b['data'] ?? '9999-12-31'));

        return $dataA <=> $dataB;
    }
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

    <title>Visitas | ELOS</title>

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
                    placeholder="Buscar visitas..."
                    aria-label="Buscar visitas"
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

            <section class="welcome-section">
                <div>
                    <p class="eyebrow">RECEPÇÃO</p>
                    <h1>
                        Visitas
                        <span>Instituições, grupos e pessoas que visitam os eventos.</span>
                    </h1>
                </div>
            </section>

            <article class="panel">

                <div class="panel-header">
                    <div>
                        <p class="eyebrow">TODAS AS VISITAS</p>
                        <h2>Lista de visitas</h2>
                    </div>
                    <span class="panel-count"><?= count($visitas) ?></span>
                </div>

                <?php if ($visitas === []): ?>

                    <div class="empty-state">
                        <div class="empty-icon">♧</div>
                        <p>
                            <?= $apiIndisponivel
                                ? 'Não foi possível carregar as visitas agora.'
                                : 'Nenhuma visita cadastrada.' ?>
                        </p>
                    </div>

                <?php else: ?>

                    <div class="listas-table-wrapper">

                        <table class="listas-table">

                            <thead>
                                <tr>
                                    <th>Evento</th>
                                    <th>Instituição</th>
                                    <th>Responsável</th>
                                    <th>Pessoas</th>
                                    <th>Data/horário</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($visitas as $visita): ?>

                                    <tr
                                        class="searchable-item"
                                        data-search="<?= escapar(
                                            ($visita['evento_titulo'] ?? '')
                                            . ' '
                                            . ($visita['instituicao'] ?? '')
                                            . ' '
                                            . ($visita['responsavel'] ?? '')
                                        ) ?>"
                                    >
                                        <td><a href="evento.php?id=<?= (int) ($visita['evento_id'] ?? 0) ?>" class="evento-link"><?= escapar($visita['evento_titulo'] ?? '—') ?></a></td>

                                        <td class="listas-titulo">
                                            <strong><?= escapar($visita['instituicao'] ?? 'Não informado') ?></strong>
                                        </td>

                                        <td><?= escapar($visita['responsavel'] ?? '—') ?></td>

                                        <td><?= escapar($visita['quantidade_pessoas'] ?? '—') ?></td>

                                        <td><?= escapar(formatarDataHora($visita['data'] ?? null, $visita['horario'] ?? null)) ?></td>

                                        <td>
                                            <span class="status-badge">
                                                <?= escapar($visita['status'] ?? '—') ?>
                                            </span>
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
