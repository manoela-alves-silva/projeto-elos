<?php

declare(strict_types=1);

namespace Elos\Frontend;

require_once __DIR__ . '/elos.php';
require_once __DIR__ . '/Fases.php';

/**
 * Tudo o que uma exposição tem, carregado da API e organizado.
 *
 * É o "caderno" da exposição: agenda (marcos) e necessidades (checklist
 * — tabela tarefas). Visitas, transportes e documentos também são itens
 * do checklist, com a categoria dizendo o tipo.
 * A partir disso monta a lista única de itens com data, que alimenta
 * a própria exposição, o Início e o Calendário — a professora cadastra
 * uma vez e o resto acompanha.
 */
final class Planejamento
{
    public const CAMPOS_AGENDA = [
        'montagem_inicio' => ['Início da montagem', 'montagem'],
        'montagem_fim' => ['Fim da montagem', 'montagem'],
        'abertura' => ['Abertura', 'abertura'],
        'permanencia_inicio' => ['Início do período', 'permanencia'],
        'permanencia_fim' => ['Fim do período', 'permanencia'],
        'desmontagem_inicio' => ['Início da desmontagem', 'desmontagem'],
        'desmontagem_fim' => ['Fim da desmontagem', 'desmontagem'],
    ];

    /**
     * @param array<string, mixed> $evento
     * @param array<string, mixed>|null $agenda
     * @param list<array<string, mixed>>|null $necessidades
     */
    private function __construct(
        public readonly array $evento,
        public readonly ?array $agenda,
        public readonly bool $agendaIndisponivel,
        public readonly ?array $necessidades
    ) {
    }

    /**
     * @param array<string, mixed> $evento  Registro de /api/eventos ou /api/eventos/{id}.
     */
    public static function carregar(array $evento): self
    {
        $id = (int) ($evento['id'] ?? 0);

        // Agenda é 1 por evento; 404 = ainda sem agenda (não é falha).
        $agenda = null;
        $agendaIndisponivel = false;

        try {
            $dados = Api::get('/api/eventos/' . $id . '/agenda');
            $agenda = is_array($dados['agenda'] ?? null) ? $dados['agenda'] : null;
        } catch (ApiException $e) {
            $agendaIndisponivel = $e->statusCode !== 404;
        }

        return new self(
            $evento,
            $agenda,
            $agendaIndisponivel,
            self::ordenarNecessidades(apiLista('/api/eventos/' . $id . '/tarefas', 'tarefas'))
        );
    }

    /**
     * Carrega várias exposições com um pedido só à API (em vez de dois
     * por exposição). Se a API ainda não tiver a rota de lote, carrega
     * uma por uma, como antes.
     *
     * @param list<array<string, mixed>> $eventos
     * @return list<self> na mesma ordem de $eventos
     */
    public static function carregarVarios(array $eventos): array
    {
        $eventos = array_values($eventos);

        if ($eventos === []) {
            return [];
        }

        $ids = array_map(static fn(array $e): int => (int) ($e['id'] ?? 0), $eventos);

        try {
            $dados = Api::get('/api/eventos/planejamento?ids=' . implode(',', $ids));
        } catch (ApiException) {
            return array_map(static fn(array $e): self => self::carregar($e), $eventos);
        }

        $porEvento = [];
        foreach ($dados['planejamento'] ?? [] as $item) {
            if (is_array($item)) {
                $porEvento[(int) ($item['evento_id'] ?? 0)] = $item;
            }
        }

        return array_map(static function (array $evento) use ($porEvento): self {
            $item = $porEvento[(int) ($evento['id'] ?? 0)] ?? null;

            if ($item === null) {
                return self::carregar($evento);
            }

            $tarefas = is_array($item['tarefas'] ?? null) ? array_values(array_filter($item['tarefas'], 'is_array')) : [];

            return new self(
                $evento,
                is_array($item['agenda'] ?? null) ? $item['agenda'] : null,
                false,
                self::ordenarNecessidades($tarefas)
            );
        }, $eventos);
    }

    public function id(): int
    {
        return (int) ($this->evento['id'] ?? 0);
    }

    public function titulo(): string
    {
        return (string) ($this->evento['titulo'] ?? ('Exposição #' . $this->id()));
    }

    /** @return array{inicio: ?string, fim: ?string} */
    public function periodo(): array
    {
        return periodoDaExposicao($this->agenda);
    }

    // -----------------------------------------------------------
    // Checklist
    // -----------------------------------------------------------

    /** pendente | atrasado | concluido | cancelado */
    public static function estadoNecessidade(array $tarefa): string
    {
        return match ((string) ($tarefa['status'] ?? '')) {
            'CONCLUIDA' => 'concluido',
            'CANCELADA' => 'cancelado',
            default => !empty($tarefa['prazo']) && (string) $tarefa['prazo'] < hoje() ? 'atrasado' : 'pendente',
        };
    }

    /**
     * @return array{total: int, pendentes: int, atrasadas: int, concluidas: int, percentual: int}
     */
    public function resumo(): array
    {
        $resumo = ['total' => 0, 'pendentes' => 0, 'atrasadas' => 0, 'concluidas' => 0, 'percentual' => 0];

        foreach ($this->necessidades ?? [] as $tarefa) {
            $estado = self::estadoNecessidade($tarefa);

            if ($estado === 'cancelado') {
                continue;
            }

            $resumo['total']++;

            if ($estado === 'concluido') {
                $resumo['concluidas']++;
            } else {
                $resumo['pendentes']++;

                if ($estado === 'atrasado') {
                    $resumo['atrasadas']++;
                }
            }
        }

        if ($resumo['total'] > 0) {
            $resumo['percentual'] = (int) round($resumo['concluidas'] * 100 / $resumo['total']);
        }

        return $resumo;
    }

    /**
     * Necessidades abertas que merecem atenção: atrasadas, ou de
     * prioridade ALTA com prazo nos próximos 3 dias (regra que o
     * Início já usava — mantida, sem inventar outra).
     *
     * @return list<array<string, mixed>>
     */
    public function alertas(): array
    {
        $alertas = [];

        foreach ($this->necessidades ?? [] as $tarefa) {
            $estado = self::estadoNecessidade($tarefa);

            if ($estado === 'atrasado') {
                $alertas[] = $tarefa + ['_motivo' => 'Atrasada'];
            } elseif (
                $estado === 'pendente'
                && ($tarefa['prioridade'] ?? '') === 'ALTA'
                && !empty($tarefa['prazo'])
                && diasAte((string) $tarefa['prazo']) <= 3
            ) {
                $alertas[] = $tarefa + ['_motivo' => 'Prioridade alta · ' . distanciaRelativa((string) $tarefa['prazo'])];
            }
        }

        return $alertas;
    }

    // -----------------------------------------------------------
    // Tudo que tem data, numa lista só
    // -----------------------------------------------------------

    /**
     * Itens com data da exposição, em ordem cronológica.
     *
     * Cada item: data (Y-m-d), horario (?H:i), origem (marco |
     * necessidade), titulo,
     * categoria, estado (marco | pendente | atrasado | agendado |
     * concluido | cancelado), rotuloEstado, grupo (cor do marco),
     * link (para a exposição, na seção certa).
     *
     * @return list<array<string, mixed>>
     */
    public function itensDatados(bool $incluirCancelados = false): array
    {
        $itens = [];
        $base = linkExposicao($this->id());

        foreach (self::CAMPOS_AGENDA as $campo => [$rotulo, $grupo]) {
            if (!empty($this->agenda[$campo])) {
                $itens[] = $this->item(
                    (string) $this->agenda[$campo],
                    $campo === 'abertura' ? ($this->agenda['horario'] ?? null) : null,
                    'marco',
                    $rotulo,
                    'Agenda',
                    'marco',
                    '',
                    $base . '#datas',
                    $grupo
                );
            }
        }

        foreach ($this->necessidades ?? [] as $tarefa) {
            if (empty($tarefa['prazo'])) {
                continue;
            }

            $estado = self::estadoNecessidade($tarefa);
            $itens[] = $this->item(
                (string) $tarefa['prazo'],
                $tarefa['horario'] ?? null,
                'necessidade',
                (string) ($tarefa['titulo'] ?? 'Necessidade'),
                (string) ($tarefa['categoria_nome'] ?? 'Necessidade'),
                $estado,
                match ($estado) {
                    'concluido' => 'Concluído',
                    'cancelado' => 'Cancelado',
                    'atrasado' => 'Atrasado',
                    default => 'Pendente',
                },
                $base . '#item-' . (int) ($tarefa['id'] ?? 0)
            );
        }

        if (!$incluirCancelados) {
            $itens = array_values(array_filter($itens, static fn(array $i): bool => $i['estado'] !== 'cancelado'));
        }

        usort(
            $itens,
            static fn(array $a, array $b): int =>
                [$a['data'], $a['horario'] ?? '99:99', $a['origem'] === 'marco' ? 0 : 1]
                <=> [$b['data'], $b['horario'] ?? '99:99', $b['origem'] === 'marco' ? 0 : 1]
        );

        return $itens;
    }

    /**
     * Próximos itens a partir de hoje, mais tudo que ficou para trás
     * e continua aberto (atrasado) — o que precisa de atenção agora.
     *
     * @return list<array<string, mixed>>
     */
    public function proximasAtividades(int $limite = 8): array
    {
        $hoje = hoje();

        $itens = array_values(array_filter(
            $this->itensDatados(),
            static fn(array $i): bool => $i['data'] >= $hoje || $i['estado'] === 'atrasado'
        ));

        return array_slice($itens, 0, $limite);
    }

    /** @return list<array<string, mixed>> */
    private static function ordenarNecessidades(?array $necessidades): ?array
    {
        if ($necessidades === null) {
            return null;
        }

        usort(
            $necessidades,
            static fn(array $a, array $b): int =>
                [in_array($a['status'] ?? '', ['CONCLUIDA', 'CANCELADA'], true), $a['prazo'] ?? '9999-12-31', $a['horario'] ?? '99:99', (int) ($a['id'] ?? 0)]
                <=> [in_array($b['status'] ?? '', ['CONCLUIDA', 'CANCELADA'], true), $b['prazo'] ?? '9999-12-31', $b['horario'] ?? '99:99', (int) ($b['id'] ?? 0)]
        );

        return $necessidades;
    }

    /** @return array<string, mixed> */
    private function item(
        string $data,
        ?string $horario,
        string $origem,
        string $titulo,
        string $categoria,
        string $estado,
        string $rotuloEstado,
        string $link,
        string $grupo = ''
    ): array {
        return [
            'data' => substr($data, 0, 10),
            'horario' => $horario !== null && $horario !== '' ? substr($horario, 0, 5) : null,
            'origem' => $origem,
            'titulo' => $titulo,
            'categoria' => $categoria,
            'estado' => $estado,
            'rotuloEstado' => $rotuloEstado,
            'grupo' => $grupo !== '' ? $grupo : $origem,
            'link' => $link,
            'evento_id' => $this->id(),
            'evento_titulo' => $this->titulo(),
        ];
    }
}
