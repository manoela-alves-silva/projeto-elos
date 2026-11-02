<?php

declare(strict_types=1);

namespace Elos\Frontend;

/**
 * Os projetos da galeria: as Exposições e os projetos de um dia só
 * (Cine Clube, Café Cultural, Arte ao Ar Livre, Amostra de Talentos).
 *
 * Cada projeto tem seus tipos de evento: a coluna tipos_evento.projeto
 * diz a que projeto o tipo pertence (vazia = Exposições). Não depende
 * do nome, então renomear um tipo não muda nada. Cada projeto define aqui os nomes usados nas telas, o endereço, o tema
 * visual, os campos da ficha (o filme, o convidado, a programação...) e
 * o checklist padrão. Nada disso fica no banco: o banco guarda só os
 * valores da ficha (eventos_fichas).
 *
 * Tipos de campo da ficha:
 *   texto  — uma linha;          numero — número (com 'sufixo' opcional);
 *   opcoes — lista ('opcoes');   linhas — várias linhas (uma por item);
 *   data   — dia;                marcar — sim/não.
 */
final class Projetos
{
    public const EXPOSICOES = 'exposicoes';
    public const CINE_CLUBE = 'cine-clube';
    public const CAFE_CULTURAL = 'cafe-cultural';
    public const ARTE_AO_AR_LIVRE = 'arte-ao-ar-livre';
    public const AMOSTRA_DE_TALENTOS = 'amostra-de-talentos';

    private const PROJETOS = [
        self::EXPOSICOES => [
            'nome' => 'Exposições',
            'tipo' => 'Exposição',
            'plural' => 'Exposições',
            'singular' => 'exposição',
            'artigo' => 'a',
            'este' => 'esta',
            'novo' => 'Nova exposição',
            'menu' => 'eventos',
            'lista' => 'exposicoes',
            'rota_nova' => '',
            'pagina' => 'exposicao',
            'tema' => 'galeria',
            'icone' => '▣',
            'momento' => 'Abertura',
            'capa' => 'Imagem de capa',
            'cancelado' => 'Exposição cancelada',
            'sobretitulo' => 'Exposições',
            'frase_lista' => 'Abra uma exposição para ver e organizar tudo o que ela precisa.',
            'frase_nova' => '',
            'proxima' => 'Próxima exposição',
            'anteriores' => 'Exposições encerradas',
            'vazio' => ['Nenhuma exposição', 'Crie uma exposição e diga o que ela precisa.'],
            'passo_ficha' => ['', ''],
            'descricao' => 'Descrição',
            'campo_titulo' => null,
            'linha' => [],
            'destaques' => [],
            'selo' => null,
            'lista_destaque' => null,
            'campos' => [],
            'checklist' => [],
        ],
        self::CINE_CLUBE => [
            'nome' => 'Cine Clube',
            'tipo' => 'Cine Clube',
            'plural' => 'Sessões',
            'singular' => 'sessão',
            'artigo' => 'a',
            'este' => 'esta',
            'novo' => 'Nova sessão',
            'menu' => 'cine-clube',
            'lista' => 'cine-clube',
            'rota_nova' => 'nova-sessao',
            'pagina' => 'sessao',
            'tema' => 'cinema',
            'icone' => '🎬',
            'momento' => 'Sessão',
            'capa' => 'Cartaz',
            'cancelado' => 'Sessão cancelada',
            'sobretitulo' => 'Cine Clube',
            'frase_lista' => 'Os filmes, os debates e o checklist de cada sessão.',
            'frase_nova' => 'O filme, quem conduz a conversa e o dia da sessão.',
            'proxima' => 'Próxima sessão',
            'anteriores' => 'Sessões anteriores',
            'vazio' => ['Nenhuma sessão marcada', 'Crie a primeira sessão: o filme, o dia e quem conduz o debate.'],
            'passo_ficha' => ['O filme', 'O que vai passar e quem conduz a conversa.'],
            'descricao' => 'Sinopse ou observações',
            'campo_titulo' => 'filme',
            'linha' => ['direcao', 'ano', 'duracao'],
            'destaques' => [['debatedor', 'Debate com'], ['mediacao', 'Mediação:']],
            'selo' => 'classificacao',
            'lista_destaque' => null,
            'campos' => [
                ['campo' => 'filme', 'rotulo' => 'Filme', 'tipo' => 'texto', 'obrigatorio' => true, 'exemplo' => 'Ex.: Central do Brasil'],
                ['campo' => 'direcao', 'rotulo' => 'Direção', 'tipo' => 'texto', 'exemplo' => 'Ex.: Walter Salles'],
                ['campo' => 'ano', 'rotulo' => 'Ano', 'tipo' => 'numero', 'min' => 1880, 'max' => 2100],
                ['campo' => 'duracao', 'rotulo' => 'Duração', 'tipo' => 'numero', 'sufixo' => ' min', 'min' => 1, 'max' => 600],
                ['campo' => 'classificacao', 'rotulo' => 'Classificação', 'tipo' => 'opcoes', 'opcoes' => ['L' => 'Livre', '10' => '10 anos', '12' => '12 anos', '14' => '14 anos', '16' => '16 anos', '18' => '18 anos']],
                ['campo' => 'debatedor', 'rotulo' => 'Debatedor(a)', 'tipo' => 'texto', 'exemplo' => 'Quem conversa com o público'],
                ['campo' => 'mediacao', 'rotulo' => 'Mediação', 'tipo' => 'texto'],
                ['campo' => 'legendas', 'rotulo' => 'Sessão com legendas (acessível)', 'tipo' => 'marcar'],
            ],
            'checklist' => [
                ['Documentação', 'Confirmar a licença de exibição do filme', -21, 'ALTA'],
                ['Outro', 'Convidar o debatedor ou a debatedora', -21, 'ALTA'],
                ['Divulgação', 'Divulgar a sessão (cartaz e redes)', -10, 'MEDIA'],
                ['Infraestrutura', 'Reservar o auditório', -14, 'MEDIA'],
                ['Equipamentos', 'Testar projetor, som e a cópia do filme', -2, 'ALTA'],
                ['Alimentação', 'Água para o debate', -1, 'BAIXA'],
                ['Documentação', 'Lista de presença', 0, 'MEDIA'],
                ['Outro', 'Registrar fotos e público', 1, 'BAIXA'],
            ],
        ],
        self::CAFE_CULTURAL => [
            'nome' => 'Café Cultural',
            'tipo' => 'Café Cultural',
            'plural' => 'Encontros',
            'singular' => 'encontro',
            'artigo' => 'o',
            'este' => 'este',
            'novo' => 'Novo encontro',
            'menu' => 'cafe-cultural',
            'lista' => 'cafe-cultural',
            'rota_nova' => 'novo-encontro',
            'pagina' => 'encontro',
            'tema' => 'cafe',
            'icone' => '☕',
            'momento' => 'Encontro',
            'capa' => 'Imagem do encontro',
            'cancelado' => 'Encontro cancelado',
            'sobretitulo' => 'Café Cultural',
            'frase_lista' => 'Conversas com convidados, com café e boa companhia.',
            'frase_nova' => 'O tema, quem vem conversar e o dia do encontro.',
            'proxima' => 'Próximo encontro',
            'anteriores' => 'Encontros anteriores',
            'vazio' => ['Nenhum encontro marcado', 'Crie o primeiro encontro: o tema, o convidado e o dia.'],
            'passo_ficha' => ['A conversa', 'O tema e quem participa.'],
            'descricao' => 'Sobre o encontro',
            'campo_titulo' => 'tema',
            'linha' => ['obra', 'autoria'],
            'destaques' => [['convidado', 'Com'], ['mediacao', 'Mediação:']],
            'selo' => null,
            'lista_destaque' => null,
            'campos' => [
                ['campo' => 'tema', 'rotulo' => 'Tema do encontro', 'tipo' => 'texto', 'obrigatorio' => true, 'exemplo' => 'Ex.: Literatura e memória'],
                ['campo' => 'convidado', 'rotulo' => 'Convidado(a)', 'tipo' => 'texto'],
                ['campo' => 'mediacao', 'rotulo' => 'Mediação', 'tipo' => 'texto'],
                ['campo' => 'obra', 'rotulo' => 'Livro ou obra em conversa', 'tipo' => 'texto'],
                ['campo' => 'autoria', 'rotulo' => 'Autoria', 'tipo' => 'texto'],
            ],
            'checklist' => [
                ['Outro', 'Confirmar o convidado', -21, 'ALTA'],
                ['Divulgação', 'Divulgar o encontro', -10, 'MEDIA'],
                ['Infraestrutura', 'Reservar e arrumar o espaço', -7, 'MEDIA'],
                ['Alimentação', 'Café e lanche', -2, 'MEDIA'],
                ['Equipamentos', 'Microfone e som', -1, 'MEDIA'],
                ['Documentação', 'Lista de presença', 0, 'MEDIA'],
                ['Outro', 'Agradecer o convidado e registrar o encontro', 2, 'BAIXA'],
            ],
        ],
        self::ARTE_AO_AR_LIVRE => [
            'nome' => 'Arte ao Ar Livre',
            'tipo' => 'Arte ao Ar Livre',
            'plural' => 'Intervenções',
            'singular' => 'intervenção',
            'artigo' => 'a',
            'este' => 'esta',
            'novo' => 'Nova intervenção',
            'menu' => 'arte-ao-ar-livre',
            'lista' => 'arte-ao-ar-livre',
            'rota_nova' => 'nova-intervencao',
            'pagina' => 'intervencao',
            'tema' => 'arlivre',
            'icone' => '🌳',
            'momento' => 'Intervenção',
            'capa' => 'Imagem da intervenção',
            'cancelado' => 'Intervenção cancelada',
            'sobretitulo' => 'Arte ao Ar Livre',
            'frase_lista' => 'Arte fora das paredes: no pátio, no jardim, na rua.',
            'frase_nova' => 'O que vai acontecer, quem faz e onde.',
            'proxima' => 'Próxima intervenção',
            'anteriores' => 'Intervenções anteriores',
            'vazio' => ['Nenhuma intervenção marcada', 'Crie a primeira intervenção: o que, quem e onde.'],
            'passo_ficha' => ['A intervenção', 'O que vai acontecer e quem participa.'],
            'descricao' => 'Sobre a intervenção',
            'campo_titulo' => 'nome',
            'linha' => ['linguagem'],
            'destaques' => [['coordenacao', 'Coordenação:']],
            'selo' => null,
            'lista_destaque' => 'artistas',
            'campos' => [
                ['campo' => 'nome', 'rotulo' => 'Nome da intervenção', 'tipo' => 'texto', 'obrigatorio' => true, 'exemplo' => 'Ex.: Mural coletivo no pátio'],
                ['campo' => 'linguagem', 'rotulo' => 'Linguagem', 'tipo' => 'texto', 'exemplo' => 'Ex.: grafite, dança, escultura'],
                ['campo' => 'coordenacao', 'rotulo' => 'Coordenação', 'tipo' => 'texto'],
                ['campo' => 'artistas', 'rotulo' => 'Artistas (um por linha)', 'tipo' => 'linhas'],
                ['campo' => 'plano_chuva', 'rotulo' => 'Se chover', 'tipo' => 'texto', 'exemplo' => 'Ex.: vai para o hall do bloco A'],
            ],
            'checklist' => [
                ['Documentação', 'Autorização para usar o espaço', -21, 'ALTA'],
                ['Equipamentos', 'Materiais da intervenção', -7, 'ALTA'],
                ['Infraestrutura', 'Plano para chuva', -7, 'MEDIA'],
                ['Divulgação', 'Divulgar a intervenção', -7, 'MEDIA'],
                ['Infraestrutura', 'Montar e sinalizar o espaço', 0, 'MEDIA'],
                ['Outro', 'Limpar e devolver o espaço', 1, 'MEDIA'],
            ],
        ],
        self::AMOSTRA_DE_TALENTOS => [
            'nome' => 'Amostra de Talentos',
            'tipo' => 'Amostra de Talentos',
            'plural' => 'Edições',
            'singular' => 'edição',
            'artigo' => 'a',
            'este' => 'esta',
            'novo' => 'Nova edição',
            'menu' => 'amostra-de-talentos',
            'lista' => 'amostra-de-talentos',
            'rota_nova' => 'nova-edicao',
            'pagina' => 'edicao',
            'tema' => 'talentos',
            'icone' => '🎤',
            'momento' => 'Apresentação',
            'capa' => 'Imagem da edição',
            'cancelado' => 'Edição cancelada',
            'sobretitulo' => 'Amostra de Talentos',
            'frase_lista' => 'Música, poesia, dança: o palco aberto da comunidade.',
            'frase_nova' => 'A programação, quem apresenta e o dia.',
            'proxima' => 'Próxima edição',
            'anteriores' => 'Edições anteriores',
            'vazio' => ['Nenhuma edição marcada', 'Crie a primeira edição: a programação e o dia.'],
            'passo_ficha' => ['A edição', 'O nome, a programação e quem apresenta.'],
            'descricao' => 'Sobre a edição',
            'campo_titulo' => 'nome',
            'linha' => ['inscricoes_ate'],
            'destaques' => [['apresentacao', 'Apresentação:']],
            'selo' => null,
            'lista_destaque' => 'programacao',
            'campos' => [
                ['campo' => 'nome', 'rotulo' => 'Nome da edição', 'tipo' => 'texto', 'obrigatorio' => true, 'exemplo' => 'Ex.: Amostra de Talentos 2026'],
                ['campo' => 'apresentacao', 'rotulo' => 'Apresentação', 'tipo' => 'texto', 'exemplo' => 'Quem conduz o palco'],
                ['campo' => 'inscricoes_ate', 'rotulo' => 'Inscrições até', 'tipo' => 'data'],
                ['campo' => 'programacao', 'rotulo' => 'Programação (uma por linha)', 'tipo' => 'linhas'],
            ],
            'checklist' => [
                ['Divulgação', 'Abrir as inscrições', -30, 'ALTA'],
                ['Outro', 'Selecionar as apresentações', -14, 'ALTA'],
                ['Divulgação', 'Divulgar a programação', -7, 'MEDIA'],
                ['Equipamentos', 'Som, microfones e luz', -2, 'ALTA'],
                ['Infraestrutura', 'Ensaio técnico no palco', -1, 'MEDIA'],
                ['Documentação', 'Certificados de participação', 3, 'BAIXA'],
            ],
        ],
    ];

    /** @return list<string> */
    public static function chaves(): array
    {
        return array_keys(self::PROJETOS);
    }

    /**
     * Configuração completa de um projeto (chave desconhecida = Exposições).
     *
     * @return array<string, mixed>
     */
    public static function dados(string $chave): array
    {
        $chave = isset(self::PROJETOS[$chave]) ? $chave : self::EXPOSICOES;

        return ['chave' => $chave] + self::PROJETOS[$chave];
    }

    /**
     * Projeto de um tipo de evento (registro de /api/tipos-evento), pela
     * coluna projeto. Vazia ou desconhecida = Exposições.
     *
     * @param array<string, mixed> $tipo
     */
    public static function doTipo(array $tipo): string
    {
        return self::chaveValida($tipo['projeto'] ?? null);
    }

    /** @param array<string, mixed> $evento Registro de /api/eventos. */
    public static function doEvento(array $evento): string
    {
        return self::chaveValida($evento['tipo_evento_projeto'] ?? null);
    }

    private static function chaveValida(mixed $chave): string
    {
        return is_string($chave) && isset(self::PROJETOS[$chave]) ? $chave : self::EXPOSICOES;
    }

    /**
     * @param list<array<string, mixed>> $eventos
     * @return list<array<string, mixed>>
     */
    public static function filtrar(array $eventos, string $chave): array
    {
        return array_values(array_filter(
            $eventos,
            static fn (array $evento): bool => self::doEvento($evento) === $chave
        ));
    }

    /**
     * Id do tipo de evento do projeto (o de nome igual ao do projeto;
     * senão o primeiro do projeto). null se ainda não existe.
     *
     * @param list<array<string, mixed>> $tipos
     */
    public static function idDoTipo(array $tipos, string $chave): ?int
    {
        $nomeDoTipo = mb_strtolower(self::dados($chave)['tipo']);
        $achado = null;

        foreach ($tipos as $tipo) {
            if (self::doTipo($tipo) !== $chave) {
                continue;
            }

            if (mb_strtolower(trim((string) ($tipo['nome'] ?? ''))) === $nomeDoTipo) {
                return (int) $tipo['id'];
            }

            $achado ??= (int) $tipo['id'];
        }

        return $achado;
    }

    /** Projeto pelo começo do endereço do evento (exposicao, sessao...). */
    public static function daPagina(string $pagina): ?string
    {
        foreach (self::PROJETOS as $chave => $projeto) {
            if ($projeto['pagina'] === $pagina) {
                return $chave;
            }
        }

        return null;
    }

    /** Projeto pela lista (cine-clube, cafe-cultural...). */
    public static function daLista(string $lista): ?string
    {
        foreach (self::PROJETOS as $chave => $projeto) {
            if ($projeto['lista'] === $lista) {
                return $chave;
            }
        }

        return null;
    }

    // -----------------------------------------------------------
    // Ficha
    // -----------------------------------------------------------

    /**
     * Nome a mostrar: o campo principal da ficha (o filme, o tema...),
     * ou o título do evento.
     *
     * @param array<string, mixed>|null $ficha
     */
    public static function tituloDoEvento(array $projeto, ?array $ficha, string $padrao): string
    {
        $campo = $projeto['campo_titulo'] ?? null;
        $valor = $campo !== null ? trim((string) ($ficha[$campo] ?? '')) : '';

        return $valor !== '' ? $valor : $padrao;
    }

    /** "Walter Salles · 1998 · 113 min" */
    public static function linhaDaFicha(array $projeto, array $ficha): string
    {
        $partes = [];

        foreach ($projeto['linha'] ?? [] as $campo) {
            if (isset($ficha[$campo]) && $ficha[$campo] !== '' && $ficha[$campo] !== null) {
                $partes[] = self::rotuloDoValor($projeto, $campo, $ficha[$campo]);
            }
        }

        return implode(' · ', $partes);
    }

    /** Valor de um campo pronto para mostrar (rótulo da opção, sufixo, data). */
    public static function rotuloDoValor(array $projeto, string $campo, mixed $valor): string
    {
        $definicao = self::campo($projeto, $campo);

        if ($definicao === null) {
            return (string) $valor;
        }

        return match ($definicao['tipo']) {
            'opcoes' => (string) ($definicao['opcoes'][(string) $valor] ?? $valor),
            'numero' => $valor . ($definicao['sufixo'] ?? ''),
            'data' => dataBr((string) $valor),
            'marcar' => $valor ? 'Sim' : 'Não',
            default => (string) $valor,
        };
    }

    /** @return list<string> */
    public static function linhas(string $texto): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\r?\n/', $texto) ?: []),
            static fn (string $linha): bool => $linha !== ''
        ));
    }

    /**
     * Valores dos campos como texto, para o formulário.
     *
     * @param array<string, mixed>|null $dados
     * @return array<string, string>
     */
    public static function valoresDaFicha(array $projeto, ?array $dados): array
    {
        $valores = [];

        foreach ($projeto['campos'] as $campo) {
            $valor = $dados[$campo['campo']] ?? null;
            $valores[$campo['campo']] = $campo['tipo'] === 'marcar'
                ? (!empty($valor) ? '1' : '')
                : ($valor === null ? '' : (string) $valor);
        }

        return $valores;
    }

    /**
     * Lê e confere a ficha enviada (campos ficha[...]).
     *
     * @return array{valores: array<string, string>, dados: array<string, mixed>, erro: string}
     */
    public static function lerFicha(array $projeto, array $post): array
    {
        $enviado = is_array($post['ficha'] ?? null) ? $post['ficha'] : [];
        $valores = [];
        $dados = [];
        $erro = '';

        foreach ($projeto['campos'] as $campo) {
            $nome = $campo['campo'];
            $texto = is_string($enviado[$nome] ?? null) ? trim($enviado[$nome]) : '';
            $valores[$nome] = $campo['tipo'] === 'marcar' ? ($texto !== '' ? '1' : '') : $texto;

            if ($campo['tipo'] === 'marcar') {
                $dados[$nome] = $texto !== '';
                continue;
            }

            if ($texto === '') {
                if (!empty($campo['obrigatorio']) && $erro === '') {
                    $erro = 'Preencha: ' . $campo['rotulo'] . '.';
                }
                continue;
            }

            $problema = match ($campo['tipo']) {
                'numero' => !preg_match('/^\d{1,6}$/', $texto)
                    || (isset($campo['min']) && (int) $texto < $campo['min'])
                    || (isset($campo['max']) && (int) $texto > $campo['max']),
                'opcoes' => !array_key_exists($texto, $campo['opcoes']),
                'data' => !dataValida($texto),
                default => false,
            };

            if ($problema) {
                $erro = $erro !== '' ? $erro : 'Confira o campo: ' . $campo['rotulo'] . '.';
                continue;
            }

            $dados[$nome] = match ($campo['tipo']) {
                'numero' => (int) $texto,
                'linhas' => implode("\n", self::linhas($texto)),
                default => mb_substr($texto, 0, $campo['tipo'] === 'linhas' ? 4000 : 200),
            };
        }

        return ['valores' => $valores, 'dados' => $dados, 'erro' => $erro];
    }

    /**
     * Campos da ficha no formulário.
     *
     * @param array<string, string> $valores
     */
    public static function campos(array $projeto, array $valores): void
    {
        foreach ($projeto['campos'] as $campo) {
            $nome = $campo['campo'];
            $id = 'ficha-' . $nome;
            $valor = $valores[$nome] ?? '';
            $largo = in_array($campo['tipo'], ['linhas', 'marcar'], true) || !empty($campo['obrigatorio']);
            ?>
            <div class="campo<?= $largo ? ' campo-largo' : '' ?>">
                <?php if ($campo['tipo'] === 'marcar'): ?>
                    <label class="opcao-marcar">
                        <input type="checkbox" name="ficha[<?= esc($nome) ?>]" value="1" <?= $valor === '1' ? 'checked' : '' ?>>
                        <span><?= esc($campo['rotulo']) ?></span>
                    </label>
                <?php else: ?>
                    <label for="<?= esc($id) ?>">
                        <?= esc($campo['rotulo']) ?>
                        <?php if (empty($campo['obrigatorio'])): ?><span>(opcional)</span><?php endif; ?>
                    </label>
                    <?php if ($campo['tipo'] === 'linhas'): ?>
                        <textarea id="<?= esc($id) ?>" name="ficha[<?= esc($nome) ?>]" rows="4"><?= esc($valor) ?></textarea>
                    <?php elseif ($campo['tipo'] === 'opcoes'): ?>
                        <select id="<?= esc($id) ?>" name="ficha[<?= esc($nome) ?>]">
                            <option value="">Não informada</option>
                            <?php foreach ($campo['opcoes'] as $opcao => $rotulo): ?>
                                <option value="<?= esc($opcao) ?>" <?= (string) $opcao === $valor ? 'selected' : '' ?>><?= esc($rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input
                            id="<?= esc($id) ?>"
                            name="ficha[<?= esc($nome) ?>]"
                            type="<?= match ($campo['tipo']) { 'numero' => 'number', 'data' => 'date', default => 'text' } ?>"
                            value="<?= esc($valor) ?>"
                            <?= isset($campo['min']) ? 'min="' . (int) $campo['min'] . '"' : '' ?>
                            <?= isset($campo['max']) ? 'max="' . (int) $campo['max'] . '"' : '' ?>
                            <?= $campo['tipo'] === 'texto' ? 'maxlength="200"' : '' ?>
                            <?= !empty($campo['exemplo']) ? 'placeholder="' . esc($campo['exemplo']) . '"' : '' ?>
                            <?= !empty($campo['obrigatorio']) ? 'required' : '' ?>
                        >
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    /** Prazo de um item do checklist padrão: o dia do evento ± dias. */
    public static function prazoDoItem(string $dia, int $dias): ?string
    {
        if ($dia === '' || !dataValida($dia)) {
            return null;
        }

        return date('Y-m-d', strtotime($dia . ' ' . ($dias >= 0 ? '+' : '') . $dias . ' days'));
    }

    /** @return array<string, mixed>|null */
    private static function campo(array $projeto, string $nome): ?array
    {
        foreach ($projeto['campos'] ?? [] as $campo) {
            if ($campo['campo'] === $nome) {
                return $campo;
            }
        }

        return null;
    }
}
