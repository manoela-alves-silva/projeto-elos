<?php

declare(strict_types=1);

/*
 * ESQUECEU A SENHA? — a pessoa cria uma senha nova com o e-mail e o
 * código de recuperação que recebeu ao criar a conta (ou gerou em
 * Minha conta). O código usado deixa de valer e um novo aparece aqui.
 */

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
$novoCodigo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $codigo = trim((string) ($_POST['codigo'] ?? ''));
    $novaSenha = (string) ($_POST['nova_senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

    if ($email === '' || $codigo === '') {
        $mensagemErro = 'Informe seu e-mail e o código de recuperação.';
    } elseif (strlen($novaSenha) < 8) {
        $mensagemErro = 'A nova senha deve ter pelo menos 8 caracteres.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $mensagemErro = 'As senhas não coincidem.';
    } else {
        try {
            $resposta = Api::post('/api/recuperar-senha', [
                'email' => $email,
                'codigo' => $codigo,
                'nova_senha' => $novaSenha,
            ]);

            $novoCodigo = (string) ($resposta['codigo_recuperacao'] ?? '');
        } catch (ApiException $e) {
            $mensagemErro = $e->getMessage();
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

    <title>Recuperar senha | ELOS</title>

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
                    Esqueceu a senha?
                </h1>

                <p>
                    Use o código de recuperação que você guardou
                    ao criar a conta. Sem o código, peça a um
                    gestor para redefinir sua senha.
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


            <?php if ($novoCodigo !== ''): ?>

                <div
                    class="login-message login-info"
                    role="status"
                >
                    Senha alterada. Você já pode entrar com a nova senha.
                </div>

                <div class="codigo-recuperacao" role="status">
                    <p>O código que você usou não vale mais. Seu novo código:</p>
                    <code><?= escapar($novoCodigo) ?></code>
                    <p>Guarde num lugar seguro. Ele só aparece agora.</p>
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

                        <label for="codigo">
                            Código de recuperação
                        </label>

                        <div class="input-wrapper">

                            <span
                                class="input-icon"
                                aria-hidden="true"
                            >
                                #
                            </span>

                            <input
                                id="codigo"
                                name="codigo"
                                type="text"
                                placeholder="abcd-efgh-jkmn"
                                autocomplete="off"
                                autocapitalize="none"
                                spellcheck="false"
                                required
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="nova_senha">
                            Nova senha
                        </label>

                        <div class="input-wrapper">

                            <span
                                class="input-icon password-icon"
                                aria-hidden="true"
                            >
                                ●
                            </span>

                            <input
                                id="nova_senha"
                                name="nova_senha"
                                type="password"
                                placeholder="Mínimo de 8 caracteres"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="show-password"
                                id="showNovaSenha"
                                aria-label="Mostrar senha"
                            >
                                <span></span>
                            </button>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="confirmar_senha">
                            Confirmar nova senha
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
                                placeholder="Repita a nova senha"
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
                        Criar nova senha
                    </button>

                </form>

                <p class="login-signup">
                    Lembrou a senha?
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

    ativarAlternarSenha('nova_senha', 'showNovaSenha');
    ativarAlternarSenha('confirmar_senha', 'showConfirmarSenha');

});
</script>

</body>
</html>
