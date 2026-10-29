<?php

declare(strict_types=1);

/*
 * NOVO EVENTO DE UM PROJETO (nova sessão do Cine Clube, novo encontro
 * do Café Cultural...) — a ficha do projeto, o dia e onde vai ser. Ao
 * salvar, cria o evento (do tipo do projeto), a ficha, o dia e o
 * horário e, se marcado, o checklist padrão do projeto, com os prazos
 * contados a partir do dia. Depois abre o evento.
 *
 * Se algo secundário falhar (a ficha, um item), o evento não é
 * desfeito: o aviso aparece nele, para completar lá.
 */

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;
use Elos\Frontend\MenuLateral;
use Elos\Frontend\Planejamento;
use Elos\Frontend\Projetos;

use function Elos\Frontend\apiLista;
use function Elos\Frontend\avisoConflito;
use function Elos\Frontend\conflitosNoLocal;
use function Elos\Frontend\dataValida;
use function Elos\Frontend\esc;
use function Elos\Frontend\extraTipoResponsavel;
use function Elos\Frontend\flash;
use function Elos\Frontend\linkExposicao;
use function Elos\Frontend\redirecionar;
use function Elos\Frontend\resolverCadastrosDaExposicao;
use function Elos\Frontend\selectAberto;
use function Elos\Frontend\usuarioLogado;

require_once __DIR__ . '/../src/Planejamento.php';

$projeto = Projetos::dados(defined('ELOS_PROJETO') ? (string) constant('ELOS_PROJETO') : Projetos::CINE_CLUBE);
$enderecoNovo = $projeto['lista'] . '/' . $projeto['rota_nova'];

$usuario = usuarioLogado('entrar');

if (!$usuario['podeGerenciar']) {
    redirecionar($projeto['lista']);
}

$tipos = apiLista('/api/tipos-evento', 'tipos_evento') ?? [];
$tipoDoProjeto = Projetos::idDoTipo($tipos, $projeto['chave']);

$valores = [
    'descricao' => '', 'data' => '', 'horario' => '',
    'responsavel_id' => '', 'local_id' => '', 'responsavel_id_novo' => '', 'local_id_novo' => '',
    'responsavel_tipo_novo' => 'COLETIVO',
];
$valoresFicha = Projetos::valoresDaFicha($projeto, null);
$criarChecklist = true;
$erro = $tipoDoProjeto === null
    ? 'O tipo "' . $projeto['tipo'] . '" ainda não existe no banco. Rode a migração 2026-10-29_projetos.sql.'
    : '';
$conflitos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tipoDoProjeto !== null) {
    foreach ($valores as $campo => $padrao) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? $padrao));
    }
    $criarChecklist = ($_POST['checklist_padrao'] ?? '') === '1';

    $ficha = Projetos::lerFicha($projeto, $_POST);
    $valoresFicha = $ficha['valores'];

    $erro = match (true) {
        $ficha['erro'] !== '' => $ficha['erro'],
        $valores['data'] !== '' && !dataValida($valores['data']) => 'Essa data não existe. Confira o dia e o mês.',
        $valores['horario'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $valores['horario']) => 'Horário inválido.',
        default => '',
    };

    // O local já tem algo nesse dia? Avisa antes de criar; quem confirmar segue.
    if ($erro === '' && $valores['data'] !== '' && ($_POST['confirmar_conflito'] ?? '') !== '1' && ctype_digit($valores['local_id'])) {
        $conflitos = conflitosNoLocal((int) $valores['local_id'], ['abertura' => $valores['data']]);
    }

    $cadastros = ['ids' => [], 'erro' => ''];
    if ($erro === '' && $conflitos === []) {
        // O tipo é sempre o do projeto; responsável e local podem ser novos.
        $cadastros = resolverCadastrosDaExposicao(['tipo_evento_id' => (string) $tipoDoProjeto] + $_POST);
        $erro = $cadastros['erro'];
    }

    if ($erro === '' && $conflitos === []) {
        try {
            $resposta = Api::post('/api/eventos', $cadastros['ids'] + [
                'titulo' => Projetos::tituloDoEvento($projeto, $ficha['dados'], $projeto['novo']),
                'status' => 'PLANEJAMENTO',
                'descricao' => $valores['descricao'] !== '' ? $valores['descricao'] : null,
                'observacoes' => null,
            ]);
            $eventoId = (int) ($resposta['evento']['id'] ?? 0);
        } catch (ApiException $e) {
            $eventoId = 0;
            $erro = $e->getMessage();
        }

        if ($eventoId > 0) {
            $falhas = [];

            try {
                Api::put('/api/eventos/' . $eventoId . '/ficha', ['dados' => $ficha['dados']]);
            } catch (ApiException $e) {
                $falhas[] = 'a ficha (' . $e->getMessage() . ')';
            }

            if ($valores['data'] !== '') {
                $agenda = array_fill_keys(array_keys(Planejamento::CAMPOS_AGENDA), null);
                $agenda['abertura'] = $valores['data'];
                $agenda['horario'] = $valores['horario'] !== '' ? $valores['horario'] : null;

                try {
                    Api::post('/api/eventos/' . $eventoId . '/agenda', $agenda + ['tipo_horario' => null, 'observacoes' => null]);
                } catch (ApiException $e) {
                    $falhas[] = 'o dia (' . $e->getMessage() . ')';
                }
            }

            if ($criarChecklist) {
                $categorias = [];
                foreach (apiLista('/api/categorias-tarefa', 'categorias_tarefa') ?? [] as $categoria) {
                    $categorias[mb_strtolower((string) $categoria['nome'])] = (int) $categoria['id'];
                }

                foreach ($projeto['checklist'] as [$categoria, $titulo, $dias, $prioridade]) {
                    try {
                        Api::post('/api/eventos/' . $eventoId . '/tarefas', [
                            'categoria_id' => $categorias[mb_strtolower($categoria)] ?? null,
                            'titulo' => $titulo,
                            'prazo' => Projetos::prazoDoItem($valores['data'], $dias),
                            'horario' => null,
                            'prioridade' => $prioridade,
                            'status' => 'PENDENTE',
                            'descricao' => null,
                            'observacoes' => null,
                            'usuario_responsavel_id' => null,
                            'responsavel_nome' => null,
                        ]);
                    } catch (ApiException) {
                        $falhas[] = 'o item “' . $titulo . '”';
                    }
                }
            }

            $criado = mb_convert_case($projeto['singular'], MB_CASE_TITLE) . ($projeto['artigo'] === 'a' ? ' criada' : ' criado');
            flash('evento', $falhas === []
                ? $criado . '. Agora é acompanhar o checklist.'
                : $criado . ', mas não foi possível salvar ' . implode(', ', $falhas) . '. Complete aqui.');
            redirecionar(linkExposicao($eventoId));
        }
    }
}

$responsaveis = apiLista('/api/responsaveis', 'responsaveis') ?? [];
$locais = apiLista('/api/locais', 'locais') ?? [];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <!-- Endereço /projeto/novo: links relativos a partir da raiz. -->
    <base href="<?= esc(\Elos\Frontend\raizDoSite()) ?>/">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($projeto['novo']) ?> | <?= esc($projeto['nome']) ?> | ELOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=2026-10-29">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=2026-10-29">
    <link rel="stylesheet" href="assets/css/planejamento.css?v=2026-10-29">
    <link rel="stylesheet" href="assets/css/eventos.css?v=2026-10-29">
    <link rel="stylesheet" href="assets/css/projeto.css?v=2026-10-29">
    <link rel="stylesheet" href="assets/css/<?= esc($projeto['tema']) ?>.css?v=2026-10-29">
    <!-- Ícone do ELOS na aba do navegador, no celular e no app instalado. -->
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/img/icone-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="assets/img/icone-180.png">
    <link rel="manifest" href="assets/elos.webmanifest">
    <meta name="theme-color" content="#123d59">
</head>
<body class="tema-<?= esc($projeto['tema']) ?>">

<div class="app-shell">

    <?php MenuLateral::renderizar($projeto['menu'], false, $usuario['nome'], $usuario['primeiroNome'], $usuario['perfil']); ?>

    <main class="main-content">

        <header class="topbar">
            <a href="<?= esc($projeto['lista']) ?>" class="voltar">← <?= esc($projeto['nome']) ?></a>
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
                    <p class="eyebrow"><?= esc($projeto['sobretitulo']) ?></p>
                    <h1>
                        <?= esc($projeto['novo']) ?>
                        <span><?= esc($projeto['frase_nova']) ?></span>
                    </h1>
                </div>
            </section>

            <?php if ($erro !== ''): ?>
                <div class="aviso aviso-erro" role="alert"><?= esc($erro) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= esc($enderecoNovo) ?>" class="form-elos passos">
                <?= \Elos\Frontend\campoCsrf() ?>

                <section class="panel passo">
                    <div class="passo-cabecalho">
                        <span class="passo-numero">1</span>
                        <div>
                            <h2><?= esc($projeto['passo_ficha'][0]) ?></h2>
                            <p><?= esc($projeto['passo_ficha'][1]) ?></p>
                        </div>
                    </div>
                    <div class="passo-corpo">
                        <?php Projetos::campos($projeto, $valoresFicha); ?>
                        <div class="campo campo-largo">
                            <label for="descricao"><?= esc($projeto['descricao']) ?> <span>(opcional)</span></label>
                            <textarea id="descricao" name="descricao" rows="3"><?= esc($valores['descricao']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="panel passo">
                    <div class="passo-cabecalho">
                        <span class="passo-numero">2</span>
                        <div>
                            <h2>Quando e onde</h2>
                            <p>O dia, o horário e o espaço.</p>
                        </div>
                    </div>
                    <div class="passo-corpo">
                        <div class="campo">
                            <label for="data">Dia</label>
                            <input id="data" type="date" name="data" value="<?= esc($valores['data']) ?>">
                        </div>
                        <div class="campo">
                            <label for="horario">Horário</label>
                            <input id="horario" type="time" name="horario" value="<?= esc($valores['horario']) ?>">
                        </div>
                        <?php selectAberto('local_id', 'Local', $locais, $valores['local_id'], $valores['local_id_novo'], 'Novo local', 'Ex.: Auditório'); ?>
                        <?php selectAberto('responsavel_id', 'Responsável', $responsaveis, $valores['responsavel_id'], $valores['responsavel_id_novo'], 'Novo responsável', 'Ex.: ' . $projeto['nome'], 'campo', extraTipoResponsavel($valores['responsavel_tipo_novo'])); ?>
                    </div>
                    <?php if ($conflitos !== []) { avisoConflito($conflitos, 'Criar mesmo assim'); } ?>
                </section>

                <section class="panel passo">
                    <div class="passo-cabecalho">
                        <span class="passo-numero">3</span>
                        <div>
                            <h2>Checklist</h2>
                            <p>O que precisa ficar pronto até o dia.</p>
                        </div>
                    </div>
                    <div class="passo-corpo passo-checklist">
                        <label class="opcao-checklist">
                            <input type="checkbox" name="checklist_padrao" value="1" <?= $criarChecklist ? 'checked' : '' ?>>
                            <span>Criar o checklist padrão, com os prazos contados a partir do dia:</span>
                        </label>
                        <ul class="checklist-previa">
                            <?php foreach ($projeto['checklist'] as [$categoria, $titulo, $dias]): ?>
                                <li>
                                    <span><?= esc($titulo) ?></span>
                                    <small><?= $dias === 0 ? 'no dia' : ($dias < 0 ? abs($dias) . ' dias antes' : $dias . ' dias depois') ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="dica">Dá para editar, remover e incluir itens depois, na própria página.</p>
                    </div>
                </section>

                <div class="form-botoes">
                    <a href="<?= esc($projeto['lista']) ?>" class="text-link">Cancelar</a>
                    <button type="submit" class="primary-button">Criar <?= esc($projeto['singular']) ?></button>
                </div>
            </form>

        </div>

    </main>

</div>

<script src="assets/js/elos.js?v=2026-10-29"></script>
</body>
</html>
