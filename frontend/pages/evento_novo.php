<?php

declare(strict_types=1);

/*
 * NOVA EXPOSIÇÃO — o planejamento começa aqui, num fluxo só:
 * 1. informações  2. datas  3. o que a exposição precisa.
 *
 * Ao salvar: cria a exposição, os cursos, a agenda e cada necessidade
 * e abre a exposição pronta. Se algo secundário falhar (um curso, uma
 * necessidade), a exposição não é desfeita: a falha aparece lá, para
 * a professora completar.
 */

use Elos\Frontend\Api;
use Elos\Frontend\ApiException;
use Elos\Frontend\MenuLateral;
use Elos\Frontend\Planejamento;

use function Elos\Frontend\apiLista;
use function Elos\Frontend\avisoConflito;
use function Elos\Frontend\conflitosNoLocal;
use function Elos\Frontend\esc;
use function Elos\Frontend\flash;
use function Elos\Frontend\redirecionar;
use function Elos\Frontend\extraTipoResponsavel;
use function Elos\Frontend\resolverCadastrosDaExposicao;
use function Elos\Frontend\resolverCategoria;
use function Elos\Frontend\resolverCursos;
use function Elos\Frontend\resolverResponsavel;
use function Elos\Frontend\sugestoesDeResponsavel;
use function Elos\Frontend\selectAberto;
use function Elos\Frontend\rotuloPrioridade;
use function Elos\Frontend\usuarioLogado;

use const Elos\Frontend\PRIORIDADES;

require_once __DIR__ . '/../src/Planejamento.php';

$usuario = usuarioLogado('login.php');

if (!$usuario['podeGerenciar']) {
    redirecionar('eventos.php');
}

const CAMPOS_LINHA = ['categoria_id', 'nova_categoria', 'titulo', 'prazo', 'horario', 'responsavel', 'prioridade'];

$categorias = array_values(array_filter(
    apiLista('/api/categorias-tarefa', 'categorias_tarefa') ?? [],
    static fn(array $c): bool => (int) ($c['ativa'] ?? 1) === 1
));
$categoriaPadrao = '';
foreach ($categorias as $categoria) {
    if (mb_strtolower((string) $categoria['nome']) === 'outro') {
        $categoriaPadrao = (string) $categoria['id'];
    }
}

$linhaVazia = array_fill_keys(CAMPOS_LINHA, '');
$linhaVazia['categoria_id'] = $categoriaPadrao;
$linhaVazia['prioridade'] = 'MEDIA';

$valores = [
    'titulo' => '', 'tipo_evento_id' => '', 'responsavel_id' => '', 'local_id' => '',
    'tipo_evento_id_novo' => '', 'responsavel_id_novo' => '', 'local_id_novo' => '',
    'responsavel_tipo_novo' => 'PESSOA', 'cursos_outros' => '',
    'descricao' => '', 'observacoes' => '',
    'montagem_inicio' => '', 'montagem_fim' => '', 'abertura' => '', 'horario' => '',
    'permanencia_inicio' => '', 'permanencia_fim' => '', 'desmontagem_inicio' => '', 'desmontagem_fim' => '',
];
$cursosMarcados = [];
$linhas = [$linhaVazia];
$erro = '';
$conflitos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($valores as $campo => $padrao) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? $padrao));
    }

    $cursosMarcados = array_values(array_filter(array_map('intval', (array) ($_POST['cursos'] ?? []))));

    // Linhas de necessidade: as totalmente vazias são ignoradas.
    $linhas = [];
    foreach ((array) ($_POST['necessidades'] ?? []) as $linha) {
        if (!is_array($linha)) {
            continue;
        }
        $lida = [];
        foreach (CAMPOS_LINHA as $campo) {
            $lida[$campo] = trim((string) ($linha[$campo] ?? ''));
        }
        if ($lida['titulo'] !== '' || $lida['prazo'] !== '' || $lida['nova_categoria'] !== '') {
            $linhas[] = $lida;
        }
    }

    $erro = match (true) {
        $valores['titulo'] === '' => 'Dê um nome à exposição.',
        default => '',
    };

    // Uma necessidade com data ou categoria nova precisa dizer o que é.
    foreach ($linhas as $posicao => $linha) {
        if ($erro === '' && $linha['titulo'] === '') {
            $erro = 'Na necessidade ' . ($posicao + 1) . ', escreva o que precisa ser feito.';
        }
        if ($erro === '' && $linha['categoria_id'] === '__nova__' && $linha['nova_categoria'] === '') {
            $erro = 'Na necessidade ' . ($posicao + 1) . ', escreva o nome da nova categoria.';
        }
    }

    // Galeria já ocupada nessas datas? Avisa antes de criar qualquer coisa;
    // quem confirmar segue em frente. (Um local novo nunca está ocupado.)
    if ($erro === '' && ($_POST['confirmar_conflito'] ?? '') !== '1' && ctype_digit($valores['local_id'])) {
        $conflitos = conflitosNoLocal((int) $valores['local_id'], $valores);
    }

    // Tipo, responsável e local: escolhidos ou escritos como novos
    // (os novos são criados agora, antes da exposição).
    $cadastros = ['ids' => [], 'erro' => ''];
    if ($erro === '' && $conflitos === []) {
        $cadastros = resolverCadastrosDaExposicao($_POST);
        $erro = $cadastros['erro'];
    }

    if ($erro === '' && $conflitos === []) {
        try {
            $resposta = Api::post('/api/eventos', $cadastros['ids'] + [
                'titulo' => $valores['titulo'],
                // O status sai das datas; toda exposição nasce em planejamento.
                'status' => 'PLANEJAMENTO',
                'descricao' => $valores['descricao'] !== '' ? $valores['descricao'] : null,
                'observacoes' => $valores['observacoes'] !== '' ? $valores['observacoes'] : null,
            ]);
            $eventoId = (int) ($resposta['evento']['id'] ?? 0);
        } catch (ApiException $e) {
            $eventoId = 0;
            $erro = $e->getMessage();
        }

        if ($eventoId > 0) {
            $falhas = [];

            $cursosEscolhidos = resolverCursos($cursosMarcados, $valores['cursos_outros']);
            foreach ($cursosEscolhidos['falhas'] as $nomeCurso) {
                $falhas[] = 'o curso “' . $nomeCurso . '”';
            }

            foreach ($cursosEscolhidos['ids'] as $cursoId) {
                try {
                    Api::post('/api/eventos/' . $eventoId . '/cursos', ['curso_id' => $cursoId]);
                } catch (ApiException) {
                    $falhas[] = 'um dos cursos';
                }
            }

            $agenda = [];
            foreach (array_keys(Planejamento::CAMPOS_AGENDA) as $campo) {
                $agenda[$campo] = $valores[$campo] !== '' ? $valores[$campo] : null;
            }
            $agenda['horario'] = $valores['horario'] !== '' ? $valores['horario'] : null;

            if (array_filter($agenda) !== []) {
                try {
                    Api::post('/api/eventos/' . $eventoId . '/agenda', $agenda + ['tipo_horario' => null, 'observacoes' => null]);
                } catch (ApiException $e) {
                    $falhas[] = 'as datas (' . $e->getMessage() . ')';
                }
            }

            $usuariosDoSistema = apiLista('/api/usuarios', 'usuarios') ?? [];

            foreach ($linhas as $linha) {
                $categoria = resolverCategoria($linha['categoria_id'], $linha['nova_categoria']);

                try {
                    Api::post('/api/eventos/' . $eventoId . '/tarefas', [
                        'categoria_id' => $categoria['id'],
                        'titulo' => $linha['titulo'],
                        'prazo' => $linha['prazo'] !== '' ? $linha['prazo'] : null,
                        'horario' => preg_match('/^\d{2}:\d{2}$/', $linha['horario']) ? $linha['horario'] : null,
                        'prioridade' => in_array($linha['prioridade'], PRIORIDADES, true) ? $linha['prioridade'] : 'MEDIA',
                        'status' => 'PENDENTE',
                        'etapa_id' => null,
                        'descricao' => null,
                        'observacoes' => null,
                    ] + resolverResponsavel($linha['responsavel'], $usuariosDoSistema));
                } catch (ApiException) {
                    $falhas[] = '“' . $linha['titulo'] . '”';
                }
            }

            $total = count($linhas);
            $mensagem = 'Exposição criada' . ($total > 0 ? ' com ' . $total . ' necessidade' . ($total === 1 ? '' : 's') : '') . '.';

            if ($falhas !== []) {
                $mensagem .= ' Não foi possível salvar: ' . implode(', ', $falhas) . ' — confira abaixo.';
            }

            flash('evento', $mensagem);
            redirecionar('evento.php?id=' . $eventoId);
        }
    }

    if ($linhas === []) {
        $linhas = [$linhaVazia];
    }
}

$tipos = apiLista('/api/tipos-evento', 'tipos_evento') ?? [];
$responsaveis = apiLista('/api/responsaveis', 'responsaveis') ?? [];
$locais = apiLista('/api/locais', 'locais') ?? [];
$cursos = apiLista('/api/cursos', 'cursos') ?? [];
$usuarios = apiLista('/api/usuarios', 'usuarios') ?? [];

/** Uma linha de necessidade; $indice pode ser "__i__" no modelo do JS. */
function linhaNecessidade(string $indice, array $v, array $categorias, array $usuarios): void
{
    $nome = static fn(string $campo): string => 'necessidades[' . $indice . '][' . $campo . ']';
    $id = static fn(string $campo): string => 'nec-' . $indice . '-' . $campo;
    ?>
    <li class="linha-necessidade">
        <button type="button" class="remover-linha" aria-label="Remover esta necessidade">Remover</button>
        <div class="necessidade-campos">
            <div class="campo campo-categoria">
                <label for="<?= $id('categoria') ?>">Categoria</label>
                <select id="<?= $id('categoria') ?>" name="<?= $nome('categoria_id') ?>" class="js-lista-aberta">
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?= (int) $categoria['id'] ?>" <?= (string) $categoria['id'] === $v['categoria_id'] ? 'selected' : '' ?>><?= esc($categoria['nome']) ?></option>
                    <?php endforeach; ?>
                    <option value="__nova__" <?= $v['categoria_id'] === '__nova__' ? 'selected' : '' ?>>+ Nova categoria…</option>
                </select>
                <div class="novo-item js-novo-item">
                    <input type="text" name="<?= $nome('nova_categoria') ?>" maxlength="100" placeholder="Ex.: Cenografia" value="<?= esc($v['nova_categoria']) ?>" aria-label="Nome da nova categoria">
                </div>
            </div>
            <div class="campo campo-titulo">
                <label for="<?= $id('titulo') ?>">O que precisa ser feito</label>
                <input id="<?= $id('titulo') ?>" type="text" name="<?= $nome('titulo') ?>" maxlength="200" placeholder="Ex.: Transporte das obras até a galeria" value="<?= esc($v['titulo']) ?>">
            </div>
            <div class="campo campo-curto">
                <label for="<?= $id('prazo') ?>">Data</label>
                <input id="<?= $id('prazo') ?>" type="date" name="<?= $nome('prazo') ?>" value="<?= esc($v['prazo']) ?>">
            </div>
            <div class="campo campo-curto">
                <label for="<?= $id('horario') ?>">Horário</label>
                <input id="<?= $id('horario') ?>" type="time" name="<?= $nome('horario') ?>" value="<?= esc($v['horario']) ?>">
            </div>
            <div class="campo">
                <label for="<?= $id('responsavel') ?>">Responsável</label>
                <input id="<?= $id('responsavel') ?>" type="text" name="<?= $nome('responsavel') ?>" maxlength="150" list="sugestoes-responsavel" autocomplete="off" placeholder="Nome de quem vai cuidar" value="<?= esc($v['responsavel']) ?>">
            </div>
            <div class="campo">
                <label for="<?= $id('prioridade') ?>">Prioridade</label>
                <select id="<?= $id('prioridade') ?>" name="<?= $nome('prioridade') ?>">
                    <?php foreach (PRIORIDADES as $prioridade): ?>
                        <option value="<?= $prioridade ?>" <?= $prioridade === $v['prioridade'] ? 'selected' : '' ?>><?= esc(rotuloPrioridade($prioridade)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </li>
    <?php
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova exposição | ELOS</title>
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
            <a href="eventos.php" class="text-link">← Exposições</a>
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
                    <p class="eyebrow">Nova exposição</p>
                    <h1>
                        Vamos planejar
                        <span>Conte o que é, quando acontece e o que vai precisar.</span>
                    </h1>
                </div>
            </section>

            <?php if ($erro !== ''): ?>
                <div class="aviso aviso-erro" role="alert"><?= esc($erro) ?></div>
            <?php elseif ($conflitos !== []): ?>
                <div class="aviso aviso-conflito" role="status">O local já está ocupado nessas datas. Veja o aviso no fim da página antes de criar.</div>
            <?php endif; ?>

            <form method="post" action="evento_novo.php" class="form-elos passos">
                <?= \Elos\Frontend\campoCsrf() ?>

                <!-- 1. INFORMAÇÕES -->
                <section class="panel passo">
                    <div class="passo-cabecalho">
                        <span class="passo-numero">1</span>
                        <div>
                            <h2>A exposição</h2>
                            <p>Nome, tipo, quem é responsável e onde acontece.</p>
                        </div>
                    </div>

                    <div class="passo-corpo">
                        <div class="campo campo-largo">
                            <label for="titulo">Nome da exposição</label>
                            <input id="titulo" type="text" name="titulo" required maxlength="200" placeholder="Ex.: Exposição sobre Meio Ambiente" value="<?= esc($valores['titulo']) ?>">
                        </div>

                        <?php selectAberto('tipo_evento_id', 'Tipo', $tipos, $valores['tipo_evento_id'], $valores['tipo_evento_id_novo'], 'Novo tipo', 'Ex.: Mostra acadêmica'); ?>
                        <?php selectAberto('responsavel_id', 'Responsável', $responsaveis, $valores['responsavel_id'], $valores['responsavel_id_novo'], 'Novo responsável', 'Nome do responsável', 'campo', extraTipoResponsavel($valores['responsavel_tipo_novo'])); ?>
                        <?php selectAberto('local_id', 'Local', $locais, $valores['local_id'], $valores['local_id_novo'], 'Novo local', 'Ex.: Auditório do bloco B'); ?>

                        <fieldset class="campo campo-largo">
                            <legend>Cursos envolvidos <span>(opcional)</span></legend>
                            <?php if ($cursos !== []): ?>
                                <div class="opcoes-marcar">
                                    <?php foreach ($cursos as $curso): ?>
                                        <label class="opcao-marcar">
                                            <input type="checkbox" name="cursos[]" value="<?= (int) $curso['id'] ?>" <?= in_array((int) $curso['id'], $cursosMarcados, true) ? 'checked' : '' ?>>
                                            <span><?= esc($curso['nome'] ?? '') ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <input type="text" name="cursos_outros" class="cursos-outros" value="<?= esc($valores['cursos_outros']) ?>" placeholder="<?= $cursos !== [] ? 'Outros cursos (separe por vírgula)' : 'Cursos envolvidos (separe por vírgula)' ?>" aria-label="Outros cursos envolvidos">
                        </fieldset>

                        <div class="campo campo-metade">
                            <label for="descricao">Descrição <span>(opcional)</span></label>
                            <textarea id="descricao" name="descricao" rows="3" placeholder="Sobre o que é a exposição"><?= esc($valores['descricao']) ?></textarea>
                        </div>

                        <div class="campo campo-metade">
                            <label for="observacoes">Observações <span>(opcional)</span></label>
                            <textarea id="observacoes" name="observacoes" rows="3"><?= esc($valores['observacoes']) ?></textarea>
                        </div>
                    </div>
                </section>

                <!-- 2. DATAS -->
                <section class="panel passo">
                    <div class="passo-cabecalho">
                        <span class="passo-numero">2</span>
                        <div>
                            <h2>Datas</h2>
                            <p>Preencha o que já souber; o resto pode ser definido depois. Tudo vai para o calendário.</p>
                        </div>
                    </div>

                    <div class="passo-corpo">
                        <?php
                        $intervalos = [
                            'Montagem' => ['montagem_inicio', 'montagem_fim'],
                            'Em cartaz' => ['permanencia_inicio', 'permanencia_fim'],
                            'Desmontagem' => ['desmontagem_inicio', 'desmontagem_fim'],
                        ];
                        ?>
                        <?php foreach ($intervalos as $rotulo => [$inicio, $fim]): ?>
                            <fieldset class="campo campo-duplo">
                                <legend><?= esc($rotulo) ?></legend>
                                <input type="date" name="<?= $inicio ?>" value="<?= esc($valores[$inicio]) ?>" aria-label="<?= esc($rotulo) ?> — início">
                                <span>até</span>
                                <input type="date" name="<?= $fim ?>" value="<?= esc($valores[$fim]) ?>" aria-label="<?= esc($rotulo) ?> — fim">
                            </fieldset>
                        <?php endforeach; ?>
                        <fieldset class="campo campo-duplo">
                            <legend>Abertura</legend>
                            <input type="date" name="abertura" value="<?= esc($valores['abertura']) ?>" aria-label="Data da abertura">
                            <span>às</span>
                            <input type="time" name="horario" value="<?= esc($valores['horario']) ?>" aria-label="Horário da abertura">
                        </fieldset>
                    </div>
                </section>

                <!-- 3. NECESSIDADES -->
                <section class="panel passo">
                    <div class="passo-cabecalho">
                        <span class="passo-numero">3</span>
                        <div>
                            <h2>O que esta exposição precisa</h2>
                            <p>Registre os recursos, serviços e providências necessários para a realização da exposição. Novos itens podem ser incluídos a qualquer momento.</p>
                        </div>
                    </div>

                    <ul class="linhas-necessidades" id="linhasNecessidades">
                        <?php foreach ($linhas as $indice => $linha): ?>
                            <?php linhaNecessidade((string) $indice, $linha, $categorias, $usuarios); ?>
                        <?php endforeach; ?>
                    </ul>

                    <button type="button" class="adicionar-linha" id="adicionarLinha">+ Adicionar outra necessidade</button>

                    <template id="modeloLinha">
                        <?php linhaNecessidade('__i__', $linhaVazia, $categorias, $usuarios); ?>
                    </template>
                </section>

                <?php if ($conflitos !== []) { avisoConflito($conflitos, 'Criar mesmo assim'); } ?>

                <div class="panel concluir-cadastro">
                    <p>Tudo entra como pendente. Você acompanha e marca o que for resolvido dentro da exposição.</p>
                    <a href="eventos.php" class="text-link">Cancelar</a>
                    <button type="submit" class="primary-button">Criar exposição</button>
                </div>

            </form>

        </div>
    </main>
</div>

<?php sugestoesDeResponsavel($usuarios); ?>
<script src="../assets/js/elos.js"></script>
<script>
(function () {
    const lista = document.getElementById('linhasNecessidades');
    const modelo = document.getElementById('modeloLinha');
    let proximo = lista.children.length;

    const prepararLinha = function (linha) {
        window.ELOS.prepararListasAbertas(linha);

        linha.querySelector('.remover-linha').addEventListener('click', function () {
            if (lista.children.length > 1) {
                linha.remove();
            } else {
                linha.querySelectorAll('input[type="text"], input[type="date"], input[type="time"]').forEach(function (campo) {
                    campo.value = '';
                });
            }
        });
    };

    Array.from(lista.children).forEach(prepararLinha);

    document.getElementById('adicionarLinha').addEventListener('click', function () {
        const html = modelo.innerHTML.replaceAll('__i__', String(proximo++));
        lista.insertAdjacentHTML('beforeend', html);
        const nova = lista.lastElementChild;
        prepararLinha(nova);
        nova.querySelector('input[name$="[titulo]"]').focus();
    });
})();
</script>

</body>
</html>
