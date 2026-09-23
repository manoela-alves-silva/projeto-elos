<?php

declare(strict_types=1);

/*
 * EQUIPE — quem tem conta no ELOS e o que cada um pode fazer.
 * Só gestores entram aqui. Cada pessoa cria a própria conta (Cadastro),
 * que fica aguardando até um gestor aprovar; aprovada, ela é
 * colaboradora. Um gestor pode torná-la gestora, e qualquer gestor pode
 * voltar a ser colaborador desde que fique outro.
 */

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;
use Elos\Frontend\MenuLateral;

use function Elos\Frontend\apiLista;
use function Elos\Frontend\esc;
use function Elos\Frontend\flash;
use function Elos\Frontend\redirecionar;
use function Elos\Frontend\usuarioLogado;

require_once __DIR__ . '/../src/Planejamento.php';

$usuario = usuarioLogado('login.php');

if (!$usuario['podeGerenciar']) {
    redirecionar('../index.php');
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decisao'])) {
    $alvoId = (int) ($_POST['usuario_id'] ?? 0);
    $nomeAlvo = trim((string) ($_POST['nome'] ?? ''));
    $aprovar = $_POST['decisao'] === 'aprovar';

    try {
        if ($aprovar) {
            Api::post('/api/usuarios/pendentes/' . $alvoId);
        } else {
            Api::delete('/api/usuarios/pendentes/' . $alvoId);
        }

        flash('equipe', $aprovar
            ? $nomeAlvo . ' já pode entrar no ELOS.'
            : 'O cadastro de ' . $nomeAlvo . ' foi recusado.');
        redirecionar('equipe.php');
    } catch (ApiException $e) {
        $erro = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alvoId = (int) ($_POST['usuario_id'] ?? 0);
    $perfil = (string) ($_POST['perfil'] ?? '');
    $nomeAlvo = trim((string) ($_POST['nome'] ?? ''));

    try {
        Api::put('/api/usuarios/' . $alvoId, ['perfil' => $perfil]);

        if ($alvoId === $usuario['id']) {
            // A própria pessoa deixou de ser gestora: sai desta tela.
            $_SESSION['usuario']['perfil'] = $perfil;
            flash('inicio', 'Pronto: agora você é colaborador(a).');
            redirecionar('../index.php');
        }

        flash('equipe', $perfil === 'GESTOR'
            ? $nomeAlvo . ' agora é gestor(a).'
            : $nomeAlvo . ' agora é colaborador(a).');
        redirecionar('equipe.php');
    } catch (ApiException $e) {
        $erro = $e->getMessage();
    }
}

$mensagem = flash('equipe');
$pessoas = apiLista('/api/usuarios', 'usuarios');
$pendentes = apiLista('/api/usuarios/pendentes', 'usuarios') ?? [];
$apiIndisponivel = $pessoas === null;

$gestores = [];
$colaboradores = [];

foreach ($pessoas ?? [] as $pessoa) {
    if (in_array($pessoa['perfil'] ?? '', ['GESTOR', 'ADMIN'], true)) {
        $gestores[] = $pessoa;
    } else {
        $colaboradores[] = $pessoa;
    }
}

$unicoGestor = count($gestores) === 1;

/**
 * Uma linha da lista, com o botão que troca o perfil.
 */
$linha = static function (array $pessoa) use ($usuario, $unicoGestor): void {
    $id = (int) $pessoa['id'];
    $souEu = $id === $usuario['id'];
    $perfil = (string) $pessoa['perfil'];
    $ehGestor = in_array($perfil, ['GESTOR', 'ADMIN'], true);
    ?>
    <li>
        <strong>
            <?= esc($pessoa['nome']) ?>
            <?php if ($souEu): ?><span class="chip">Você</span><?php endif; ?>
        </strong>
        <span class="equipe-email"><?= esc($pessoa['email']) ?></span>

        <?php if ($perfil === 'ADMIN'): ?>
            <span class="estado-tag agendado">Administrador</span>
        <?php elseif (!$ehGestor): ?>
            <form method="post" class="equipe-acao">
                <?= \Elos\Frontend\campoCsrf() ?>
                <input type="hidden" name="usuario_id" value="<?= $id ?>">
                <input type="hidden" name="nome" value="<?= esc($pessoa['nome']) ?>">
                <input type="hidden" name="perfil" value="GESTOR">
                <button type="submit" class="botao-secundario">Tornar gestor(a)</button>
            </form>
        <?php elseif ($unicoGestor): ?>
            <span class="equipe-nota">Único gestor: torne outra pessoa gestora antes de sair.</span>
        <?php elseif ($souEu): ?>
            <details class="equipe-acao">
                <summary class="botao-secundario">Deixar de ser gestor(a)</summary>
                <form method="post" class="equipe-confirmar">
                    <?= \Elos\Frontend\campoCsrf() ?>
                    <p>Você deixa de criar e editar exposições e de acessar esta tela.</p>
                    <input type="hidden" name="usuario_id" value="<?= $id ?>">
                    <input type="hidden" name="nome" value="<?= esc($pessoa['nome']) ?>">
                    <input type="hidden" name="perfil" value="COLABORADOR">
                    <button type="submit" class="botao-perigo">Confirmar</button>
                </form>
            </details>
        <?php else: ?>
            <form method="post" class="equipe-acao">
                <?= \Elos\Frontend\campoCsrf() ?>
                <input type="hidden" name="usuario_id" value="<?= $id ?>">
                <input type="hidden" name="nome" value="<?= esc($pessoa['nome']) ?>">
                <input type="hidden" name="perfil" value="COLABORADOR">
                <button type="submit" class="botao-secundario">Passar para colaborador(a)</button>
            </form>
        <?php endif; ?>
    </li>
    <?php
};

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipe | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/planejamento.css">
    <link rel="stylesheet" href="../assets/css/equipe.css">
</head>
<body>

<div class="app-shell">

    <?php MenuLateral::renderizar('equipe', false, $usuario['nome'], $usuario['primeiroNome'], $usuario['perfil']); ?>

    <main class="main-content">

        <header class="topbar">
            <div></div>
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

            <section class="welcome-section">
                <div>
                    <p class="eyebrow">Equipe</p>
                    <h1>
                        Quem usa o ELOS
                        <span>Gestores organizam as exposições e a equipe. Colaboradores acompanham o que está com eles.</span>
                    </h1>
                </div>
            </section>

            <?php if ($mensagem !== ''): ?>
                <div class="aviso aviso-ok" role="status"><?= esc($mensagem) ?></div>
            <?php endif; ?>

            <?php if ($erro !== ''): ?>
                <div class="aviso aviso-erro" role="alert"><?= esc($erro) ?></div>
            <?php endif; ?>

            <?php if ($apiIndisponivel): ?>
                <div class="aviso aviso-erro" role="alert">Não foi possível carregar a equipe agora.</div>
            <?php else: ?>

                <?php if ($pendentes !== []): ?>
                    <article class="panel equipe-pendentes">
                        <div class="panel-header">
                            <h2>Aguardando aprovação <span class="equipe-contagem"><?= count($pendentes) ?></span></h2>
                        </div>
                        <ul class="lista-simples">
                            <?php foreach ($pendentes as $pessoa): ?>
                                <li>
                                    <strong><?= esc($pessoa['nome']) ?></strong>
                                    <span class="equipe-email"><?= esc($pessoa['email']) ?></span>
                                    <form method="post" class="equipe-acao equipe-decisao">
                                        <?= \Elos\Frontend\campoCsrf() ?>
                                        <input type="hidden" name="usuario_id" value="<?= (int) $pessoa['id'] ?>">
                                        <input type="hidden" name="nome" value="<?= esc($pessoa['nome']) ?>">
                                        <button type="submit" name="decisao" value="recusar" class="botao-secundario">Recusar</button>
                                        <button type="submit" name="decisao" value="aprovar" class="primary-button">Aprovar</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endif; ?>

                <article class="panel">
                    <div class="panel-header">
                        <h2>Gestores <span class="equipe-contagem"><?= count($gestores) ?></span></h2>
                    </div>
                    <ul class="lista-simples">
                        <?php foreach ($gestores as $pessoa) { $linha($pessoa); } ?>
                    </ul>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <h2>Colaboradores <span class="equipe-contagem"><?= count($colaboradores) ?></span></h2>
                    </div>
                    <?php if ($colaboradores === []): ?>
                        <p class="equipe-vazio">Ninguém por aqui ainda.</p>
                    <?php else: ?>
                        <ul class="lista-simples">
                            <?php foreach ($colaboradores as $pessoa) { $linha($pessoa); } ?>
                        </ul>
                    <?php endif; ?>
                </article>

                <p class="equipe-dica">
                    Para alguém entrar na equipe, a pessoa cria a própria conta em
                    <strong>Criar conta</strong>, na tela de login, com o e-mail dela. O cadastro aparece aqui
                    para você aprovar; só depois disso ela consegue entrar. Recuse quem você não conhece.
                </p>

            <?php endif; ?>

        </div>

    </main>

</div>

</body>
</html>
