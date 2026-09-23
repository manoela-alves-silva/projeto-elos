<?php

declare(strict_types=1);

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;

require_once __DIR__ . '/../src/Api.php';

\Elos\Frontend\iniciarSessao();

$usuario = $_SESSION['usuario'] ?? null;

if (is_array($usuario) && !empty($usuario['id'])) {
    header('Location: ../index.php');
    exit();
}

$mensagemErro = '';
$cadastroConcluido = false;
$aguardaAprovacao = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

    if ($nome === '') {
        $mensagemErro = 'Informe seu nome.';
    } elseif ($email === '') {
        $mensagemErro = 'Informe seu e-mail.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagemErro = 'Digite um e-mail válido.';
    } elseif ($senha === '') {
        $mensagemErro = 'Informe uma senha.';
    } elseif (strlen($senha) < 8) {
        $mensagemErro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($confirmarSenha === '') {
        $mensagemErro = 'Confirme sua senha.';
    } elseif ($senha !== $confirmarSenha) {
        $mensagemErro = 'As senhas não coincidem.';
    } else {
        try {
            $resposta = Api::post('/api/usuarios', [
                'nome' => $nome,
                'email' => $email,
                'senha' => $senha,
            ]);

            $cadastroConcluido = true;
            $aguardaAprovacao = ($resposta['pendente'] ?? true) === true;
        } catch (ApiException $e) {
            $mensagemErro = $e->statusCode === 409
                ? 'Este e-mail já está cadastrado.'
                : $e->getMessage();
        }
    }
}

function escapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

$nomeInformado = escapar((string) ($_POST['nome'] ?? ''));
$emailInformado = escapar((string) ($_POST['email'] ?? ''));

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Criar conta | ELOS</title>

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

    <link
        rel="stylesheet"
        href="../assets/css/base.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/login.css"
    >
</head>

<body class="login-page">

<div class="login-app">

    <aside class="login-sidebar">

        <div class="sidebar-decoration sidebar-decoration-top">
            <span></span>
        </div>

        <div class="sidebar-logo">
            <a
                href="../index.php"
                aria-label="ELOS"
            >
                <strong class="sidebar-logo-text">
                    EL<span>O</span>S
                </strong>

                <small>
                    EVENTOS QUE<br>
                    CONECTAM
                </small>
            </a>
        </div>

        <p class="login-tagline">
            Eventos que conectam pessoas,
            ideias e oportunidades.
        </p>

        <div class="sidebar-decoration sidebar-decoration-bottom">
            <span class="shape-yellow"></span>
            <span class="shape-blue"></span>
            <span class="shape-cream"></span>
        </div>

    </aside>


    <main class="login-main">

        <div
            class="decorative-shape decorative-shape-top"
            aria-hidden="true"
        ></div>

        <div
            class="decorative-shape decorative-shape-right"
            aria-hidden="true"
        ></div>

        <div
            class="decorative-shape decorative-shape-bottom"
            aria-hidden="true"
        ></div>


        <section class="login-card">

            <div class="login-card-brand">

                <div class="card-logo">
                    EL<span>O</span>S
                </div>

                <small>
                    EVENTOS QUE CONECTAM
                </small>

            </div>


            <div class="login-heading">

                <h1>
                    Crie sua conta
                </h1>

                <p>
                    Cadastre-se para acompanhar e organizar
                    os eventos do ELOS.
                </p>

            </div>


            <?php if ($mensagemErro !== ''): ?>

                <div
                    class="login-message login-error"
                    role="alert"
                >
                    <?= escapar($mensagemErro) ?>
                </div>

            <?php endif; ?>


            <?php if ($cadastroConcluido): ?>

                <div
                    class="login-message login-info"
                    role="status"
                >
                    <?php if ($aguardaAprovacao): ?>
                        Conta criada. Um gestor do ELOS precisa aprovar
                        seu cadastro; depois disso é só entrar com seu
                        e-mail e senha.
                    <?php else: ?>
                        Conta criada com sucesso. Você já pode
                        entrar com seu e-mail e senha.
                    <?php endif; ?>
                </div>

                <a
                    href="login.php"
                    class="login-submit login-submit-link"
                >
                    Ir para o login
                </a>

            <?php else: ?>

                <form
                    class="login-form"
                    method="post"
                    action=""
                >
                    <?= \Elos\Frontend\campoCsrf() ?>

                    <div class="form-group">

                        <label for="nome">
                            Nome
                        </label>

                        <div class="input-wrapper">

                            <span
                                class="input-icon"
                                aria-hidden="true"
                            >
                                ●
                            </span>

                            <input
                                id="nome"
                                name="nome"
                                type="text"
                                value="<?= $nomeInformado ?>"
                                placeholder="Seu nome completo"
                                autocomplete="name"
                                required
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="email">
                            E-mail
                        </label>

                        <div class="input-wrapper">

                            <span
                                class="input-icon"
                                aria-hidden="true"
                            >
                                @
                            </span>

                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="<?= $emailInformado ?>"
                                placeholder="seuemail@exemplo.com"
                                autocomplete="email"
                                required
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="senha">
                            Senha
                        </label>

                        <div class="input-wrapper">

                            <span
                                class="input-icon password-icon"
                                aria-hidden="true"
                            >
                                ●
                            </span>

                            <input
                                id="senha"
                                name="senha"
                                type="password"
                                placeholder="Mínimo de 8 caracteres"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="show-password"
                                id="showSenha"
                                aria-label="Mostrar senha"
                            >
                                <span></span>
                            </button>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="confirmar_senha">
                            Confirmar senha
                        </label>

                        <div class="input-wrapper">

                            <span
                                class="input-icon password-icon"
                                aria-hidden="true"
                            >
                                ●
                            </span>

                            <input
                                id="confirmar_senha"
                                name="confirmar_senha"
                                type="password"
                                placeholder="Repita a senha"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="show-password"
                                id="showConfirmarSenha"
                                aria-label="Mostrar senha"
                            >
                                <span></span>
                            </button>

                        </div>

                    </div>


                    <button
                        type="submit"
                        class="login-submit"
                    >
                        Criar conta
                    </button>

                </form>

                <p class="login-signup">
                    Já tem conta?
                    <a href="login.php">Entrar</a>
                </p>

            <?php endif; ?>

        </section>

    </main>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    function ativarAlternarSenha(idCampo, idBotao) {
        const campo = document.getElementById(idCampo);
        const botao = document.getElementById(idBotao);

        if (!campo || !botao) {
            return;
        }

        botao.addEventListener('click', function () {

            const mostrando = campo.type === 'text';

            campo.type = mostrando
                ? 'password'
                : 'text';

            botao.classList.toggle(
                'is-visible',
                !mostrando
            );

            botao.setAttribute(
                'aria-label',
                mostrando
                    ? 'Mostrar senha'
                    : 'Ocultar senha'
            );
        });
    }

    ativarAlternarSenha('senha', 'showSenha');
    ativarAlternarSenha('confirmar_senha', 'showConfirmarSenha');

});
</script>

</body>
</html>
