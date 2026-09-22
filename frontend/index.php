<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$usuario = $_SESSION['usuario'] ?? null;

if (!is_array($usuario) || empty($usuario['id'])) {
    header('Location: pages/login.php');
    exit();
}

$nomeUsuario = (string) ($usuario['nome'] ?? 'Usuário');
$perfilUsuario = (string) ($usuario['perfil'] ?? 'COLABORADOR');

$primeiroNome = explode(' ', trim($nomeUsuario))[0] ?: $nomeUsuario;

function buscarApi(string $path): ?array
{
    $url = 'http://127.0.0.1:8000' . $path;

    $cookie = session_name() . '=' . session_id();

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'Cookie: ' . $cookie,
            ],
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);

    $resposta = @file_get_contents($url, false, $context);

    if ($resposta === false) {
        return null;
    }

    $dados = json_decode($resposta, true);

    return is_array($dados) ? $dados : null;
}

function escapar(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatarData(?string $data): string
{
    if (!$data) {
        return 'Não informado';
    }

    $timestamp = strtotime($data);

    if ($timestamp === false) {
        return $data;
    }

    return date('d/m/Y', $timestamp);
}

function traduzirStatusEvento(string $status): string
{
    return match ($status) {
        'PLANEJAMENTO' => 'Planejamento',
        'EM_ANDAMENTO' => 'Em andamento',
        'CONCLUIDO' => 'Concluído',
        'CANCELADO' => 'Cancelado',
        default => $status,
    };
}

function classeStatusEvento(string $status): string
{
    return match ($status) {
        'EM_ANDAMENTO' => 'status-andamento',
        'CONCLUIDO' => 'status-concluido',
        'CANCELADO' => 'status-cancelado',
        default => 'status-planejamento',
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

$eventos = buscarApi('/api/eventos');

$apiIndisponivel = $eventos === null;

if (!is_array($eventos)) {
    $eventos = [];
}

$totalEventos = count($eventos);

$eventosAndamento = array_values(
    array_filter(
        $eventos,
        static fn(array $evento): bool =>
            ($evento['status'] ?? '') === 'EM_ANDAMENTO'
    )
);

$eventosPlanejamento = array_values(
    array_filter(
        $eventos,
        static fn(array $evento): bool =>
            ($evento['status'] ?? '') === 'PLANEJAMENTO'
    )
);

$eventosConcluidos = array_values(
    array_filter(
        $eventos,
        static fn(array $evento): bool =>
            ($evento['status'] ?? '') === 'CONCLUIDO'
    )
);

$eventoDestaque = $eventosAndamento[0]
    ?? $eventosPlanejamento[0]
    ?? $eventos[0]
    ?? null;

$tarefas = [];

if ($eventoDestaque && isset($eventoDestaque['id'])) {
    $dadosTarefas = buscarApi(
        '/api/eventos/' . (int) $eventoDestaque['id'] . '/tarefas'
    );

    if (is_array($dadosTarefas)) {
        $tarefas = $dadosTarefas;
    }
}

$tarefasAtivas = array_values(
    array_filter(
        $tarefas,
        static fn(array $tarefa): bool =>
            !in_array(
                $tarefa['status'] ?? '',
                ['CONCLUIDA', 'CANCELADA'],
                true
            )
    )
);

$hoje = strtotime(date('Y-m-d'));

$tarefasAtrasadas = array_values(
    array_filter(
        $tarefasAtivas,
        static function (array $tarefa) use ($hoje): bool {
            if (empty($tarefa['prazo'])) {
                return false;
            }

            $prazo = strtotime((string) $tarefa['prazo']);

            return $prazo !== false && $prazo < $hoje;
        }
    )
);

$alertasCriticos = array_values(
    array_filter(
        $tarefasAtivas,
        static function (array $tarefa) use ($hoje): bool {
            $prioridade = $tarefa['prioridade'] ?? '';

            if ($prioridade !== 'ALTA') {
                return false;
            }

            if (empty($tarefa['prazo'])) {
                return true;
            }

            $prazo = strtotime((string) $tarefa['prazo']);

            return $prazo === false || $prazo <= strtotime('+3 days');
        }
    )
);

$alertas = array_values(
    array_unique(
        array_merge($alertasCriticos, $tarefasAtrasadas),
        SORT_REGULAR
    )
);

usort(
    $eventos,
    static function (array $a, array $b): int {
        $dataA = strtotime((string) ($a['created_at'] ?? '9999-12-31'));
        $dataB = strtotime((string) ($b['created_at'] ?? '9999-12-31'));

        return $dataB <=> $dataA;
    }
);

$eventosRecentes = array_slice($eventos, 0, 5);

usort(
    $tarefasAtivas,
    static function (array $a, array $b): int {
        $prazoA = strtotime((string) ($a['prazo'] ?? '9999-12-31'));
        $prazoB = strtotime((string) ($b['prazo'] ?? '9999-12-31'));

        return $prazoA <=> $prazoB;
    }
);

$tarefasExibidas = array_slice($tarefasAtivas, 0, 5);

$rotaAtual = 'inicio';

$menuLateral = [
    ['rota' => 'inicio', 'titulo' => 'Início', 'icone' => '⌂', 'href' => 'index.php'],
    ['rota' => 'eventos', 'titulo' => 'Eventos', 'icone' => '▣', 'href' => '#'],
    ['rota' => 'agenda', 'titulo' => 'Agenda', 'icone' => '◷', 'href' => '#'],
    ['rota' => 'tarefas', 'titulo' => 'Tarefas', 'icone' => '✓', 'href' => '#'],
    ['rota' => 'formularios', 'titulo' => 'Formulários', 'icone' => '▤', 'href' => '#'],
    ['rota' => 'transportes', 'titulo' => 'Transportes', 'icone' => '▱', 'href' => '#'],
    ['rota' => 'visitas', 'titulo' => 'Visitas', 'icone' => '♧', 'href' => '#'],
    ['rota' => 'anexos', 'titulo' => 'Anexos', 'icone' => '▧', 'href' => '#'],
    ['rota' => 'relatorios', 'titulo' => 'Relatórios', 'icone' => '▥', 'href' => '#'],
    ['rota' => 'usuarios', 'titulo' => 'Usuários', 'icone' => '♙', 'href' => '#'],
    ['rota' => 'locais', 'titulo' => 'Locais', 'icone' => '⌖', 'href' => '#'],
    ['rota' => 'tipos-evento', 'titulo' => 'Tipo de evento', 'icone' => '◈', 'href' => '#'],
    ['rota' => 'responsaveis', 'titulo' => 'Responsáveis', 'icone' => '◎', 'href' => '#'],
];

if ($apiIndisponivel) {
    $mensagemEventos = 'Não foi possível carregar os eventos agora.';
} elseif ($eventos === []) {
    $mensagemEventos = 'Ainda não existem eventos cadastrados.';
} else {
    $mensagemEventos = 'Acompanhe os eventos e tudo o que precisa acontecer para que eles ganhem vida.';
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

    <title>Dashboard | ELOS</title>

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

    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body>

<div class="app-shell">

    <aside class="sidebar">

        <div class="sidebar-decoration sidebar-decoration-top"></div>

        <div class="sidebar-top">

            <a href="index.php" class="sidebar-logo" aria-label="ELOS">

                <strong class="sidebar-logo-text">
                    EL<span>O</span>S
                </strong>

                <small>
                    EVENTOS QUE<br>
                    CONECTAM
                </small>

            </a>

            <nav class="sidebar-navigation" aria-label="Navegação principal">

                <?php foreach ($menuLateral as $item): ?>

                    <a
                        href="<?= escapar($item['href']) ?>"
                        class="sidebar-item<?= $item['rota'] === $rotaAtual ? ' active' : '' ?>"
                        <?= $item['rota'] === $rotaAtual ? 'aria-current="page"' : '' ?>
                    >
                        <span class="sidebar-icon" aria-hidden="true">
                            <?= $item['icone'] ?>
                        </span>

                        <span><?= escapar($item['titulo']) ?></span>
                    </a>

                <?php endforeach; ?>

            </nav>

        </div>

        <div class="sidebar-footer">

            <div class="sidebar-divider"></div>

            <div class="sidebar-user">

                <span class="user-avatar">
                    <?= escapar(mb_substr($nomeUsuario, 0, 1)) ?>
                </span>

                <span>
                    <strong><?= escapar($primeiroNome) ?></strong>
                    <small><?= escapar($perfilUsuario) ?></small>
                </span>

            </div>

            <a href="pages/logout.php" class="sidebar-logout">
                <span class="sidebar-icon" aria-hidden="true">↪</span>
                <span>Sair</span>
            </a>

        </div>

        <div class="sidebar-decoration sidebar-decoration-bottom">
            <span class="shape-yellow"></span>
            <span class="shape-blue"></span>
            <span class="shape-cream"></span>
        </div>

    </aside>

    <main class="main-content">

        <header class="topbar">

            <div class="topbar-search">

                <span class="search-icon">⌕</span>

                <input
                    type="search"
                    id="dashboardSearch"
                    placeholder="Buscar no ELOS..."
                    aria-label="Buscar no ELOS"
                >

            </div>

            <div class="topbar-actions">

                <button
                    type="button"
                    class="icon-button"
                    aria-label="Notificações"
                >
                    ♢
                    <?php if ($alertas !== []): ?>
                        <span class="notification-dot"></span>
                    <?php endif; ?>
                </button>

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

                        <p>
                            Os números desta tela podem estar desatualizados.
                            Verifique se o serviço está no ar e recarregue a página.
                        </p>
                    </div>

                </div>

            <?php endif; ?>

            <section class="welcome-section">

                <div>

                    <p class="eyebrow">PAINEL ELOS</p>

                    <h1>
                        Olá, <?= escapar($primeiroNome) ?>.
                        <span>Vamos dar movimento aos eventos.</span>
                    </h1>

                    <p class="welcome-description">
                        <?= escapar($mensagemEventos) ?>
                    </p>

                </div>

                <a href="#" class="primary-button">
                    <span>+</span>
                    Novo evento
                </a>

            </section>

            <section class="metrics-grid">

                <article class="metric-card blue">

                    <div class="metric-icon">◫</div>

                    <div class="metric-content">
                        <span class="metric-label">Total de eventos</span>
                        <strong><?= $totalEventos ?></strong>
                    </div>

                    <span class="metric-decoration">01</span>

                </article>

                <article class="metric-card turquoise">

                    <div class="metric-icon">◌</div>

                    <div class="metric-content">
                        <span class="metric-label">Em andamento</span>
                        <strong><?= count($eventosAndamento) ?></strong>
                    </div>

                    <span class="metric-decoration">02</span>

                </article>

                <article class="metric-card yellow">

                    <div class="metric-icon">✓</div>

                    <div class="metric-content">
                        <span class="metric-label">Em planejamento</span>
                        <strong><?= count($eventosPlanejamento) ?></strong>
                    </div>

                    <span class="metric-decoration">03</span>

                </article>

                <article class="metric-card red">

                    <div class="metric-icon">!</div>

                    <div class="metric-content">
                        <span class="metric-label">Alertas</span>
                        <strong><?= count($alertas) ?></strong>
                    </div>

                    <span class="metric-decoration">04</span>

                </article>

            </section>

            <section class="dashboard-grid">

                <div class="dashboard-main">

                    <article class="featured-event">

                        <?php if ($eventoDestaque): ?>

                            <div class="featured-top">

                                <div class="event-badge">
                                    Evento em destaque
                                </div>

                                <span class="status-badge <?= escapar(classeStatusEvento((string) ($eventoDestaque['status'] ?? ''))) ?>">
                                    <?= escapar(traduzirStatusEvento((string) ($eventoDestaque['status'] ?? ''))) ?>
                                </span>

                            </div>

                            <div class="featured-content">

                                <div class="abstract-art">

                                    <span class="shape shape-one"></span>
                                    <span class="shape shape-two"></span>
                                    <span class="shape shape-three"></span>

                                    <span class="abstract-letter">E</span>

                                </div>

                                <div class="featured-info">

                                    <p class="event-type">
                                        <?= escapar($eventoDestaque['tipo_evento_nome'] ?? 'Evento') ?>
                                    </p>

                                    <h2>
                                        <?= escapar($eventoDestaque['titulo'] ?? 'Sem título') ?>
                                    </h2>

                                    <p class="featured-description">
                                        <?= escapar(
                                            $eventoDestaque['descricao']
                                            ?? 'Este evento ainda não possui uma descrição cadastrada.'
                                        ) ?>
                                    </p>

                                    <div class="event-meta-grid">

                                        <div class="event-meta">

                                            <span class="meta-icon">◷</span>

                                            <div>
                                                <small>Período</small>
                                                <strong>
                                                    <?= escapar(formatarData($eventoDestaque['abertura'] ?? null)) ?>
                                                </strong>
                                            </div>

                                        </div>

                                        <div class="event-meta">

                                            <span class="meta-icon">⌖</span>

                                            <div>
                                                <small>Local</small>
                                                <strong>
                                                    <?= escapar($eventoDestaque['local_nome'] ?? 'Não informado') ?>
                                                </strong>
                                            </div>

                                        </div>

                                        <div class="event-meta">

                                            <span class="meta-icon">◎</span>

                                            <div>
                                                <small>Responsável</small>
                                                <strong>
                                                    <?= escapar($eventoDestaque['responsavel_nome'] ?? 'Não informado') ?>
                                                </strong>
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="featured-footer">

                                <div class="priority-wrapper">

                                    <span>Prioridade</span>

                                    <strong class="<?= escapar(classePrioridade((string) ($eventoDestaque['prioridade'] ?? ''))) ?>">
                                        <?= escapar(traduzirPrioridade((string) ($eventoDestaque['prioridade'] ?? ''))) ?>
                                    </strong>

                                </div>

                                <a href="#" class="secondary-button">
                                    Ver evento
                                    <span>→</span>
                                </a>

                            </div>

                        <?php else: ?>

                            <div class="empty-state large">

                                <div class="empty-icon">◫</div>

                                <?php if ($apiIndisponivel): ?>

                                    <h2>Não foi possível carregar os eventos</h2>

                                    <p>
                                        Assim que a conexão com a API for
                                        restabelecida, os eventos aparecem aqui.
                                    </p>

                                <?php else: ?>

                                    <h2>Nenhum evento cadastrado</h2>

                                    <p>
                                        Quando um evento for criado, ele aparecerá aqui.
                                    </p>

                                    <a href="#" class="primary-button">
                                        <span>+</span>
                                        Criar primeiro evento
                                    </a>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                    </article>

                    <article class="panel recent-events">

                        <div class="panel-header">

                            <div>
                                <p class="eyebrow">ACOMPANHAMENTO</p>
                                <h2>Eventos recentes</h2>
                            </div>

                            <a href="#" class="text-link">
                                Ver todos →
                            </a>

                        </div>

                        <?php if ($eventosRecentes !== []): ?>

                            <div class="events-list">

                                <?php foreach ($eventosRecentes as $evento): ?>

                                    <div
                                        class="event-row searchable-item"
                                        data-search="<?= escapar(
                                            ($evento['titulo'] ?? '')
                                            . ' '
                                            . ($evento['tipo_evento_nome'] ?? '')
                                            . ' '
                                            . ($evento['local_nome'] ?? '')
                                        ) ?>"
                                    >

                                        <div class="event-date">

                                            <strong>
                                                <?= escapar(
                                                    date(
                                                        'd',
                                                        strtotime(
                                                            (string) (
                                                                $evento['abertura']
                                                                ?? $evento['created_at']
                                                                ?? date('Y-m-d')
                                                            )
                                                        )
                                                    )
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= escapar(
                                                    strtoupper(
                                                        date(
                                                            'M',
                                                            strtotime(
                                                                (string) (
                                                                    $evento['abertura']
                                                                    ?? $evento['created_at']
                                                                    ?? date('Y-m-d')
                                                                )
                                                            )
                                                        )
                                                    )
                                                ) ?>
                                            </span>

                                        </div>

                                        <div class="event-row-info">

                                            <strong>
                                                <?= escapar($evento['titulo'] ?? 'Sem título') ?>
                                            </strong>

                                            <span>
                                                <?= escapar($evento['local_nome'] ?? 'Local não informado') ?>
                                            </span>

                                        </div>

                                        <span class="status-badge <?= escapar(classeStatusEvento((string) ($evento['status'] ?? ''))) ?>">
                                            <?= escapar(traduzirStatusEvento((string) ($evento['status'] ?? ''))) ?>
                                        </span>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="empty-state">
                                <div class="empty-icon">◌</div>
                                <p>Nenhum evento encontrado.</p>
                            </div>

                        <?php endif; ?>

                    </article>

                </div>

                <aside class="dashboard-side">

                    <article class="panel tasks-panel">

                        <div class="panel-header">

                            <div>
                                <p class="eyebrow">PRÓXIMOS PRAZOS</p>
                                <h2>Tarefas</h2>
                            </div>

                            <span class="panel-count">
                                <?= count($tarefasAtivas) ?>
                            </span>

                        </div>

                        <?php if ($tarefasExibidas !== []): ?>

                            <div class="task-list">

                                <?php foreach ($tarefasExibidas as $tarefa): ?>

                                    <div class="task-item">

                                        <div class="task-marker <?= escapar(classePrioridade((string) ($tarefa['prioridade'] ?? 'BAIXA'))) ?>"></div>

                                        <div class="task-content">

                                            <strong>
                                                <?= escapar($tarefa['titulo'] ?? 'Tarefa sem título') ?>
                                            </strong>

                                            <span>
                                                <?= escapar(
                                                    !empty($tarefa['prazo'])
                                                        ? 'Prazo: ' . formatarData($tarefa['prazo'])
                                                        : 'Sem prazo definido'
                                                ) ?>
                                            </span>

                                        </div>

                                        <span class="task-status">
                                            <?= escapar(traduzirStatusTarefa((string) ($tarefa['status'] ?? ''))) ?>
                                        </span>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="empty-state">
                                <div class="empty-icon">✓</div>
                                <p>Não existem tarefas pendentes para o evento em destaque.</p>
                            </div>

                        <?php endif; ?>

                    </article>

                    <article class="panel alert-panel">

                        <div class="panel-header">

                            <div>
                                <p class="eyebrow">ATENÇÃO</p>
                                <h2>Alertas</h2>
                            </div>

                            <span class="alert-symbol">!</span>

                        </div>

                        <?php if ($alertas !== []): ?>

                            <div class="alert-list">

                                <?php foreach (array_slice($alertas, 0, 3) as $alerta): ?>

                                    <div class="alert-card">

                                        <strong>
                                            <?= escapar($alerta['titulo'] ?? 'Tarefa com atenção') ?>
                                        </strong>

                                        <span>
                                            <?php if (!empty($alerta['prazo'])): ?>
                                                Prazo: <?= escapar(formatarData($alerta['prazo'])) ?>
                                            <?php else: ?>
                                                Prioridade alta sem prazo definido
                                            <?php endif; ?>
                                        </span>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="all-clear">

                                <span class="all-clear-icon">✓</span>

                                <div>
                                    <strong>Tudo em ordem.</strong>
                                    <p>Nenhum alerta crítico encontrado.</p>
                                </div>

                            </div>

                        <?php endif; ?>

                    </article>

                    <article class="movement-card">

                        <div class="movement-decoration">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>

                        <p>IDEIA DO ELOS</p>

                        <h3>
                            Eventos não são apenas datas.
                            <span>São experiências em movimento.</span>
                        </h3>

                    </article>

                </aside>

            </section>

        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('dashboardSearch');

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