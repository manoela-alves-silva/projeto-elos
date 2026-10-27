<?php

declare(strict_types=1);

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;

require_once __DIR__ . '/../src/Api.php';

\Elos\Frontend\iniciarSessao();

$usuario = $_SESSION['usuario'] ?? null;

if (is_array($usuario) && !empty($usuario['id'])) {
    header('Location: ./');
    exit();
}

$mensagemErro = '';
$mensagemInfo = '';

// Aviso deixado pela entrada com Google (google_entrar/google_retorno).
$aviso = $_SESSION['aviso_login'] ?? null;
unset($_SESSION['aviso_login']);

if (is_array($aviso) && is_string($aviso['texto'] ?? null)) {
    if (($aviso['tipo'] ?? '') === 'info') {
        $mensagemInfo = $aviso['texto'];
    } else {
        $mensagemErro = $aviso['texto'];
    }
}

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
                \Elos\Frontend\lembrarLogin(($_POST['lembrar'] ?? '') === '1');

                header('Location: ./');
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
        href="../assets/css/base.css?v=2026-10-27"
    >

    <link
        rel="stylesheet"
        href="../assets/css/login.css?v=2026-10-27"
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
                href="./"
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


                    <a
                        href="recuperar-senha"
                        class="forgot-password"
                    >
                        Esqueceu a senha?
                    </a>

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


            <a
                href="entrar-com-google"
                class="google-button"
                id="googleButton"
            >

                <span class="google-icon">
                    G
                </span>

                <span>
                    Continuar com o Google
                </span>

            </a>


            <p class="login-signup">
                Ainda não tem conta?
                <a href="criar-conta">Criar conta</a>
            </p>


        </section>

    </main>

</div>


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


    // "Lembrar de mim" também vale para a entrada com Google.
    const lembrar = document.querySelector('input[name="lembrar"]');
    const google = document.getElementById('googleButton');

    if (lembrar && google) {
        google.addEventListener('click', function () {
            google.href = 'entrar-com-google' + (lembrar.checked ? '?lembrar=1' : '');
        });
    }

});
</script>

</body>
</html>