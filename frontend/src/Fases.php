<?php

declare(strict_types=1);

namespace Elos\Frontend;

/**
 * O checklist dividido em fases, a partir da categoria de cada item.
 *
 * Cada categoria vira uma fase (Documentação, Transporte...) e cada
 * fase pertence a uma etapa do trabalho — Preparação, Produção ou
 * Realização — que dá a cor. Categorias novas, criadas na hora,
 * entram em "Outros cuidados".
 */
final class Fases
{
    /** Etapas, na ordem do trabalho. */
    public const ROTULOS_ETAPA = [
        'preparacao' => 'Preparação',
        'producao' => 'Produção',
        'realizacao' => 'Realização',
        'geral' => 'Outros cuidados',
    ];

    private const CORES_ETAPA = [
        'preparacao' => '#5a9fbe',
        'producao' => '#d9a521',
        'realizacao' => '#35a7a7',
        'geral' => '#a89677',
    ];

    /** Categoria (em minúsculas) => [chave, rótulo, etapa], na ordem em que aparecem. */
    private const CATEGORIAS = [
        'documentação' => ['documentacao', 'Documentação', 'preparacao'],
        'divulgação' => ['divulgacao', 'Divulgação', 'preparacao'],
        'transporte' => ['transporte', 'Transporte', 'producao'],
        'infraestrutura' => ['infraestrutura', 'Infraestrutura', 'producao'],
        'equipamentos' => ['equipamentos', 'Equipamentos', 'producao'],
        'alimentação' => ['alimentacao', 'Alimentação', 'realizacao'],
        'visita' => ['visitas', 'Visitas', 'realizacao'],
        'outro' => ['outros', 'Outros', 'geral'],
    ];

    /**
     * Fase de uma categoria.
     *
     * @return array{chave: string, rotulo: string, etapa: string}
     */
    public static function daCategoria(?string $categoria): array
    {
        $nome = trim((string) $categoria);
        $chave = mb_strtolower($nome);

        if (isset(self::CATEGORIAS[$chave])) {
            [$fase, $rotulo, $etapa] = self::CATEGORIAS[$chave];

            return ['chave' => $fase, 'rotulo' => $rotulo, 'etapa' => $etapa];
        }

        if ($nome === '') {
            return ['chave' => 'sem-categoria', 'rotulo' => 'Sem categoria', 'etapa' => 'geral'];
        }

        return ['chave' => self::slug($nome), 'rotulo' => $nome, 'etapa' => 'geral'];
    }

    /**
     * Itens agrupados por fase, na ordem das etapas. Em cada fase:
     * total (sem os cancelados), feitas, pendentes e atrasadas.
     *
     * @param list<array<string, mixed>> $necessidades
     * @return list<array{chave: string, rotulo: string, etapa: string, itens: list<array<string, mixed>>, total: int, feitas: int, pendentes: int, atrasadas: int}>
     */
    public static function agrupar(array $necessidades): array
    {
        $fases = [];

        foreach ($necessidades as $tarefa) {
            $fase = self::daCategoria($tarefa['categoria_nome'] ?? null);
            $chave = $fase['chave'];

            $fases[$chave] ??= $fase + ['itens' => [], 'total' => 0, 'feitas' => 0, 'pendentes' => 0, 'atrasadas' => 0];
            $fases[$chave]['itens'][] = $tarefa;

            $estado = Planejamento::estadoNecessidade($tarefa);

            if ($estado === 'cancelado') {
                continue;
            }

            $fases[$chave]['total']++;

            if ($estado === 'concluido') {
                $fases[$chave]['feitas']++;
            } else {
                $fases[$chave]['pendentes']++;
                $fases[$chave]['atrasadas'] += $estado === 'atrasado' ? 1 : 0;
            }
        }

        $ordemEtapa = array_flip(array_keys(self::ROTULOS_ETAPA));
        $ordemFase = array_flip(array_column(array_values(self::CATEGORIAS), 0));

        usort($fases, static fn (array $a, array $b): int =>
            [$ordemEtapa[$a['etapa']] ?? 99, $ordemFase[$a['chave']] ?? 50, $a['rotulo']]
            <=> [$ordemEtapa[$b['etapa']] ?? 99, $ordemFase[$b['chave']] ?? 50, $b['rotulo']]);

        return array_values($fases);
    }

    /** style="" com a cor da etapa (--fase-cor). */
    public static function estiloCor(string $etapa): string
    {
        return '--fase-cor: ' . (self::CORES_ETAPA[$etapa] ?? self::CORES_ETAPA['geral']) . ';';
    }

    private static function slug(string $texto): string
    {
        return slugDeTexto($texto) ?: 'fase';
    }
}
