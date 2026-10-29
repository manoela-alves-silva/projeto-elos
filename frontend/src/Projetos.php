<?php

declare(strict_types=1);

namespace Elos\Frontend;

/**
 * Projetos da galeria: cada um tem área própria no ELOS (lista, página
 * de cada evento, visual), mas todos usam o mesmo checklist, anexos,
 * histórico, calendário e painel ao vivo.
 *
 * Um evento pertence a um projeto pelo tipo (tipos_evento.nome). Tipo
 * que não é de nenhum projeto fica com as exposições, como sempre foi.
 *
 * O que é próprio de cada projeto fica descrito aqui: os campos da
 * ficha (guardada em evento_ficha), os nomes ("sessão", "encontro"), o
 * checklist padrão e os textos das telas. O visual fica no CSS do tema
 * (assets/css/<tema>.css). Para um projeto novo: uma entrada aqui, o
 * tipo no banco (migração) e o CSS do tema.
 */
final class Projetos
{
    public const EXPOSICOES = 'exposicoes';
    public const CINE_CLUBE = 'cine-clube';
    public const CAFE_CULTURAL = 'cafe-cultural';
    public const ARTE_AO_AR_LIVRE = 'arte-ao-ar-livre';
    public const AMOSTRA_DE_TALENTOS = 'amostra-de-talentos';

    private const LISTA = [
        self::EXPOSICOES => [
            'nome' => 'Exposições',
            'tipo' => 'Exposição de Arte',
            'termo' => '',
            'lista' => 'exposicoes',
            'pagina' => 'exposicao',
            'menu' => 'eventos',
            'singular' => 'exposição',
            'plural' => 'Exposições',
            'artigo' => 'a',
            'este' => 'esta',
            'cancelado' => 'Exposição cancelada',
            'momento' => 'Abertura',
        ],

        self::CINE_CLUBE => [
            'nome' => 'Cine Clube',
            'tipo' => 'Cine Clube',
            'termo' => 'cine',
            'lista' => 'cine-clube',
            'pagina' => 'cine-clube',
            'menu' => 'cine-clube',
            'icone' => '▶',
            'tema' => 'cinema',
            'singular' => 'sessão',
            'plural' => 'Sessões',
            'artigo' => 'a',
            'este' => 'esta',
            'novo' => 'Nova sessão',
            'rota_nova' => 'nova-sessao',
            'cancelado' => 'Sessão cancelada',
            'momento' => 'Sessão',
            'sobretitulo' => 'Cine Clube La Salle',
            'frase_lista' => 'Cada sessão com o filme, o debate e tudo o que precisa ficar pronto.',
            'frase_nova' => 'O filme, o dia e onde vai ser. O resto se organiza no checklist.',
            'passo_ficha' => ['O filme', 'Nome, ficha técnica e quem conduz o debate.'],
            'vazio' => ['Nenhuma sessão ainda', 'Crie a primeira sessão do Cine Clube: o filme, o dia e o checklist do que precisa ficar pronto.'],
            'proxima' => 'Próxima sessão',
            'anteriores' => 'Sessões anteriores',
            'capa' => 'Cartaz do filme',
            'descricao' => 'Sinopse ou tema do debate',
            'icone_capa' => '▶',

            // Ficha: o campo que dá nome ao evento, a linha da ficha técnica,
            // as pessoas em destaque e o selo (classificação indicativa).
            'titulo_campo' => 'filme',
            'linha_ficha' => [['direcao', 'Direção: ', ''], ['ano', '', ''], ['duracao_minutos', '', ' min']],
            'destaques' => [['mediacao', 'Debate com']],
            'selo' => 'classificacao',
            'etiquetas' => [],
            'campos' => [
                ['campo' => 'filme', 'rotulo' => 'Filme', 'tipo' => 'texto', 'max' => 200, 'obrigatorio' => true, 'exemplo' => 'Ex.: Central do Brasil', 'largo' => true],
                ['campo' => 'direcao', 'rotulo' => 'Direção', 'tipo' => 'texto', 'max' => 150, 'exemplo' => 'Ex.: Walter Salles'],
                ['campo' => 'ano', 'rotulo' => 'Ano', 'tipo' => 'numero', 'min' => 1888, 'max' => 2100],
                ['campo' => 'duracao_minutos', 'rotulo' => 'Duração (min)', 'tipo' => 'numero', 'min' => 1, 'max' => 1000],
                ['campo' => 'classificacao', 'rotulo' => 'Classificação indicativa', 'tipo' => 'escolha', 'opcoes' => [
                    'L' => 'Livre', '10' => '10 anos', '12' => '12 anos', '14' => '14 anos', '16' => '16 anos', '18' => '18 anos',
                ]],
                ['campo' => 'mediacao', 'rotulo' => 'Debate com', 'tipo' => 'texto', 'max' => 200, 'exemplo' => 'Quem conduz a conversa depois do filme', 'largo' => true],
            ],

            // [categoria, o que fazer, dias em relação ao dia (negativo = antes), prioridade]
            'checklist' => [
                ['Escolha do filme', 'Escolher o filme com a curadoria dos alunos', -21, 'ALTA'],
                ['Direitos de exibição', 'Verificar a licença ou os direitos de exibição do filme', -14, 'ALTA'],
                ['Mediação e debate', 'Convidar e confirmar quem conduz o debate', -10, 'ALTA'],
                ['Divulgação', 'Criar a arte de divulgação (cartaz e redes sociais)', -10, 'MEDIA'],
                ['Divulgação', 'Divulgar nas redes e para as turmas', -7, 'MEDIA'],
                ['Projeção e som', 'Reservar o espaço e testar projetor, som e legendas', -2, 'ALTA'],
                ['Mediação e debate', 'Preparar o roteiro de perguntas do debate', -2, 'MEDIA'],
                ['Recepção do público', 'Organizar a recepção e a lista de presença', 0, 'MEDIA'],
                ['Elaboração do relatório', 'Registrar fotos e escrever o relatório da sessão', 3, 'BAIXA'],
            ],
        ],

        self::CAFE_CULTURAL => [
            'nome' => 'Café Cultural',
            'tipo' => 'Café Cultural',
            'termo' => 'caf',
            'lista' => 'cafe-cultural',
            'pagina' => 'cafe-cultural',
            'menu' => 'cafe-cultural',
            'icone' => '❦',
            'tema' => 'cafe',
            'singular' => 'encontro',
            'plural' => 'Encontros',
            'artigo' => 'o',
            'este' => 'este',
            'novo' => 'Novo encontro',
            'rota_nova' => 'novo-encontro',
            'cancelado' => 'Encontro cancelado',
            'momento' => 'Encontro',
            'sobretitulo' => 'Café Cultural',
            'frase_lista' => 'Encontros com autores e leitores: a obra, a conversa e o café.',
            'frase_nova' => 'Quem vem, qual obra, quando e onde. O resto se organiza no checklist.',
            'passo_ficha' => ['A obra e o autor', 'Quem vem conversar, sobre qual obra e quem faz a mediação.'],
            'vazio' => ['Nenhum encontro ainda', 'Crie o primeiro encontro do Café Cultural: o autor, a obra, o dia e o checklist do que precisa ficar pronto.'],
            'proxima' => 'Próximo encontro',
            'anteriores' => 'Encontros anteriores',
            'capa' => 'Capa do livro',
            'descricao' => 'Sobre a obra e a conversa',
            'icone_capa' => '❦',

            'titulo_campo' => 'obra',
            'linha_ficha' => [['genero', '', ''], ['editora', '', ''], ['ano', '', '']],
            'destaques' => [['autor', 'Com'], ['mediacao', 'Mediação']],
            'selo' => null,
            'etiquetas' => [['autografos', 'Sessão de autógrafos']],
            'campos' => [
                ['campo' => 'obra', 'rotulo' => 'Obra ou tema do encontro', 'tipo' => 'texto', 'max' => 200, 'obrigatorio' => true, 'exemplo' => 'Ex.: Memórias da Baía', 'largo' => true],
                ['campo' => 'autor', 'rotulo' => 'Autor(a) convidado(a)', 'tipo' => 'texto', 'max' => 200, 'obrigatorio' => true, 'exemplo' => 'Nome de quem vem conversar'],
                ['campo' => 'genero', 'rotulo' => 'Gênero', 'tipo' => 'escolha', 'opcoes' => [
                    'Romance' => 'Romance', 'Conto' => 'Conto', 'Crônica' => 'Crônica', 'Poesia' => 'Poesia',
                    'Ensaio' => 'Ensaio', 'Infantojuvenil' => 'Infantojuvenil', 'Quadrinhos' => 'Quadrinhos',
                    'Biografia e memórias' => 'Biografia e memórias', 'Acadêmico' => 'Acadêmico', 'Outro' => 'Outro',
                ]],
                ['campo' => 'editora', 'rotulo' => 'Editora', 'tipo' => 'texto', 'max' => 150],
                ['campo' => 'ano', 'rotulo' => 'Ano de publicação', 'tipo' => 'numero', 'min' => 1500, 'max' => 2100],
                ['campo' => 'mediacao', 'rotulo' => 'Mediação', 'tipo' => 'texto', 'max' => 200, 'exemplo' => 'Quem conduz a conversa', 'largo' => true],
                ['campo' => 'autografos', 'rotulo' => 'Terá sessão de autógrafos', 'tipo' => 'marcar'],
            ],

            'checklist' => [
                ['Convidados', 'Confirmar a participação do autor e a data', -30, 'ALTA'],
                ['Convidados', 'Pedir minibiografia, foto e sinopse da obra', -21, 'MEDIA'],
                ['Divulgação', 'Criar a arte de divulgação', -14, 'MEDIA'],
                ['Divulgação', 'Divulgar nas redes, para as turmas e a comunidade', -10, 'MEDIA'],
                ['Livros e autógrafos', 'Combinar exemplares para venda e autógrafos', -7, 'MEDIA'],
                ['Mediação e debate', 'Preparar o roteiro da conversa com o autor', -5, 'ALTA'],
                ['Alimentação', 'Organizar o café (comidas, bebidas e louça)', -3, 'ALTA'],
                ['Espaço e ambientação', 'Reservar o espaço e arrumar o ambiente (mesas, som e microfone)', -1, 'ALTA'],
                ['Recepção do público', 'Organizar a recepção e a lista de presença', 0, 'MEDIA'],
                ['Agradecimentos', 'Enviar agradecimento ao autor', 2, 'BAIXA'],
                ['Elaboração do relatório', 'Registrar fotos e escrever o relatório do encontro', 3, 'BAIXA'],
            ],
        ],

        self::ARTE_AO_AR_LIVRE => [
            'nome' => 'Arte ao Ar Livre',
            'tipo' => 'Arte ao Ar Livre',
            'termo' => 'ar livre',
            'lista' => 'arte-ao-ar-livre',
            'pagina' => 'arte-ao-ar-livre',
            'menu' => 'arte-ao-ar-livre',
            'icone' => '✿',
            'tema' => 'ar-livre',
            'singular' => 'atividade',
            'plural' => 'Atividades',
            'artigo' => 'a',
            'este' => 'esta',
            'novo' => 'Nova atividade',
            'rota_nova' => 'nova-atividade',
            'cancelado' => 'Atividade cancelada',
            'momento' => 'Atividade',
            'sobretitulo' => 'Arte ao Ar Livre · Varanda Cultural',
            'frase_lista' => 'No intervalo, na Varanda Cultural: depois de ver as exposições, pôr a mão na massa.',
            'frase_nova' => 'A proposta, a exposição que inspira, o dia e os materiais. O resto se organiza no checklist.',
            'passo_ficha' => ['A atividade', 'A proposta, a exposição que inspira, quem conduz e os materiais.'],
            'vazio' => ['Nenhuma atividade ainda', 'Crie a primeira atividade do Arte ao Ar Livre: a proposta, o dia e o checklist do que precisa ficar pronto.'],
            'proxima' => 'Próxima atividade',
            'anteriores' => 'Atividades anteriores',
            'capa' => 'Foto da atividade',
            'descricao' => 'Como vai ser a atividade',
            'icone_capa' => '✿',

            'titulo_campo' => 'atividade',
            'linha_ficha' => [['linguagem', '', ''], ['publico', 'até ', ' pessoas']],
            'destaques' => [['mediacao', 'Com'], ['exposicao', 'Inspirada em']],
            'selo' => null,
            'etiquetas' => [],
            'campos' => [
                ['campo' => 'atividade', 'rotulo' => 'Atividade', 'tipo' => 'texto', 'max' => 200, 'obrigatorio' => true, 'exemplo' => 'Ex.: Colagem de memórias', 'largo' => true],
                ['campo' => 'linguagem', 'rotulo' => 'Linguagem', 'tipo' => 'escolha', 'opcoes' => [
                    'Desenho' => 'Desenho', 'Pintura' => 'Pintura', 'Colagem' => 'Colagem', 'Modelagem' => 'Modelagem',
                    'Gravura' => 'Gravura', 'Fotografia' => 'Fotografia', 'Escrita criativa' => 'Escrita criativa',
                    'Obra coletiva' => 'Obra coletiva', 'Outra' => 'Outra',
                ]],
                ['campo' => 'exposicao', 'rotulo' => 'Exposição que inspira', 'tipo' => 'exposicao'],
                ['campo' => 'mediacao', 'rotulo' => 'Quem conduz', 'tipo' => 'texto', 'max' => 200, 'exemplo' => 'Artista, professor ou educador'],
                ['campo' => 'publico', 'rotulo' => 'Público estimado', 'tipo' => 'numero', 'min' => 1, 'max' => 5000],
                ['campo' => 'materiais', 'rotulo' => 'Materiais', 'tipo' => 'texto', 'max' => 400, 'exemplo' => 'Ex.: papel kraft, giz pastel, revistas, cola', 'largo' => true],
            ],

            'checklist' => [
                ['Proposta da atividade', 'Definir a proposta a partir da exposição em cartaz', -14, 'ALTA'],
                ['Mediação e debate', 'Confirmar quem conduz a atividade', -10, 'ALTA'],
                ['Materiais', 'Listar e separar os materiais artísticos', -7, 'ALTA'],
                ['Materiais', 'Comprar ou pedir o que faltar', -5, 'MEDIA'],
                ['Divulgação', 'Divulgar nas redes e para as turmas', -5, 'MEDIA'],
                ['Espaço e ambientação', 'Combinar o plano B se chover', -2, 'MEDIA'],
                ['Espaço e ambientação', 'Preparar a Varanda Cultural (mesas, proteção do chão, água para limpeza)', 0, 'ALTA'],
                ['Espaço e ambientação', 'Guardar os materiais e limpar o espaço', 0, 'MEDIA'],
                ['Elaboração do relatório', 'Fotografar as produções e escrever o relatório', 1, 'BAIXA'],
            ],
        ],

        self::AMOSTRA_DE_TALENTOS => [
            'nome' => 'Amostra de Talentos',
            'tipo' => 'Amostra de Talentos',
            'termo' => 'amostra',
            'lista' => 'amostra-de-talentos',
            'pagina' => 'amostra-de-talentos',
            'menu' => 'amostra-de-talentos',
            'icone' => '★',
            'tema' => 'palco',
            'singular' => 'edição',
            'plural' => 'Edições',
            'artigo' => 'a',
            'este' => 'esta',
            'novo' => 'Nova edição',
            'rota_nova' => 'nova-edicao',
            'cancelado' => 'Edição cancelada',
            'momento' => 'Amostra',
            'sobretitulo' => 'Amostra de Talentos · Varanda Cultural',
            'frase_lista' => 'Apresentações na Varanda Cultural: as inscrições, a programação e o palco.',
            'frase_nova' => 'A edição, a programação, o dia e quem organiza junto. O resto se organiza no checklist.',
            'passo_ficha' => ['A edição', 'O nome, as apresentações e quem organiza junto com a galeria.'],
            'vazio' => ['Nenhuma edição ainda', 'Crie a primeira edição da Amostra de Talentos: a programação, o dia e o checklist do que precisa ficar pronto.'],
            'proxima' => 'Próxima edição',
            'anteriores' => 'Edições anteriores',
            'capa' => 'Cartaz da edição',
            'descricao' => 'Sobre esta edição',
            'icone_capa' => '★',

            'titulo_campo' => 'edicao',
            // ['programacao', ...] conta as linhas: "5 apresentações"
            'linha_ficha' => [['programacao', '', ['apresentação', 'apresentações']], ['publico', 'até ', ' pessoas']],
            'destaques' => [['apresentacao', 'Apresentação de'], ['organizacao', 'Organização com']],
            'lista_destaque' => 'programacao',
            'selo' => null,
            'etiquetas' => [],
            'campos' => [
                ['campo' => 'edicao', 'rotulo' => 'Nome da edição', 'tipo' => 'texto', 'max' => 200, 'obrigatorio' => true, 'exemplo' => 'Ex.: Amostra de Talentos — 2º semestre', 'largo' => true],
                ['campo' => 'programacao', 'rotulo' => 'Programação (uma apresentação por linha)', 'tipo' => 'linhas', 'max' => 2000, 'exemplo' => "Ex.:\nBanda Maré Alta — música\nGrupo Passos — dança\nAna Clara — poesia", 'largo' => true],
                ['campo' => 'apresentacao', 'rotulo' => 'Quem apresenta', 'tipo' => 'texto', 'max' => 200, 'exemplo' => 'Mestre de cerimônias'],
                ['campo' => 'organizacao', 'rotulo' => 'Quem organiza junto com a galeria', 'tipo' => 'texto', 'max' => 200, 'exemplo' => 'Ex.: Diretório Acadêmico'],
                ['campo' => 'publico', 'rotulo' => 'Público estimado', 'tipo' => 'numero', 'min' => 1, 'max' => 5000],
            ],

            'checklist' => [
                ['Inscrições', 'Abrir as inscrições das apresentações', -30, 'ALTA'],
                ['Divulgação', 'Divulgar a chamada para as inscrições', -28, 'MEDIA'],
                ['Inscrições', 'Encerrar as inscrições e montar a programação', -10, 'ALTA'],
                ['Inscrições', 'Confirmar cada apresentação (horário e o que precisa)', -7, 'ALTA'],
                ['Divulgação', 'Divulgar a programação', -5, 'MEDIA'],
                ['Som e palco', 'Reservar som, microfones e extensões', -5, 'ALTA'],
                ['Espaço e ambientação', 'Combinar o plano B se chover', -2, 'MEDIA'],
                ['Som e palco', 'Montar o palco na Varanda Cultural e passar o som', 0, 'ALTA'],
                ['Recepção do público', 'Organizar a recepção e a ordem das apresentações', 0, 'MEDIA'],
                ['Agradecimentos', 'Agradecer e enviar certificados aos participantes', 3, 'BAIXA'],
                ['Elaboração do relatório', 'Registrar fotos e vídeos e escrever o relatório', 3, 'BAIXA'],
            ],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function dados(string $chave): array
    {
        return ['chave' => $chave] + (self::LISTA[$chave] ?? self::LISTA[self::EXPOSICOES]);
    }

    /** Todos os projetos, na ordem do menu. @return list<string> */
    public static function chaves(): array
    {
        return array_keys(self::LISTA);
    }

    /** Só os projetos com área própria (todos menos as exposições). @return list<string> */
    public static function comArea(): array
    {
        return array_values(array_filter(self::chaves(), static fn(string $c): bool => $c !== self::EXPOSICOES));
    }

    /** Projeto de um tipo de evento, pelo nome do tipo. */
    public static function doTipo(?string $nomeTipo): string
    {
        $nome = mb_strtolower(trim((string) $nomeTipo));

        foreach (self::LISTA as $chave => $projeto) {
            if ($projeto['termo'] !== '' && $nome !== '' && str_contains($nome, $projeto['termo'])) {
                return $chave;
            }
        }

        return self::EXPOSICOES;
    }

    /** Projeto de um evento (precisa de tipo_evento_nome, que a API manda). */
    public static function doEvento(array $evento): string
    {
        return self::doTipo($evento['tipo_evento_nome'] ?? null);
    }

    /** Projeto pelo endereço da página dos eventos ("cine-clube"); null se não for de projeto. */
    public static function daPagina(string $pagina): ?string
    {
        foreach (self::comArea() as $chave) {
            if (self::LISTA[$chave]['pagina'] === $pagina) {
                return $chave;
            }
        }

        return null;
    }

    /**
     * Só os eventos de um projeto.
     *
     * @param list<array<string, mixed>> $eventos
     * @return list<array<string, mixed>>
     */
    public static function filtrar(array $eventos, string $chave): array
    {
        return array_values(array_filter(
            $eventos,
            static fn(array $evento): bool => self::doEvento($evento) === $chave
        ));
    }

    /**
     * Id do tipo de evento do projeto (null se ainda não existe no banco).
     *
     * @param list<array<string, mixed>> $tipos lista de /api/tipos-evento
     */
    public static function idDoTipo(array $tipos, string $chave): ?int
    {
        if ($chave === self::EXPOSICOES) {
            return null;
        }

        foreach ($tipos as $tipo) {
            if (self::doTipo((string) ($tipo['nome'] ?? '')) === $chave) {
                return (int) $tipo['id'];
            }
        }

        return null;
    }

    // ---------------------------------------------------------------
    // Ficha
    // ---------------------------------------------------------------

    /**
     * Exposições para o campo do tipo "exposicao" (id => título): as que
     * não foram canceladas, mais a já escolhida (mesmo que tenha acabado).
     *
     * @return array<int, string>
     */
    public static function exposicoesParaEscolher(int $escolhida = 0): array
    {
        $opcoes = [];

        foreach (self::filtrar(apiLista('/api/eventos', 'eventos') ?? [], self::EXPOSICOES) as $evento) {
            $id = (int) $evento['id'];
            $ativa = !in_array($evento['status'] ?? '', ['CONCLUIDO', 'CANCELADO'], true);
            if ($ativa || $id === $escolhida) {
                $opcoes[$id] = (string) $evento['titulo'];
            }
        }

        asort($opcoes);

        return $opcoes;
    }

    /**
     * Lê e confere os campos da ficha vindos de um formulário.
     *
     * @param array<string, mixed> $projeto dados($chave)
     * @param array<string, mixed> $post
     * @return array{dados: array<string, scalar|null>, valores: array<string, string>, erro: string}
     */
    public static function lerFicha(array $projeto, array $post): array
    {
        $dados = [];
        $valores = [];
        $erro = '';

        foreach ($projeto['campos'] ?? [] as $campo) {
            $nome = $campo['campo'];

            if ($campo['tipo'] === 'marcar') {
                $marcado = ($post[$nome] ?? '') === '1';
                $dados[$nome] = $marcado;
                $valores[$nome] = $marcado ? '1' : '';
                continue;
            }

            $valor = trim((string) ($post[$nome] ?? ''));
            $valores[$nome] = $valor;

            if ($valor === '') {
                $dados[$nome] = null;
                if ($erro === '' && !empty($campo['obrigatorio'])) {
                    $erro = 'Preencha: ' . $campo['rotulo'] . '.';
                }
                continue;
            }

            switch ($campo['tipo']) {
                case 'numero':
                    if (!ctype_digit($valor) || (int) $valor < $campo['min'] || (int) $valor > $campo['max']) {
                        $erro = $erro !== '' ? $erro : $campo['rotulo'] . ': número inválido.';
                    }
                    $dados[$nome] = (int) $valor;
                    break;

                case 'exposicao':
                    if (!ctype_digit($valor) || !isset(self::exposicoesParaEscolher((int) $valor)[(int) $valor])) {
                        $erro = $erro !== '' ? $erro : $campo['rotulo'] . ': escolha uma exposição da lista.';
                    }
                    $dados[$nome] = (int) $valor;
                    break;

                case 'linhas':
                    $dados[$nome] = mb_substr(implode("\n", self::linhas($valor)), 0, (int) ($campo['max'] ?? 2000));
                    $valores[$nome] = (string) $dados[$nome];
                    break;

                case 'escolha':
                    if (!array_key_exists($valor, $campo['opcoes'])) {
                        $erro = $erro !== '' ? $erro : $campo['rotulo'] . ': escolha uma opção da lista.';
                    }
                    $dados[$nome] = $valor;
                    break;

                default:
                    $dados[$nome] = mb_substr($valor, 0, (int) ($campo['max'] ?? 200));
            }
        }

        return ['dados' => $dados, 'valores' => $valores, 'erro' => $erro];
    }

    /**
     * Valores da ficha como texto, para preencher os formulários.
     *
     * @param array<string, mixed>|null $ficha
     * @return array<string, string>
     */
    public static function valoresDaFicha(array $projeto, ?array $ficha): array
    {
        $valores = [];

        foreach ($projeto['campos'] ?? [] as $campo) {
            $valor = $ficha[$campo['campo']] ?? null;
            $valores[$campo['campo']] = $campo['tipo'] === 'marcar'
                ? (!empty($valor) ? '1' : '')
                : (string) ($valor ?? '');
        }

        return $valores;
    }

    /** "Direção: Walter Salles · 1998 · 113 min" */
    public static function linhaDaFicha(array $projeto, ?array $ficha): string
    {
        $partes = [];

        foreach ($projeto['linha_ficha'] ?? [] as [$campo, $antes, $depois]) {
            $valor = (string) ($ficha[$campo] ?? '');

            // [singular, plural]: conta as linhas do campo ("5 apresentações").
            if (is_array($depois)) {
                $total = count(self::linhas($valor));
                if ($total > 0) {
                    $partes[] = $antes . $total . ' ' . ($total === 1 ? $depois[0] : $depois[1]);
                }
                continue;
            }

            if ($valor !== '') {
                $partes[] = $antes . $valor . $depois;
            }
        }

        return implode(' · ', $partes);
    }

    /**
     * Linhas não vazias de um campo de várias linhas (ex.: programação).
     *
     * @return list<string>
     */
    public static function linhas(string $texto): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', $texto) ?: []),
            static fn(string $linha): bool => $linha !== ''
        ));
    }

    /** Nome a mostrar do evento: o campo principal da ficha ou o título. */
    public static function tituloDoEvento(array $projeto, ?array $ficha, string $titulo): string
    {
        $campo = $projeto['titulo_campo'] ?? '';
        $valor = $campo !== '' ? trim((string) ($ficha[$campo] ?? '')) : '';

        return $valor !== '' ? $valor : $titulo;
    }

    /** Tipo de um campo da ficha ("texto", "exposicao"...); "" se não existe. */
    public static function tipoDoCampo(array $projeto, string $campo): string
    {
        foreach ($projeto['campos'] ?? [] as $definicao) {
            if ($definicao['campo'] === $campo) {
                return (string) $definicao['tipo'];
            }
        }

        return '';
    }

    /** Rótulo de uma opção escolhida ("12" -> "12 anos"). */
    public static function rotuloDoValor(array $projeto, string $campo, mixed $valor): string
    {
        foreach ($projeto['campos'] ?? [] as $definicao) {
            if ($definicao['campo'] === $campo) {
                return match ($definicao['tipo']) {
                    'escolha' => (string) ($definicao['opcoes'][(string) $valor] ?? $valor),
                    'exposicao' => self::exposicoesParaEscolher((int) $valor)[(int) $valor] ?? 'Exposição apagada',
                    'marcar' => !empty($valor) ? 'Sim' : 'Não',
                    'linhas' => implode(' · ', self::linhas((string) $valor)),
                    default => (string) $valor,
                };
            }
        }

        return (string) $valor;
    }

    /**
     * Desenha os campos da ficha de um projeto (formulário "novo" e
     * edição na página do evento). $largoClasse é a classe dos campos
     * que ocupam a linha inteira.
     *
     * @param array<string, string> $valores
     */
    public static function campos(array $projeto, array $valores, string $prefixo = '', string $largoClasse = 'campo-largo'): void
    {
        foreach ($projeto['campos'] ?? [] as $campo) {
            $nome = $campo['campo'];
            $id = $prefixo . $nome;
            $valor = $valores[$nome] ?? '';
            $opcional = empty($campo['obrigatorio']) && $campo['tipo'] !== 'marcar' ? ' <span>(opcional)</span>' : '';
            $classe = 'campo' . (!empty($campo['largo']) ? ' ' . $largoClasse : '');

            if ($campo['tipo'] === 'marcar') {
                echo '<label class="' . $classe . ' opcao-ficha"><input type="checkbox" name="' . esc($nome) . '" value="1"'
                    . ($valor === '1' ? ' checked' : '') . '> ' . esc($campo['rotulo']) . '</label>';
                continue;
            }

            echo '<div class="' . $classe . '"><label for="' . esc($id) . '">' . esc($campo['rotulo']) . $opcional . '</label>';

            if ($campo['tipo'] === 'exposicao') {
                echo '<select id="' . esc($id) . '" name="' . esc($nome) . '"><option value="">Nenhuma</option>';
                foreach (self::exposicoesParaEscolher((int) $valor) as $idExposicao => $titulo) {
                    echo '<option value="' . $idExposicao . '"' . ((string) $idExposicao === $valor ? ' selected' : '') . '>' . esc($titulo) . '</option>';
                }
                echo '</select>';
            } elseif ($campo['tipo'] === 'escolha') {
                echo '<select id="' . esc($id) . '" name="' . esc($nome) . '"><option value="">Não informado</option>';
                foreach ($campo['opcoes'] as $opcao => $rotulo) {
                    echo '<option value="' . esc($opcao) . '"' . ((string) $opcao === $valor ? ' selected' : '') . '>' . esc($rotulo) . '</option>';
                }
                echo '</select>';
            } elseif ($campo['tipo'] === 'linhas') {
                echo '<textarea id="' . esc($id) . '" name="' . esc($nome) . '" rows="5" maxlength="' . (int) ($campo['max'] ?? 2000) . '"'
                    . (!empty($campo['exemplo']) ? ' placeholder="' . esc($campo['exemplo']) . '"' : '') . '>' . esc($valor) . '</textarea>';
            } elseif ($campo['tipo'] === 'numero') {
                echo '<input id="' . esc($id) . '" type="number" name="' . esc($nome) . '" min="' . (int) $campo['min']
                    . '" max="' . (int) $campo['max'] . '" value="' . esc($valor) . '">';
            } else {
                echo '<input id="' . esc($id) . '" type="text" name="' . esc($nome) . '" maxlength="' . (int) ($campo['max'] ?? 200) . '"'
                    . (!empty($campo['obrigatorio']) ? ' required' : '')
                    . (!empty($campo['exemplo']) ? ' placeholder="' . esc($campo['exemplo']) . '"' : '')
                    . ' value="' . esc($valor) . '">';
            }

            echo '</div>';
        }
    }

    /**
     * Prazo de um item do checklist padrão: o dia do evento mais os
     * dias do item, sem cair antes de hoje (evento marcado em cima da
     * hora não começa com tudo atrasado). Sem dia, fica sem prazo.
     */
    public static function prazoDoItem(string $dia, int $dias): ?string
    {
        if ($dia === '') {
            return null;
        }

        $prazo = date('Y-m-d', strtotime($dia . ' ' . ($dias >= 0 ? '+' : '') . $dias . ' days'));

        return $dias < 0 && $prazo < hoje() ? min(hoje(), $dia) : $prazo;
    }
}
