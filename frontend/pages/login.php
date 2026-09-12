<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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
        $payload = json_encode(
            [
                'email' => $email,
                'senha' => $senha,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]),
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $resposta = @file_get_contents(
            'http://127.0.0.1:8000/api/login',
            false,
            $context
        );

        if ($resposta === false) {
            $mensagemErro = 'Não foi possível conectar ao servidor do ELOS.';
        } else {
            $dados = json_decode($resposta, true);

            if (
                is_array($dados)
                && isset($dados['usuario'])
                && is_array($dados['usuario'])
                && !empty($dados['usuario']['id'])
            ) {
                $_SESSION['usuario'] = [
                    'id' => $dados['usuario']['id'] ?? null,
                    'nome' => $dados['usuario']['nome'] ?? null,
                    'email' => $dados['usuario']['email'] ?? null,
                    'perfil' => $dados['usuario']['perfil'] ?? null,
                ];

                header('Location: ../index.php');
                exit();
            }

            $mensagemErro = (
                is_array($dados)
                && !empty($dados['erro'])
            )
                ? (string) $dados['erro']
                : 'E-mail ou senha inválidos.';
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

        <nav
            class="sidebar-navigation"
            aria-label="Navegação principal"
        >

            <a
                href="#"
                class="sidebar-item active"
                data-disabled-link
            >
                <span class="sidebar-icon">⌂</span>
                <span>Início</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">▣</span>
                <span>Eventos</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">◷</span>
                <span>Agenda</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">✓</span>
                <span>Tarefas</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">▤</span>
                <span>Formulários</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">▱</span>
                <span>Transportes</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">♧</span>
                <span>Visitas</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">▧</span>
                <span>Anexos</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">▥</span>
                <span>Relatórios</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">♙</span>
                <span>Usuários</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">⌖</span>
                <span>Locais</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">◈</span>
                <span>Tipo de evento</span>
            </a>

            <a
                href="#"
                class="sidebar-item"
                data-disabled-link
            >
                <span class="sidebar-icon">◎</span>
                <span>Responsáveis</span>
            </a>

        </nav>

        <div class="sidebar-footer">

            <div class="sidebar-divider"></div>

            <a
                href="#"
                class="sidebar-logout"
                data-disabled-link
            >
                <span class="sidebar-icon">↪</span>
                <span>Sair</span>
            </a>

        </div>

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


            <p class="login-footer">
                Eventos que conectam pessoas,
                ideias e oportunidades.
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


    const linksDesativados = document.querySelectorAll(
        '[data-disabled-link]'
    );

    linksDesativados.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
        });
    });


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