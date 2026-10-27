<?php

declare(strict_types=1);

namespace Elos\Frontend;

/**
 * Fases do trabalho de uma exposição, montadas a partir da categoria
 * de cada item do checklist.
 *
 * A equipe já organiza o trabalho pelas categorias (Curadoria,
 * Montagem, Divulgação…). Aqui cada categoria ganha uma fase, que dá
 * a ordem em que aparecem e a cor. Nada muda no banco: a ligação é
 * feita pelo nome da categoria. Nomes parecidos ("Galeria Virtual" e
 * "Galeria Virtual e Instagram") caem na mesma fase.
 *
 * Categoria que não se encaixa em nenhuma fase vira um grupo próprio,
 * no fim, com o próprio nome.
 */
final class Fases
{
    /**
     * Cores por etapa do trabalho. Fases da mesma etapa dividem a cor.
     * [cor da marca (bolinha, barra, traço), cor clara (fundo)]
     */
    public const CORES = [
        // Paleta conferida para daltonismo (todos os pares). A cor marca
        // bolinhas, barras e fundos; o texto fica sempre na cor de texto.
        'planejamento' => ['#4a89f2', '#e4eefd'],
        'producao' => ['#5b3eae', '#ebe6f6'],
        'divulgacao' => ['#d85891', '#fae6ef'],
        'montagem' => ['#cb9317', '#faf0d9'],
        'encerramento' => ['#23690d', '#e0ede0'],
        'outros' => ['#7a868c', '#eceff0'],
    ];

    public const ROTULOS_ETAPA = [
        'planejamento' => 'Planejamento',
        'producao' => 'Produção',
        'divulgacao' => 'Divulgação',
        'montagem' => 'Montagem e abertura',
        'encerramento' => 'Encerramento',
        'outros' => 'Outros',
    ];

    /**
     * Na ordem do trabalho (é a ordem em que aparecem na tela).
     *
     * chave => [rótulo, etapa, termos que reconhecem a categoria]
     */
    private const FASES = [
        'planejamento' => ['Planejamento e cronograma', 'planejamento', ['planejamento', 'cronograma']],
        'conceito' => ['Conceito curatorial', 'planejamento', ['conceito', 'tema central']],
        'curadoria' => ['Curadoria', 'planejamento', ['curadoria', 'curatorial']],
        'vistoria' => ['Vistoria do espaço', 'planejamento', ['vistoria']],
        'setores' => ['Sinalização aos setores', 'producao', ['sinaliza', 'setores']],
        'identidade' => ['Identidade visual', 'producao', ['identidade visual']],
        'documentacao' => ['Documentação', 'producao', ['documenta']],
        'transporte' => ['Transporte e laudo', 'producao', ['transporte', 'laudo']],
        'infraestrutura' => ['Infraestrutura', 'producao', ['infraestrutura', 'equipamento']],
        'divulgacao' => ['Divulgação', 'divulgacao', ['divulga']],
        'galeria-virtual' => ['Galeria Virtual e Instagram', 'divulgacao', ['galeria virtual', 'instagram', 'redes sociais']],
        'montagem' => ['Montagem', 'montagem', ['montagem']],
        'inauguracao' => ['Inauguração (vernissage)', 'montagem', ['inaugura', 'vernis', 'abertura']],
        'visita' => ['Visitas', 'montagem', ['visita']],
        'alimentacao' => ['Alimentação', 'montagem', ['alimenta']],
        'desmontagem' => ['Desmontagem e devolução', 'encerramento', ['desmontagem', 'devolu']],
        'relatorio' => ['Elaboração do relatório', 'encerramento', ['relat']],
        'agradecimentos' => ['Agradecimentos', 'encerramento', ['agradec']],
    ];

    /**
     * Fases conferidas antes das outras, porque o nome delas contém o
     * termo de outra fase: "Desmontagem" contém "montagem" e "Vistoria
     * do espaço expositivo para montagem" também.
     */
    private const CONFERIR_PRIMEIRO = ['desmontagem', 'vistoria'];

    /**
     * Fase de um nome de categoria.
     *
     * @return array{chave: string, rotulo: string, etapa: string, ordem: int}
     */
    public static function daCategoria(?string $categoria): array
    {
        $nome = trim((string) $categoria);
        $busca = mb_strtolower($nome);
        $ordens = array_flip(array_keys(self::FASES));
        $sequencia = array_unique([...self::CONFERIR_PRIMEIRO, ...array_keys(self::FASES)]);

        foreach ($sequencia as $chave) {
            [$rotulo, $etapa, $termos] = self::FASES[$chave];
            foreach ($termos as $termo) {
                if ($busca !== '' && str_contains($busca, $termo)) {
                    return ['chave' => $chave, 'rotulo' => $rotulo, 'etapa' => $etapa, 'ordem' => $ordens[$chave]];
                }
            }
        }

        // Fora das fases conhecidas: grupo com o próprio nome, no fim.
        if ($nome === '' || $busca === 'outro') {
            return ['chave' => 'outro', 'rotulo' => 'Outros', 'etapa' => 'outros', 'ordem' => 1000];
        }

        return ['chave' => 'cat-' . self::slug($nome), 'rotulo' => $nome, 'etapa' => 'outros', 'ordem' => 500];
    }

    /**
     * Itens do checklist agrupados por fase, na ordem do trabalho.
     * Dentro de cada fase, pendentes primeiro (pela data), resolvidos no fim.
     *
     * @param list<array<string, mixed>> $tarefas
     * @return list<array{chave: string, rotulo: string, etapa: string, itens: list<array<string, mixed>>, total: int, feitas: int, pendentes: int, atrasadas: int}>
     */
    public static function agrupar(array $tarefas): array
    {
        $grupos = [];

        foreach ($tarefas as $tarefa) {
            $fase = self::daCategoria($tarefa['categoria_nome'] ?? null);
            $grupos[$fase['chave']] ??= $fase + ['itens' => [], 'total' => 0, 'feitas' => 0, 'pendentes' => 0, 'atrasadas' => 0];
            $grupo = &$grupos[$fase['chave']];
            $grupo['itens'][] = $tarefa;

            $estado = Planejamento::estadoNecessidade($tarefa);
            if ($estado !== 'cancelado') {
                $grupo['total']++;
                if ($estado === 'concluido') {
                    $grupo['feitas']++;
                } else {
                    $grupo['pendentes']++;
                    if ($estado === 'atrasado') {
                        $grupo['atrasadas']++;
                    }
                }
            }
            unset($grupo);
        }

        uasort($grupos, static fn(array $a, array $b): int => [$a['ordem'], $a['rotulo']] <=> [$b['ordem'], $b['rotulo']]);

        return array_map(static function (array $grupo): array {
            unset($grupo['ordem']);

            return $grupo;
        }, array_values($grupos));
    }

    /** Estilo inline com as cores da etapa, para usar em style="". */
    public static function estiloCor(string $etapa): string
    {
        [$forte, $clara] = self::CORES[$etapa] ?? self::CORES['outros'];

        return '--fase-cor: ' . $forte . '; --fase-fundo: ' . $clara . ';';
    }

    private static function slug(string $texto): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($texto)) ?: $texto;

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-') ?: 'categoria';
    }
}
