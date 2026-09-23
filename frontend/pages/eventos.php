<?php

declare(strict_types=1);

/*
 * EXPOSIÇÕES — todas as exposições, cada uma com a situação do seu
 * planejamento (período, progresso do checklist, próximo passo).
 * Clicar abre a exposição, onde está tudo dela.
 */

use Elos\Frontend\MenuLateral;
use Elos\Frontend\Planejamento;

use function Elos\Frontend\apiLista;
use function Elos\Frontend\classeStatusEvento;
use function Elos\Frontend\diaMes;
use function Elos\Frontend\esc;
use function Elos\Frontend\exposicaoAtiva;
use function Elos\Frontend\intervaloBr;
use function Elos\Frontend\redirecionar;
use function Elos\Frontend\rotuloStatusEvento;
use function Elos\Frontend\usuarioLogado;

require_once __DIR__ . '/../src/Planejamento.php';

$usuario = usuarioLogado('login.php');

// Endereço antigo do cadastro: agora é um fluxo próprio.
if (($_GET['painel'] ?? '') === 'novo') {
    redirecionar('evento_novo.php');
}

$eventos = apiLista('/api/eventos', 'eventos');
$apiIndisponivel = $eventos === null;

$planos = array_map(static fn(array $evento): Planejamento => Planejamento::carregar($evento), $eventos ?? []);

$verEncerradas = ($_GET['ver'] ?? '') === 'encerradas';
$ativas = array_values(array_filter($planos, static fn(Planejamento $p): bool => exposicaoAtiva($p->evento)));
$encerradas = array_values(array_filter($planos, static fn(Planejamento $p): bool => !exposicaoAtiva($p->evento)));

// Mais próximas primeiro; sem data vão para o fim.
$ordenar = static function (array &$lista): void {
    usort(
        $lista,
        static fn(Planejamento $a, Planejamento $b): int =>
            ($a->periodo()['inicio'] ?? '9999-12-31') <=> ($b->periodo()['inicio'] ?? '9999-12-31')
    );
};
$ordenar($ativas);
$ordenar($encerradas);

$exibidas = $verEncerradas ? $encerradas : $ativas;
$busca = trim((string) ($_GET['q'] ?? ''));

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exposições | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/planejamento.css">
    <link rel="stylesheet" href="../assets/css/eventos.css">
</head>
<body>

<div class="app-shell">

    <?php MenuLateral::renderizar('eventos', false, $usuario['nome'], $usuario['primeiroNome'], $usuario['perfil']); ?>

    <main class="main-content">

        <header class="topbar">
            <div class="topbar-search">
                <span class="search-icon">⌕</span>
                <input type="search" id="buscaExposicoes" placeholder="Buscar exposição…" aria-label="Buscar exposição" value="<?= esc($busca) ?>">
            </div>
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

            <?php if ($apiIndisponivel): ?>
                <div class="aviso aviso-erro" role="alert">Não foi possível carregar as exposições agora.</div>
            <?php endif; ?>

            <section class="welcome-section">
                <div>
                    <p class="eyebrow">Exposições</p>
                    <h1>
                        Suas exposições
                        <span>Abra uma exposição para ver e organizar tudo o que ela precisa.</span>
                    </h1>
                </div>

                <?php if ($usuario['podeGerenciar']): ?>
                    <a href="evento_novo.php" class="primary-button"><span>+</span> Nova exposição</a>
                <?php endif; ?>
            </section>

            <nav class="abas" aria-label="Filtrar exposições">
                <a href="eventos.php" class="<?= $verEncerradas ? '' : 'ativa' ?>" <?= $verEncerradas ? '' : 'aria-current="page"' ?>>
                    Em andamento e planejamento <span><?= count($ativas) ?></span>
                </a>
                <a href="eventos.php?ver=encerradas" class="<?= $verEncerradas ? 'ativa' : '' ?>" <?= $verEncerradas ? 'aria-current="page"' : '' ?>>
                    Encerradas <span><?= count($encerradas) ?></span>
                </a>
            </nav>

            <?php if ($exibidas === []): ?>

                <article class="panel">
                    <div class="empty-state large">
                        <div class="empty-icon">▣</div>
                        <?php if ($verEncerradas): ?>
                            <h2>Nenhuma exposição encerrada</h2>
                            <p>Quando uma exposição for concluída ou cancelada, ela aparece aqui.</p>
                        <?php else: ?>
                            <h2>Nenhuma exposição em andamento</h2>
                            <p>Crie uma exposição e diga o que ela precisa: o ELOS organiza as datas e as pendências.</p>
                            <?php if ($usuario['podeGerenciar']): ?>
                                <a href="evento_novo.php" class="primary-button"><span>+</span> Nova exposição</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </article>

            <?php else: ?>

                <div class="expo-cartoes">
                    <?php foreach ($exibidas as $plano): ?>
                        <?php
                        $evento = $plano->evento;
                        $resumo = $plano->resumo();
                        $periodo = $plano->periodo();
                        $proximo = $plano->proximasAtividades(1)[0] ?? null;
                        ?>
                        <a
                            href="evento.php?id=<?= $plano->id() ?>"
                            class="expo-cartao"
                            data-busca="<?= esc(mb_strtolower(($evento['titulo'] ?? '') . ' ' . ($evento['local_nome'] ?? '') . ' ' . ($evento['tipo_evento_nome'] ?? '') . ' ' . ($evento['responsavel_nome'] ?? ''))) ?>"
                        >
                            <div class="expo-cartao-topo">
                                <span class="eyebrow"><?= esc($evento['tipo_evento_nome'] ?? 'Exposição') ?></span>
                                <span class="status-badge <?= classeStatusEvento((string) ($evento['status'] ?? '')) ?>">
                                    <?= esc(rotuloStatusEvento((string) ($evento['status'] ?? ''))) ?>
                                </span>
                            </div>

                            <h2><?= esc($plano->titulo()) ?></h2>

                            <p class="expo-cartao-fatos">
                                <span>◷ <?= esc(intervaloBr($periodo['inicio'], $periodo['fim'], 'Datas a definir')) ?></span>
                                <?php if (!empty($evento['local_nome'])): ?>
                                    <span>⌖ <?= esc($evento['local_nome']) ?></span>
                                <?php endif; ?>
                            </p>

                            <div class="expo-cartao-progresso">
                                <?php if ($resumo['total'] > 0): ?>
                                    <div class="progresso"><span style="width: <?= $resumo['percentual'] ?>%"></span></div>
                                    <p>
                                        <strong><?= $resumo['concluidas'] ?> de <?= $resumo['total'] ?></strong> resolvidas
                                        <?php if ($resumo['atrasadas'] > 0): ?>
                                            · <span class="texto-atrasado"><?= $resumo['atrasadas'] ?> atrasada<?= $resumo['atrasadas'] === 1 ? '' : 's' ?></span>
                                        <?php endif; ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted">Nada no planejamento ainda.</p>
                                <?php endif; ?>
                            </div>

                            <?php if ($proximo !== null): ?>
                                <p class="expo-cartao-proximo estado-<?= esc($proximo['estado']) ?>">
                                    <span>Próximo</span>
                                    <strong><?= esc(diaMes($proximo['data'])) ?> — <?= esc($proximo['titulo']) ?></strong>
                                </p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <p class="busca-vazia" hidden>Nenhuma exposição encontrada para essa busca.</p>

            <?php endif; ?>

        </div>
    </main>
</div>

<script>
// Busca: filtra os cartões pelo nome, local, tipo ou responsável.
(function () {
    const campo = document.getElementById('buscaExposicoes');
    const cartoes = document.querySelectorAll('.expo-cartao');
    const vazio = document.querySelector('.busca-vazia');

    if (!campo) {
        return;
    }

    const filtrar = function () {
        const termo = campo.value.trim().toLowerCase();
        let visiveis = 0;

        cartoes.forEach(function (cartao) {
            const mostra = !termo || cartao.dataset.busca.includes(termo);
            cartao.hidden = !mostra;
            visiveis += mostra ? 1 : 0;
        });

        if (vazio) {
            vazio.hidden = visiveis > 0;
        }
    };

    campo.addEventListener('input', filtrar);
    filtrar();
})();
</script>

</body>
</html>
