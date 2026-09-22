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
$mensagemInfo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
        $mensagemErro = 'Preencha seu e-mail e sua senha.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagemErro = 'Digite um e-mail válido.';
    } else {
        try {
            $dados = Api::post('/api/login', [
                'email' => $email,
                'senha' => $senha,
            ]);

            if (
                isset($dados['usuario'])
                && is_array($dados['usuario'])
                && !empty($dados['usuario']['id'])
            ) {
                // Sessão nova a cada login: um ID de sessão plantado antes
                // do login não serve para entrar na conta de ninguém.
                session_regenerate_id(true);

                $_SESSION['usuario'] = [
                    'id' => $dados['usuario']['id'] ?? null,
                    'nome' => $dados['usuario']['nome'] ?? null,
                    'email' => $dados['usuario']['email'] ?? null,
                    'perfil' => $dados['usuario']['perfil'] ?? null,
                ];

                header('Location: ../index.php');
                exit();
            }

            $mensagemErro = 'E-mail ou senha inválidos.';
        } catch (ApiException $e) {
            $mensagemErro = $e->statusCode === 401
                ? 'E-mail ou senha inválidos.'
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

$emailInformado = escapar(
    (string) ($_POST['email'] ?? '')
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

    <title>Entrar | ELOS</title>

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
                    Acesse sua conta
                </h1>

                <p>
                    Entre para gerenciar eventos,
                    equipes e atividades.
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


            <?php if ($mensagemInfo !== ''): ?>

                <div
                    class="login-message login-info"
                    role="status"
                >
                    <?= escapar($mensagemInfo) ?>
                </div>

            <?php endif; ?>


            <form
                class="login-form"
                method="post"
                action=""
            >
                <?= \Elos\Frontend\campoCsrf() ?>

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
                            placeholder="••••••••••"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="show-password"
                            id="showPassword"
                            aria-label="Mostrar senha"
                        >
                            <span></span>
                        </button>

                    </div>

                </div>


                <div class="form-options">

                    <label class="remember-me">

                        <input
                            type="checkbox"
                            name="lembrar"
                            value="1"
                        >

                        <span class="custom-checkbox"></span>

                        <span>
                            Lembrar de mim
                        </span>

                    </label>


                    <button
                        type="button"
                        class="forgot-password"
                        id="forgotPassword"
                    >
                        Esqueceu a senha?
                    </button>

                </div>


                <button
                    type="submit"
                    class="login-submit"
                >
                    Entrar
                </button>

            </form>


            <div class="login-or">

                <span></span>

                <small>
                    Ou entre com
                </small>

                <span></span>

            </div>


            <button
                type="button"
                class="google-button"
                id="googleButton"
            >

                <span class="google-icon">
                    G
                </span>

                <span>
                    Continuar com o Google
                </span>

            </button>


            <p
                class="google-notice"
                id="googleNotice"
                hidden
            >
                O login com Google será habilitado
                quando a autenticação OAuth do projeto
                estiver configurada.
            </p>


            <p class="login-signup">
                Ainda não tem conta?
                <a href="cadastro.php">Criar conta</a>
            </p>


        </section>

    </main>

</div>


<div
    class="login-toast"
    id="loginToast"
    role="status"
    aria-live="polite"
></div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const senha = document.getElementById('senha');
    const botaoSenha = document.getElementById('showPassword');

    if (senha && botaoSenha) {
        botaoSenha.addEventListener('click', function () {

            const mostrando = senha.type === 'text';

            senha.type = mostrando
                ? 'password'
                : 'text';

            botaoSenha.classList.toggle(
                'is-visible',
                !mostrando
            );

            botaoSenha.setAttribute(
                'aria-label',
                mostrando
                    ? 'Mostrar senha'
                    : 'Ocultar senha'
            );
        });
    }


    const forgotPassword = document.getElementById(
        'forgotPassword'
    );

    const toast = document.getElementById(
        'loginToast'
    );

    function mostrarToast(mensagem) {

        if (!toast) {
            return;
        }

        toast.textContent = mensagem;
        toast.classList.add('show');

        window.clearTimeout(
            mostrarToast.timeout
        );

        mostrarToast.timeout = window.setTimeout(
            function () {
                toast.classList.remove('show');
            },
            3500
        );
    }


    if (forgotPassword) {

        forgotPassword.addEventListener(
            'click',
            function () {

                mostrarToast(
                    'A recuperação de senha será disponibilizada nesta etapa do sistema.'
                );

            }
        );

    }


    const googleButton = document.getElementById(
        'googleButton'
    );

    const googleNotice = document.getElementById(
        'googleNotice'
    );

    if (googleButton && googleNotice) {

        googleButton.addEventListener(
            'click',
            function () {

                googleNotice.hidden = false;

                mostrarToast(
                    'O acesso com Google ainda precisa da configuração OAuth.'
                );

            }
        );

    }

});
</script>

</body>
</html>