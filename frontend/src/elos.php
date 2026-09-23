<?php

declare(strict_types=1);

namespace Elos\Frontend;

/*
 * Funções compartilhadas pelas telas centradas na exposição
 * (Início, Exposições, Exposição, Nova exposição, Calendário, Relatório).
 */

require_once __DIR__ . '/Api.php';
require_once __DIR__ . '/MenuLateral.php';

const PRIORIDADES = ['BAIXA', 'MEDIA', 'ALTA'];
const STATUS_EVENTO = ['PLANEJAMENTO', 'EM_ANDAMENTO', 'CONCLUIDO', 'CANCELADO'];
const MESES = [
    1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
    5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
    9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
];

/**
 * Garante sessão e usuário logado; redireciona para o login se não houver.
 *
 * @return array{id: int, nome: string, perfil: string, primeiroNome: string, podeGerenciar: bool}
 */
function usuarioLogado(string $caminhoLogin): array
{
    iniciarSessao();

    $usuario = $_SESSION['usuario'] ?? null;

    if (!is_array($usuario) || empty($usuario['id'])) {
        header('Location: ' . $caminhoLogin);
        exit();
    }

    // O perfil pode ter mudado na tela Equipe desde o login: confere
    // com o backend (que já relê do banco a cada requisição).
    foreach (apiLista('/api/usuarios', 'usuarios') ?? [] as $pessoa) {
        if ((int) ($pessoa['id'] ?? 0) === (int) $usuario['id'] && isset($pessoa['perfil'])) {
            $_SESSION['usuario']['perfil'] = $usuario['perfil'] = (string) $pessoa['perfil'];
            break;
        }
    }

    $nome = (string) ($usuario['nome'] ?? 'Usuário');
    $perfil = (string) ($usuario['perfil'] ?? 'COLABORADOR');

    return [
        'id' => (int) $usuario['id'],
        'nome' => $nome,
        'perfil' => $perfil,
        'primeiroNome' => explode(' ', trim($nome))[0] ?: $nome,
        // Mesma regra do backend: criar/editar exige GESTOR (ou ADMIN).
        'podeGerenciar' => in_array($perfil, ['ADMIN', 'GESTOR'], true),
    ];
}

function esc(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/**
 * GET na API sem derrubar a página: null quando falha.
 */
function apiGet(string $path): ?array
{
    try {
        return Api::get($path);
    } catch (ApiException) {
        return null;
    }
}

/**
 * Lista contida em uma resposta da API (ex.: ["tarefas" => [...]]).
 * null = não foi possível carregar; [] = nenhum registro.
 *
 * @return list<array<string, mixed>>|null
 */
function apiLista(string $path, string $chave): ?array
{
    $dados = apiGet($path);

    if ($dados === null) {
        return null;
    }

    $lista = $dados[$chave] ?? [];

    return is_array($lista) ? array_values(array_filter($lista, 'is_array')) : [];
}

/** Mensagem de uma página para a próxima (Post/Redirect/Get). */
function flash(string $chave, ?string $mensagem = null): string
{
    if ($mensagem !== null) {
        $_SESSION['_flash_' . $chave] = $mensagem;

        return $mensagem;
    }

    $valor = (string) ($_SESSION['_flash_' . $chave] ?? '');
    unset($_SESSION['_flash_' . $chave]);

    return $valor;
}

function redirecionar(string $url): never
{
    header('Location: ' . $url);
    exit();
}

// ---------------------------------------------------------------
// Datas
// ---------------------------------------------------------------

function hoje(): string
{
    return date('Y-m-d');
}

/** "2026-10-19" → "19/10/2026"; vazio → $vazio. */
function dataBr(?string $data, string $vazio = 'Sem data'): string
{
    if ($data === null || $data === '') {
        return $vazio;
    }

    $timestamp = strtotime($data);

    return $timestamp === false ? $data : date('d/m/Y', $timestamp);
}

/** "19/10/2026 às 14:00" (horário vindo do campo próprio ou da data-hora). */
function dataHoraBr(?string $data, ?string $horario = null, string $vazio = 'Sem data'): string
{
    if ($data === null || $data === '') {
        return $vazio;
    }

    $texto = dataBr($data);

    if ($horario !== null && $horario !== '') {
        return $texto . ' às ' . substr($horario, 0, 5);
    }

    if (strlen($data) > 10 && ($timestamp = strtotime($data)) !== false) {
        return $texto . ' às ' . date('H:i', $timestamp);
    }

    return $texto;
}

/** "19/10" — usado nas listas curtas. */
function diaMes(string $data): string
{
    $timestamp = strtotime($data);

    return $timestamp === false ? $data : date('d/m', $timestamp);
}

/** "out" — mês abreviado em português. */
function mesAbreviado(string $data): string
{
    $timestamp = strtotime($data);

    return $timestamp === false ? '' : mb_substr(MESES[(int) date('n', $timestamp)], 0, 3);
}

function intervaloBr(?string $inicio, ?string $fim, string $vazio = 'Não definida'): string
{
    if (!$inicio && !$fim) {
        return $vazio;
    }

    if (!$inicio || !$fim || $inicio === $fim) {
        return dataBr($inicio ?: $fim);
    }

    return dataBr($inicio) . ' → ' . dataBr($fim);
}

/** Dias de hoje até a data (negativo = já passou). */
function diasAte(string $data): int
{
    return (int) floor((strtotime(substr($data, 0, 10)) - strtotime(hoje())) / 86400);
}

/** "hoje", "amanhã", "em 5 dias", "há 3 dias". */
function distanciaRelativa(string $data): string
{
    $dias = diasAte($data);

    return match (true) {
        $dias === 0 => 'hoje',
        $dias === 1 => 'amanhã',
        $dias === -1 => 'ontem',
        $dias > 1 => 'em ' . $dias . ' dias',
        default => 'há ' . abs($dias) . ' dias',
    };
}

/**
 * Período de uma exposição a partir da agenda: permanência, com
 * alternativas quando ainda não estiver toda definida.
 *
 * @param array<string, mixed>|null $agenda
 * @return array{inicio: ?string, fim: ?string}
 */
function periodoDaExposicao(?array $agenda): array
{
    if ($agenda === null) {
        return ['inicio' => null, 'fim' => null];
    }

    return [
        'inicio' => $agenda['permanencia_inicio'] ?? $agenda['abertura'] ?? $agenda['montagem_inicio'] ?? null,
        'fim' => $agenda['permanencia_fim'] ?? $agenda['desmontagem_fim'] ?? null,
    ];
}

/**
 * Período em que a exposição ocupa o local: do primeiro dia marcado
 * (normalmente a montagem) ao último (normalmente a desmontagem).
 * Mesma regra do backend.
 *
 * @param array<string, mixed> $datas campos da agenda (montagem_inicio…)
 * @return array{inicio: string, fim: string}|null
 */
function periodoDeOcupacao(array $datas): ?array
{
    $ordem = ['montagem_inicio', 'montagem_fim', 'abertura', 'permanencia_inicio',
        'permanencia_fim', 'desmontagem_inicio', 'desmontagem_fim'];
    $marcadas = array_values(array_filter(
        array_map(static fn(string $campo): string => trim((string) ($datas[$campo] ?? '')), $ordem),
        static fn(string $data): bool => $data !== ''
    ));

    if ($marcadas === []) {
        return null;
    }

    return ['inicio' => $marcadas[0], 'fim' => $marcadas[count($marcadas) - 1]];
}

/**
 * Outras exposições que já ocupam o local nesse período.
 * Lista vazia quando não há local, datas, ou a consulta falhar.
 *
 * @param array<string, mixed> $datas
 * @return array<int, array<string, mixed>>
 */
function conflitosNoLocal(int $localId, array $datas, ?int $excetoId = null): array
{
    $periodo = periodoDeOcupacao($datas);

    if ($localId < 1 || $periodo === null) {
        return [];
    }

    $consulta = http_build_query([
        'local_id' => $localId,
        'inicio' => $periodo['inicio'],
        'fim' => $periodo['fim'],
        'exceto' => $excetoId,
    ]);

    return apiLista('/api/eventos/conflitos?' . $consulta, 'conflitos') ?? [];
}

/**
 * Aviso de local ocupado, com botão para salvar mesmo assim. Vai dentro
 * do formulário: o botão reenvia tudo com confirmar_conflito=1.
 *
 * @param array<int, array<string, mixed>> $conflitos
 */
function avisoConflito(array $conflitos, string $textoBotao = 'Salvar mesmo assim'): void
{
    ?>
    <div class="aviso aviso-conflito" role="alert">
        <p><strong>O local já está ocupado nessas datas.</strong></p>
        <ul>
            <?php foreach ($conflitos as $conflito): ?>
                <li>
                    <a href="evento.php?id=<?= (int) $conflito['id'] ?>"><?= esc($conflito['titulo']) ?></a>:
                    <?= esc(dataBr($conflito['ocupacao_inicio'])) ?> a <?= esc(dataBr($conflito['ocupacao_fim'])) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p>Confira as datas ou escolha outro local. Se as duas cabem no espaço, pode salvar assim mesmo.</p>
        <button type="submit" name="confirmar_conflito" value="1" class="botao-secundario"><?= esc($textoBotao) ?></button>
    </div>
    <?php
}

// ---------------------------------------------------------------
// Rótulos
// ---------------------------------------------------------------

function rotuloStatusEvento(string $status): string
{
    return match ($status) {
        'PLANEJAMENTO' => 'Planejamento',
        'EM_ANDAMENTO' => 'Em andamento',
        'CONCLUIDO' => 'Concluído',
        'CANCELADO' => 'Cancelado',
        default => $status,
    };
}

function classeStatusEvento(string $status): string
{
    return match ($status) {
        'EM_ANDAMENTO' => 'status-andamento',
        'CONCLUIDO' => 'status-concluido',
        'CANCELADO' => 'status-cancelado',
        default => 'status-planejamento',
    };
}

function rotuloPrioridade(string $prioridade): string
{
    return match ($prioridade) {
        'ALTA' => 'Alta',
        'MEDIA' => 'Média',
        'BAIXA' => 'Baixa',
        default => $prioridade,
    };
}

function classePrioridade(string $prioridade): string
{
    return match ($prioridade) {
        'ALTA' => 'priority-alta',
        'MEDIA' => 'priority-media',
        default => 'priority-baixa',
    };
}

function rotuloTipoHorario(string $tipo): string
{
    return match ($tipo) {
        'HORARIO_ESPECIFICO' => 'Horário específico',
        'DIA_TODO' => 'Dia todo',
        'TURNO_MANHA' => 'Turno da manhã',
        'TURNO_NOITE' => 'Turno da noite',
        'MANHA_E_NOITE' => 'Manhã e noite',
        default => $tipo,
    };
}

/** Exposições "vivas": ainda não concluídas nem canceladas. */
function exposicaoAtiva(array $evento): bool
{
    return !in_array($evento['status'] ?? '', ['CONCLUIDO', 'CANCELADO'], true);
}

// ---------------------------------------------------------------
// Listas abertas: escolher um existente OU escrever um novo
// ---------------------------------------------------------------

/** Valor da opção "+ Novo…" nos selects de listas abertas. */
const OPCAO_NOVO = '__nova__';

/**
 * Cadastros que a professora pode ampliar enquanto preenche:
 * rota da API, chave da lista, chave do item criado e o nome usado
 * nas mensagens.
 */
const CADASTROS = [
    'categoria' => ['/api/categorias-tarefa', 'categorias_tarefa', 'categoria_tarefa', 'a categoria'],
    'tipo' => ['/api/tipos-evento', 'tipos_evento', 'tipo_evento', 'o tipo'],
    'responsavel' => ['/api/responsaveis', 'responsaveis', 'responsavel', 'o responsável'],
    'local' => ['/api/locais', 'locais', 'local', 'o local'],
    'curso' => ['/api/cursos', 'cursos', 'curso', 'o curso'],
];

const TIPOS_RESPONSAVEL = [
    'PESSOA' => 'Pessoa',
    'SETOR' => 'Setor',
    'CURSO' => 'Curso',
    'COLETIVO' => 'Coletivo',
    'INSTITUICAO' => 'Instituição',
    'OUTRO' => 'Outro',
];

/**
 * Item escolhido num select de lista aberta: o id existente, ou o
 * nome digitado em "+ Novo…", que é criado pela API. Se o nome já
 * existir (mesmo com outra caixa/espaços), reaproveita em vez de
 * duplicar.
 *
 * @param array<string, mixed> $extras  Campos a mais para criar (ex.: tipo do responsável).
 * @return array{id: ?int, erro: string}
 */
function resolverCadastro(string $cadastro, string $escolha, string $novoNome, array $extras = []): array
{
    [$rota, $chaveLista, $chaveItem, $rotulo] = CADASTROS[$cadastro];

    if ($escolha !== OPCAO_NOVO) {
        $id = (int) $escolha;

        return $id > 0 ? ['id' => $id, 'erro' => ''] : ['id' => null, 'erro' => 'Escolha ' . $rotulo . '.'];
    }

    $novoNome = trim($novoNome);

    if ($novoNome === '') {
        return ['id' => null, 'erro' => 'Escreva ' . $rotulo . ' que deseja adicionar.'];
    }

    $buscar = static function () use ($rota, $chaveLista, $novoNome): ?int {
        foreach (apiLista($rota, $chaveLista) ?? [] as $item) {
            if (mb_strtolower(trim((string) ($item['nome'] ?? ''))) === mb_strtolower($novoNome)) {
                return (int) $item['id'];
            }
        }

        return null;
    };

    $existente = $buscar();

    if ($existente !== null) {
        return ['id' => $existente, 'erro' => ''];
    }

    try {
        $resposta = Api::post($rota, ['nome' => $novoNome] + $extras);

        return ['id' => (int) ($resposta[$chaveItem]['id'] ?? 0) ?: null, 'erro' => ''];
    } catch (ApiException $e) {
        $existente = $e->statusCode === 409 ? $buscar() : null;

        return $existente !== null ? ['id' => $existente, 'erro' => ''] : ['id' => null, 'erro' => $e->getMessage()];
    }
}

/** @return array{id: ?int, erro: string} */
function resolverCategoria(string $escolha, string $novaCategoria): array
{
    return resolverCadastro('categoria', $escolha, $novaCategoria);
}

/**
 * Tipo, responsável e local de uma exposição, vindos do formulário
 * (cada um pode ter sido escrito como novo).
 *
 * @return array{ids: array{tipo_evento_id: ?int, responsavel_id: ?int, local_id: ?int}, erro: string}
 */
function resolverCadastrosDaExposicao(array $post): array
{
    $tipoResponsavel = (string) ($post['responsavel_tipo_novo'] ?? 'PESSOA');
    $tipoResponsavel = array_key_exists($tipoResponsavel, TIPOS_RESPONSAVEL) ? $tipoResponsavel : 'PESSOA';

    $campos = [
        'tipo_evento_id' => ['tipo', []],
        'responsavel_id' => ['responsavel', ['tipo' => $tipoResponsavel]],
        'local_id' => ['local', []],
    ];

    $ids = [];

    foreach ($campos as $campo => [$cadastro, $extras]) {
        $resultado = resolverCadastro(
            $cadastro,
            trim((string) ($post[$campo] ?? '')),
            (string) ($post[$campo . '_novo'] ?? ''),
            $extras
        );

        if ($resultado['erro'] !== '') {
            return ['ids' => [], 'erro' => $resultado['erro']];
        }

        $ids[$campo] = $resultado['id'];
    }

    return ['ids' => $ids, 'erro' => ''];
}

/**
 * Cursos marcados + cursos escritos no campo "outros" (separados por
 * vírgula). Os escritos que ainda não existem são criados.
 *
 * @return array{ids: list<int>, falhas: list<string>}
 */
function resolverCursos(array $marcados, string $outros): array
{
    $ids = array_values(array_filter(array_map('intval', $marcados)));
    $falhas = [];

    foreach (array_filter(array_map('trim', explode(',', $outros))) as $nome) {
        $resultado = resolverCadastro('curso', OPCAO_NOVO, $nome);

        if ($resultado['id'] !== null) {
            $ids[] = $resultado['id'];
        } else {
            $falhas[] = $nome;
        }
    }

    return ['ids' => array_values(array_unique($ids)), 'falhas' => $falhas];
}

/**
 * Select de lista aberta: as opções existentes + "+ Novo…", com o
 * campo para escrever o novo logo abaixo (aparece ao escolher).
 *
 * @param list<array<string, mixed>> $opcoes
 */
function selectAberto(
    string $nome,
    string $rotulo,
    array $opcoes,
    string $atual,
    string $novoAtual,
    string $rotuloNovo,
    string $exemplo,
    string $classe = 'campo',
    string $extraNovo = ''
): void {
    ?>
    <div class="<?= esc($classe) ?>">
        <label for="<?= esc($nome) ?>"><?= esc($rotulo) ?></label>
        <select id="<?= esc($nome) ?>" name="<?= esc($nome) ?>" class="js-lista-aberta" required>
            <?php if ($atual === '' && $opcoes !== []): ?>
                <option value="">Escolha…</option>
            <?php endif; ?>
            <?php foreach ($opcoes as $opcao): ?>
                <option value="<?= (int) $opcao['id'] ?>" <?= (string) $opcao['id'] === $atual ? 'selected' : '' ?>><?= esc($opcao['nome'] ?? '') ?></option>
            <?php endforeach; ?>
            <option value="<?= OPCAO_NOVO ?>" <?= $atual === OPCAO_NOVO || $opcoes === [] ? 'selected' : '' ?>>+ <?= esc($rotuloNovo) ?>…</option>
        </select>
        <div class="novo-item js-novo-item">
            <input
                type="text"
                name="<?= esc($nome) ?>_novo"
                maxlength="150"
                placeholder="<?= esc($exemplo) ?>"
                value="<?= esc($novoAtual) ?>"
                aria-label="<?= esc($rotuloNovo) ?>"
                data-obrigatorio
            >
            <?= $extraNovo ?>
        </div>
    </div>
    <?php
}

/** Escolha do tipo de um responsável novo (Pessoa, Setor…). */
function extraTipoResponsavel(string $atual): string
{
    $html = '<select name="responsavel_tipo_novo" aria-label="Tipo do novo responsável">';

    foreach (TIPOS_RESPONSAVEL as $valor => $rotulo) {
        $html .= '<option value="' . $valor . '"' . ($valor === $atual ? ' selected' : '') . '>' . esc($rotulo) . '</option>';
    }

    return $html . '</select>';
}

// ---------------------------------------------------------------
// Responsável de uma necessidade: texto livre
// ---------------------------------------------------------------

/**
 * O responsável é escrito livremente (qualquer pessoa). Se o nome for
 * de alguém com conta no sistema, a necessidade também fica ligada a
 * essa conta — assim a pessoa vê o item em "Suas pendências" e pode
 * marcá-lo. Caso contrário, fica só o nome.
 *
 * @param list<array<string, mixed>> $usuarios
 * @return array{usuario_responsavel_id: ?int, responsavel_nome: ?string}
 */
function resolverResponsavel(string $texto, array $usuarios): array
{
    $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? '');

    if ($texto === '') {
        return ['usuario_responsavel_id' => null, 'responsavel_nome' => null];
    }

    foreach ($usuarios as $pessoa) {
        if (mb_strtolower(trim((string) ($pessoa['nome'] ?? ''))) === mb_strtolower($texto)) {
            return ['usuario_responsavel_id' => (int) $pessoa['id'], 'responsavel_nome' => null];
        }
    }

    return ['usuario_responsavel_id' => null, 'responsavel_nome' => mb_substr($texto, 0, 150)];
}

/** Nome do responsável para exibir (conta do sistema ou texto livre). */
function nomeDoResponsavel(array $tarefa): string
{
    return (string) ($tarefa['responsavel_exibicao'] ?? $tarefa['usuario_responsavel_nome'] ?? $tarefa['responsavel_nome'] ?? '');
}

/**
 * Sugestões para o campo de responsável (pessoas com conta). A lista
 * é só ajuda: qualquer outro nome pode ser escrito.
 *
 * @param list<array<string, mixed>> $usuarios
 */
function sugestoesDeResponsavel(array $usuarios): void
{
    echo '<datalist id="sugestoes-responsavel">';

    foreach ($usuarios as $pessoa) {
        echo '<option value="' . esc($pessoa['nome'] ?? '') . '"></option>';
    }

    echo '</datalist>';
}
