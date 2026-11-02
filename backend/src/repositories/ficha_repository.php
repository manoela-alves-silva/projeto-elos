<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

/**
 * Ficha dos eventos de projeto (Cine Clube, Café Cultural...): os campos
 * próprios de cada projeto (o filme, o convidado, a programação...).
 * Os campos são definidos no frontend; aqui fica só o JSON.
 */
final class FichaRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @return list<array{evento_id: int, dados: array<string, mixed>}>
     */
    public function todas(): array
    {
        $linhas = $this->pdo->query('SELECT evento_id, dados FROM eventos_fichas')->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $linha): array => self::formatar($linha), $linhas);
    }

    /**
     * @return array{evento_id: int, dados: array<string, mixed>}|null
     */
    public function doEvento(int $eventoId): ?array
    {
        $statement = $this->pdo->prepare('SELECT evento_id, dados FROM eventos_fichas WHERE evento_id = :id LIMIT 1');
        $statement->execute(['id' => $eventoId]);
        $linha = $statement->fetch(PDO::FETCH_ASSOC);

        return $linha !== false ? self::formatar($linha) : null;
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function salvar(int $eventoId, array $dados): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO eventos_fichas (evento_id, dados) VALUES (:id, :dados)
                 ON DUPLICATE KEY UPDATE dados = VALUES(dados)'
            )
            ->execute([
                'id' => $eventoId,
                'dados' => json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
    }

    /**
     * @param array<string, mixed> $linha
     * @return array{evento_id: int, dados: array<string, mixed>}
     */
    private static function formatar(array $linha): array
    {
        $dados = json_decode((string) $linha['dados'], true);

        return ['evento_id' => (int) $linha['evento_id'], 'dados' => is_array($dados) ? $dados : []];
    }
}
