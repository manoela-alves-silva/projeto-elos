<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

/**
 * Ficha de um evento de projeto (Cine Clube, Café Cultural...): os
 * campos próprios do projeto, guardados em JSON, um por evento.
 */
final class EventoFichaRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @return array<string, mixed>|null ['evento_id', 'dados' (array), 'updated_at']
     */
    public function findByEventoId(int $eventoId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT evento_id, dados, updated_at FROM evento_ficha WHERE evento_id = :evento_id LIMIT 1'
        );
        $statement->execute(['evento_id' => $eventoId]);

        $linha = $statement->fetch();

        return $linha !== false ? $this->decodificar($linha) : null;
    }

    /**
     * Fichas de todos os eventos (para as listas dos projetos).
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $linhas = $this->pdo->query(
            'SELECT evento_id, dados, updated_at FROM evento_ficha ORDER BY evento_id'
        )->fetchAll();

        return array_map(fn (array $linha): array => $this->decodificar($linha), $linhas);
    }

    /**
     * Grava a ficha (cria na primeira vez, depois substitui).
     *
     * @param array<string, scalar|null> $dados
     * @return array<string, mixed>|null
     */
    public function save(int $eventoId, array $dados): ?array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO evento_ficha (evento_id, dados) VALUES (:evento_id, :dados)
             ON DUPLICATE KEY UPDATE dados = VALUES(dados)'
        );

        $statement->execute([
            'evento_id' => $eventoId,
            'dados' => json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);

        return $this->findByEventoId($eventoId);
    }

    /**
     * @param array<string, mixed> $linha
     * @return array<string, mixed>
     */
    private function decodificar(array $linha): array
    {
        $dados = json_decode((string) $linha['dados'], true);

        return [
            'evento_id' => (int) $linha['evento_id'],
            'dados' => is_array($dados) ? $dados : [],
            'updated_at' => $linha['updated_at'],
        ];
    }
}
