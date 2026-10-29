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
        ];
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
