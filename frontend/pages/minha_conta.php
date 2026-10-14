<?php

declare(strict_types=1);

/*
 * MINHA CONTA — a pessoa logada troca a própria senha e gera o seu
 * código de recuperação (usado em "Esqueceu a senha?").
 * Quem esqueceu a senha não chega aqui: pede a um gestor, que gera uma
 * senha provisória na tela Equipe. Depois de entrar com ela, a pessoa
 * vem aqui e escolhe uma nova.
 */

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;
use Elos\Frontend\MenuLateral;

use function Elos\Frontend\esc;
use function Elos\Frontend\flash;
use function Elos\Frontend\redirecionar;
use function Elos\Frontend\usuarioLogado;

require_once __DIR__ . '/../src/Planejamento.php';

$usuario = usuarioLogado('login.php');

$erro = '';
$erroCodigo = '';
$codigoRecuperacao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_codigo') {
    // Sem redirecionar: o código aparece só nesta resposta.
    $senhaAtual = (string) ($_POST['senha_atual'] ?? '');

    if ($senhaAtual === '') {
        $erroCodigo = 'Informe sua senha atual.';
    } else {
        try {
            $resposta = Api::post('/api/usuarios/codigo-recuperacao', ['senha_atual' => $senhaAtual]);
            $codigoRecuperacao = (string) ($resposta['codigo_recuperacao'] ?? '');
        } catch (ApiException $e) {
            $erroCodigo = $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
    $novaSenha = (string) ($_POST['nova_senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

    if ($senhaAtual === '') {
        $erro = 'Informe a senha atual.';
    } elseif (strlen($novaSenha) < 8) {
        $erro = 'A nova senha deve ter pelo menos 8 caracteres.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $erro = 'A confirmação não é igual à nova senha.';
    } else {
        try {
            Api::put('/api/usuarios/senha', [
                'senha_atual' => $senhaAtual,
                'nova_senha' => $novaSenha,
            ]);

            flash('conta', 'Senha alterada. Use a nova senha da próxima vez que entrar.');
            redirecionar('minha_conta.php');
        } catch (ApiException $e) {
            $erro = $e->getMessage();
        }
    }
}

$mensagem = flash('conta');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha conta | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/planejamento.css">
    <link rel="stylesheet" href="../assets/css/conta.css">
</head>
<body>

<div class="app-shell">

    <?php MenuLateral::renderizar('conta', false, $usuario['nome'], $usuario['primeiroNome'], $usuario['perfil']); ?>

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
                    <p class="eyebrow">Minha conta</p>
                    <h1>
                        <?= esc($usuario['nome']) ?>
                        <span>Troque sua senha quando quiser, e sempre depois de entrar com uma senha provisória.</span>
                    </h1>
                </div>
            </section>

            <?php if ($mensagem !== ''): ?>
                <div class="aviso aviso-ok" role="status"><?= esc($mensagem) ?></div>
            <?php endif; ?>

            <?php if ($erro !== ''): ?>
                <div class="aviso aviso-erro" role="alert"><?= esc($erro) ?></div>
            <?php endif; ?>

            <article class="panel conta-senha">
                <div class="panel-header">
                    <h2>Trocar senha</h2>
                </div>

                <form method="post" class="form-elos">
                    <?= \Elos\Frontend\campoCsrf() ?>

                    <div class="campo">
                        <label for="senha_atual">Senha atual</label>
                        <input id="senha_atual" type="password" name="senha_atual" required autocomplete="current-password">
                    </div>

                    <div class="campo">
                        <label for="nova_senha">Nova senha <span>(pelo menos 8 caracteres)</span></label>
                        <input id="nova_senha" type="password" name="nova_senha" required minlength="8" autocomplete="new-password">
                    </div>

                    <div class="campo">
                        <label for="confirmar_senha">Confirmar nova senha</label>
                        <input id="confirmar_senha" type="password" name="confirmar_senha" required minlength="8" autocomplete="new-password">
                    </div>

                    <button type="submit" class="primary-button">Salvar nova senha</button>
                </form>
            </article>

            <article class="panel conta-senha" id="codigo">
                <div class="panel-header">
                    <h2>Código de recuperação</h2>
                </div>

                <?php if ($codigoRecuperacao !== ''): ?>
                    <div class="codigo-recuperacao" role="status">
                        <p>Seu novo código de recuperação:</p>
                        <code><?= esc($codigoRecuperacao) ?></code>
                        <p>Guarde num lugar seguro. Ele só aparece agora, e o código anterior deixou de valer.</p>
                    </div>
                <?php else: ?>
                    <form method="post" action="#codigo" class="form-elos">
                        <?= \Elos\Frontend\campoCsrf() ?>
                        <input type="hidden" name="acao" value="gerar_codigo">

                        <p class="conta-explicacao">
                            Com ele você cria uma senha nova em <strong>Esqueceu a senha?</strong>,
                            sem precisar de um gestor. Gerar um código novo invalida o anterior.
                        </p>

                        <?php if ($erroCodigo !== ''): ?>
                            <div class="aviso aviso-erro" role="alert"><?= esc($erroCodigo) ?></div>
                        <?php endif; ?>

                        <div class="campo">
                            <label for="senha_atual_codigo">Senha atual</label>
                            <input id="senha_atual_codigo" type="password" name="senha_atual" required autocomplete="current-password">
                        </div>

                        <button type="submit" class="botao-secundario">Gerar código</button>
                    </form>
                <?php endif; ?>
            </article>

        </div>

    </main>

</div>

</body>
</html>
