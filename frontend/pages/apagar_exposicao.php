<?php

declare(strict_types=1);

/*
 * APAGAR EXPOSIÇÃO — só gestores. É definitivo: some a exposição, as
 * datas, o checklist e os anexos. Para confirmar, a pessoa digita o
 * nome da exposição (evita apagar a errada por engano). Quem só quer
 * tirar a mostra de circulação marca "Exposição cancelada".
 */

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;
use Elos\Frontend\Identidade;
use Elos\Frontend\MenuLateral;

use function Elos\Frontend\apiLista;
use function Elos\Frontend\esc;
use function Elos\Frontend\flash;
use function Elos\Frontend\redirecionar;
use function Elos\Frontend\usuarioLogado;

require_once __DIR__ . '/../src/Planejamento.php';
require_once __DIR__ . '/../src/Identidade.php';

$usuario = usuarioLogado('entrar');

if (!$usuario['podeGerenciar']) {
    redirecionar('exposicoes');
}

$eventoId = (int) ($_GET['id'] ?? 0);
$evento = null;

try {
    $evento = $eventoId > 0 ? (Api::get('/api/eventos/' . $eventoId)['evento'] ?? null) : null;
} catch (ApiException) {
    $evento = null;
}

if (!is_array($evento)) {
    redirecionar('exposicoes');
}

$titulo = (string) ($evento['titulo'] ?? '');
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $digitado = trim((string) ($_POST['confirmar_nome'] ?? ''));

    if (mb_strtolower($digitado) !== mb_strtolower(trim($titulo))) {
        $erro = 'O nome digitado não é igual ao da exposição. Nada foi apagado.';
    } else {
        try {
            Api::delete('/api/eventos/' . $eventoId);
            // A imagem de capa fica no frontend: sai junto com a exposição.
            Identidade::apagarArquivo($evento['imagem_capa'] ?? null);
            flash('eventos', 'Exposição "' . $titulo . '" apagada.');
            redirecionar('exposicoes');
        } catch (ApiException $e) {
            $erro = $e->getMessage();
        }
    }
}

$itens = count(apiLista('/api/eventos/' . $eventoId . '/tarefas', 'tarefas') ?? []);
$anexos = count(apiLista('/api/eventos/' . $eventoId . '/anexos', 'anexos') ?? []);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apagar exposição | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/base.css?v=2026-10-29">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=2026-10-29">
    <link rel="stylesheet" href="../assets/css/planejamento.css?v=2026-10-29">
    <link rel="stylesheet" href="../assets/css/conta.css?v=2026-10-29">
</head>
<body>

<div class="app-shell">

    <?php MenuLateral::renderizar('eventos', false, $usuario['nome'], $usuario['primeiroNome'], $usuario['perfil']); ?>

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
                    <p class="eyebrow">Apagar exposição</p>
                    <h1>
                        <?= esc($titulo) ?>
                        <span>Apagar é definitivo e não tem como desfazer.</span>
                    </h1>
                </div>
            </section>

            <?php if ($erro !== ''): ?>
                <div class="aviso aviso-erro" role="alert"><?= esc($erro) ?></div>
            <?php endif; ?>

            <article class="panel conta-senha">
                <div class="panel-header">
                    <h2>O que será apagado</h2>
                </div>

                <form method="post" class="form-elos">
                    <?= \Elos\Frontend\campoCsrf() ?>

                    <ul class="conta-explicacao">
                        <li>a exposição, com as informações e as datas;</li>
                        <li><?= $itens ?> <?= $itens === 1 ? 'item' : 'itens' ?> do checklist<?= $itens > 0 ? ', que saem também do Início e do Calendário' : '' ?>;</li>
                        <li><?= $anexos ?> <?= $anexos === 1 ? 'anexo' : 'anexos' ?>, com os arquivos.</li>
                    </ul>

                    <p class="conta-explicacao">
                        Só quer tirar a mostra de circulação? Volte para a exposição e marque
                        <strong>Exposição cancelada</strong>: ela continua guardada.
                    </p>

                    <div class="campo">
                        <label for="confirmar_nome">Para confirmar, digite o nome da exposição: <strong><?= esc($titulo) ?></strong></label>
                        <input id="confirmar_nome" type="text" name="confirmar_nome" required autocomplete="off">
                    </div>

                    <button type="submit" class="botao-perigo">Apagar exposição</button>
                    <a href="exposicao?id=<?= $eventoId ?>" class="botao-secundario">Voltar sem apagar</a>
                </form>
            </article>

        </div>

    </main>

</div>

</body>
</html>
