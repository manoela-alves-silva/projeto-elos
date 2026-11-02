<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\PainelRepository;

/**
 * Painel ao vivo: o que a produção precisa fazer, agora.
 *
 * Separa os itens abertos do checklist em três colunas:
 *   - atrasados: o prazo já passou (de qualquer prioridade);
 *   - prioridade: marcados como prioridade alta, com prazo nos
 *     próximos 30 dias;
 *   - pendentes: prazo hoje ou nos próximos dias.
 * Cada item aparece numa coluna só, nessa ordem de importância.
 * Os de prioridade alta sem prazo não entram nas colunas (encheriam a
 * tela): vão em "sem_prazo", e o painel lembra deles de tempos em tempos.
 * Também manda o que foi concluído hoje (para o aviso "Concluído").
 */
final class PainelController
{
    // Até quantos dias à frente entram os pendentes.
    public const DIAS_A_FRENTE = 7;

    // Prioridade alta: até quantos dias à frente (os sem prazo entram sempre).
    public const DIAS_A_FRENTE_PRIORIDADE = 30;

    // "Em foco": eventos que abrem até quantos dias à frente, quantos
    // entram no rodízio e quantos próximos passos de cada um.
    public const DIAS_A_FRENTE_FOCO = 60;
    public const EVENTOS_EM_FOCO = 6;
    public const PASSOS_POR_EVENTO = 4;

    public function __construct(
        private readonly PainelRepository $repository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(string $hoje): array
    {
        $ate = date('Y-m-d', strtotime($hoje . ' +' . self::DIAS_A_FRENTE . ' days'));

        $atePrioridade = date('Y-m-d', strtotime($hoje . ' +' . self::DIAS_A_FRENTE_PRIORIDADE . ' days'));

        $colunas = ['atrasados' => [], 'prioridade' => [], 'pendentes' => []];
        $semPrazo = [];

        foreach ($this->repository->tarefasAbertasAte($ate, $atePrioridade) as $tarefa) {
            $prazo = $tarefa['prazo'] !== null ? (string) $tarefa['prazo'] : null;

            $coluna = match (true) {
                $prazo === null => 'sem_prazo',
                $prazo < $hoje => 'atrasados',
                $tarefa['prioridade'] === 'ALTA' => 'prioridade',
                default => 'pendentes',
            };

            $item = [
                'id' => (int) $tarefa['id'],
                'titulo' => (string) $tarefa['titulo'],
                'prazo' => $prazo,
                'horario' => $tarefa['horario'] !== null ? substr((string) $tarefa['horario'], 0, 5) : null,
                'prioridade' => (string) $tarefa['prioridade'],
                'status' => (string) $tarefa['status'],
                'responsavel' => $tarefa['responsavel'],
                'evento' => $this->evento($tarefa),
            ];

            if ($coluna === 'sem_prazo') {
                $semPrazo[] = $item;
            } else {
                $colunas[$coluna][] = $item;
            }
        }

        // Nos atrasados, prioridade alta vem primeiro; depois o mais antigo.
        usort($colunas['atrasados'], static fn (array $a, array $b): int =>
            [$a['prioridade'] !== 'ALTA', $a['prazo'], $a['id']] <=> [$b['prioridade'] !== 'ALTA', $b['prazo'], $b['id']]);

        $concluidas = array_map(fn (array $tarefa): array => [
            'id' => (int) $tarefa['id'],
            'titulo' => (string) $tarefa['titulo'],
            'responsavel' => $tarefa['responsavel'],
            'evento' => $this->evento($tarefa),
        ], $this->repository->tarefasConcluidasNosUltimos(time() - (int) strtotime($hoje . ' 00:00:00')));

        return [
            'hoje' => $hoje,
            'gerado_em' => date('c'),
            'colunas' => $colunas,
            'sem_prazo' => $semPrazo,
            'concluidas_hoje' => $concluidas,
            'em_foco' => $this->emFoco($hoje),
        ];
    }

    /**
     * Bloco "Em foco": um evento por vez, com o andamento do checklist
     * e os próximos passos.
     *
     * @return list<array<string, mixed>>
     */
    private function emFoco(string $hoje): array
    {
        $ate = date('Y-m-d', strtotime($hoje . ' +' . self::DIAS_A_FRENTE_FOCO . ' days'));
        $eventos = $this->repository->eventosEmFoco($hoje, $ate, self::EVENTOS_EM_FOCO);

        $passos = [];
        $ids = array_map(static fn (array $e): int => (int) $e['id'], $eventos);

        foreach ($this->repository->proximasTarefasDosEventos($ids) as $tarefa) {
            $id = (int) $tarefa['evento_id'];

            if (count($passos[$id] ?? []) >= self::PASSOS_POR_EVENTO) {
                continue;
            }

            $passos[$id][] = [
                'id' => (int) $tarefa['id'],
                'titulo' => (string) $tarefa['titulo'],
                'prazo' => $tarefa['prazo'] !== null ? (string) $tarefa['prazo'] : null,
                'horario' => $tarefa['horario'] !== null ? substr((string) $tarefa['horario'], 0, 5) : null,
                'prioridade' => (string) $tarefa['prioridade'],
                'responsavel' => $tarefa['responsavel'],
            ];
        }

        return array_map(static fn (array $e): array => [
            'id' => (int) $e['id'],
            'titulo' => (string) $e['titulo'],
            'cor' => $e['cor'] ?? null,
            'imagem_capa' => $e['imagem_capa'] ?? null,
            'tipo' => $e['tipo'] ?? null,
            'tipo_cor' => $e['tipo_cor'] ?? null,
            'data' => $e['abertura'] ?? null,
            'horario' => $e['horario'] !== null ? substr((string) $e['horario'], 0, 5) : null,
            'ate' => $e['permanencia_fim'] ?? null,
            'local' => $e['local'] ?? null,
            'total' => (int) $e['total'],
            'feitas' => (int) $e['feitas'],
            'passos' => $passos[(int) $e['id']] ?? [],
        ], $eventos);
    }

    /**
     * @param array<string, mixed> $linha
     * @return array{id: int, titulo: string, cor: ?string, tipo: ?string, tipo_cor: ?string, data: ?string}
     */
    private function evento(array $linha): array
    {
        return [
            'id' => (int) $linha['evento_id'],
            'titulo' => (string) $linha['evento_titulo'],
            'cor' => $linha['evento_cor'] ?? null,
            'tipo' => $linha['evento_tipo'] ?? null,
            'tipo_cor' => $linha['evento_tipo_cor'] ?? null,
            'data' => $linha['evento_data'] ?? null,
        ];
    }

    public function codigoValido(string $codigo): bool
    {
        // O código é gerado com 32 bytes aleatórios (43 caracteres).
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $codigo) !== 1) {
            return false;
        }

        return $this->repository->codigoExiste(hash('sha256', $codigo));
    }

    /**
     * Gera um link novo para o monitor. O anterior deixa de valer.
     * O código só existe nesta resposta: o banco guarda o hash.
     */
    public function novoCodigo(?int $usuarioId): string
    {
        $codigo = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->repository->trocarCodigo(hash('sha256', $codigo), $usuarioId);

        return $codigo;
    }

    public function desativarCodigo(): void
    {
        $this->repository->desativarCodigo();
    }

    public function dataDoCodigo(): ?string
    {
        return $this->repository->dataDoCodigo();
    }
}
