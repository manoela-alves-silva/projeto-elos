<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

/**
 * Consultas do painel ao vivo (monitor da produção).
 *
 * Só leitura dos eventos e tarefas: o painel mostra o que precisa ser
 * feito e nunca altera nada.
 */
final class PainelRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Itens do checklist ainda abertos com prazo até $ate (inclui os
     * atrasados) e os de prioridade alta com prazo até $atePrioridade
     * ou sem prazo (menos os marcados "não precisa de prazo"), de eventos que não terminaram nem foram cancelados.
     *
     * @return list<array<string, mixed>>
     */
    public function tarefasAbertasAte(string $ate, string $atePrioridade): array
    {
        $sql = '
            SELECT
                t.id,
                t.evento_id,
                t.titulo,
                t.prazo,
                t.horario,
                t.prioridade,
                t.status,
                COALESCE(u.nome, t.responsavel_nome) AS responsavel,
                c.nome AS categoria,
                e.titulo AS evento_titulo,
                e.cor AS evento_cor,
                te.nome AS evento_tipo,
                te.cor AS evento_tipo_cor,
                ae.abertura AS evento_data
            FROM tarefas t
            INNER JOIN eventos e
                ON e.id = t.evento_id
            LEFT JOIN tipos_evento te
                ON te.id = e.tipo_evento_id
            LEFT JOIN agenda_eventos ae
                ON ae.evento_id = e.id
            LEFT JOIN usuarios u
                ON u.id = t.usuario_responsavel_id
            LEFT JOIN categorias_tarefa c
                ON c.id = t.categoria_id
            WHERE t.status NOT IN (\'CONCLUIDA\', \'CANCELADA\')
                AND e.status NOT IN (\'CONCLUIDO\', \'CANCELADO\')
                AND (
                    (t.prazo IS NOT NULL AND t.prazo <= :ate)
                    OR (
                        t.prioridade = \'ALTA\'
                        AND (
                            (t.prazo IS NULL AND t.prazo_dispensado = 0)
                            OR t.prazo <= :ate_prioridade
                        )
                    )
                )
            ORDER BY
                t.prazo IS NULL,
                t.prazo ASC,
                t.horario IS NULL,
                t.horario ASC,
                FIELD(t.prioridade, \'ALTA\', \'MEDIA\', \'BAIXA\'),
                t.id ASC
            LIMIT 300
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['ate' => $ate, 'ate_prioridade' => $atePrioridade]);

        return $statement->fetchAll();
    }

    /**
     * Itens concluídos nos últimos $segundos (os mais recentes
     * primeiro), de eventos não cancelados: o painel mostra o que foi
     * feito. Conta em segundos a partir do relógio do próprio banco,
     * que pode estar em outro fuso horário.
     *
     * @return list<array<string, mixed>>
     */
    public function tarefasConcluidasNosUltimos(int $segundos): array
    {
        $sql = '
            SELECT
                t.id,
                t.evento_id,
                t.titulo,
                TIMESTAMPDIFF(SECOND, t.updated_at, NOW()) AS ha_segundos,
                COALESCE(u.nome, t.responsavel_nome) AS responsavel,
                e.titulo AS evento_titulo,
                e.cor AS evento_cor,
                te.nome AS evento_tipo,
                te.cor AS evento_tipo_cor,
                ae.abertura AS evento_data
            FROM tarefas t
            INNER JOIN eventos e
                ON e.id = t.evento_id
            LEFT JOIN tipos_evento te
                ON te.id = e.tipo_evento_id
            LEFT JOIN agenda_eventos ae
                ON ae.evento_id = e.id
            LEFT JOIN usuarios u
                ON u.id = t.usuario_responsavel_id
            WHERE t.status = \'CONCLUIDA\'
                AND e.status <> \'CANCELADO\'
                AND t.updated_at >= NOW() - INTERVAL :segundos SECOND
            ORDER BY t.updated_at DESC, t.id DESC
            LIMIT 50
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue('segundos', max(0, $segundos), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Exposições e projetos para o bloco "Em foco": os que ainda não
     * terminaram, com abertura até $ate (ou em cartaz hoje), na ordem
     * da abertura. Traz quantos itens do checklist existem e quantos
     * já foram concluídos, para a barra de andamento.
     *
     * @return list<array<string, mixed>>
     */
    public function eventosEmFoco(string $hoje, string $ate, int $limite): array
    {
        $sql = '
            SELECT
                e.id,
                e.titulo,
                e.cor,
                e.imagem_capa,
                te.nome AS tipo,
                te.cor AS tipo_cor,
                ae.abertura,
                ae.horario,
                ae.permanencia_fim,
                l.nome AS local,
                (SELECT COUNT(*) FROM tarefas t
                    WHERE t.evento_id = e.id AND t.status <> \'CANCELADA\') AS total,
                (SELECT COUNT(*) FROM tarefas t
                    WHERE t.evento_id = e.id AND t.status = \'CONCLUIDA\') AS feitas
            FROM eventos e
            INNER JOIN agenda_eventos ae
                ON ae.evento_id = e.id
            LEFT JOIN tipos_evento te
                ON te.id = e.tipo_evento_id
            LEFT JOIN locais l
                ON l.id = e.local_id
            WHERE e.status NOT IN (\'CONCLUIDO\', \'CANCELADO\')
                AND ae.abertura IS NOT NULL
                AND ae.abertura <= :ate
                AND COALESCE(ae.permanencia_fim, ae.abertura) >= :hoje
            ORDER BY ae.abertura ASC, e.id ASC
            LIMIT ' . max(1, $limite);

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['hoje' => $hoje, 'ate' => $ate]);

        return $statement->fetchAll();
    }

    /**
     * Próximos itens abertos do checklist dos eventos pedidos: primeiro
     * os com prazo (o mais cedo antes), depois os sem prazo.
     *
     * @param list<int> $eventoIds
     * @return list<array<string, mixed>>
     */
    public function proximasTarefasDosEventos(array $eventoIds): array
    {
        if ($eventoIds === []) {
            return [];
        }

        $marcas = implode(', ', array_fill(0, count($eventoIds), '?'));
        $sql = '
            SELECT
                t.id,
                t.evento_id,
                t.titulo,
                t.prazo,
                t.horario,
                t.prioridade,
                COALESCE(u.nome, t.responsavel_nome) AS responsavel
            FROM tarefas t
            LEFT JOIN usuarios u
                ON u.id = t.usuario_responsavel_id
            WHERE t.evento_id IN (' . $marcas . ')
                AND t.status NOT IN (\'CONCLUIDA\', \'CANCELADA\')
            ORDER BY
                t.prazo IS NULL,
                t.prazo ASC,
                FIELD(t.prioridade, \'ALTA\', \'MEDIA\', \'BAIXA\'),
                t.id ASC
            LIMIT 300
        ';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_values(array_map('intval', $eventoIds)));

        return $statement->fetchAll();
    }

    public function codigoExiste(string $codigoHash): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM painel_acesso WHERE codigo_hash = :hash LIMIT 1');
        $statement->execute(['hash' => $codigoHash]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Troca o link do monitor: apaga os anteriores e guarda o novo.
     */
    public function trocarCodigo(string $codigoHash, ?int $usuarioId): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec('DELETE FROM painel_acesso');

            $statement = $this->pdo->prepare(
                'INSERT INTO painel_acesso (codigo_hash, criado_por) VALUES (:hash, :usuario)'
            );
            $statement->execute(['hash' => $codigoHash, 'usuario' => $usuarioId]);

            $this->pdo->commit();
        } catch (\Throwable $erro) {
            $this->pdo->rollBack();
            throw $erro;
        }
    }

    public function desativarCodigo(): void
    {
        $this->pdo->exec('DELETE FROM painel_acesso');
    }

    /**
     * Data em que o link atual foi criado (null quando não há link).
     */
    public function dataDoCodigo(): ?string
    {
        $valor = $this->pdo->query('SELECT MAX(created_at) FROM painel_acesso')->fetchColumn();

        return is_string($valor) ? $valor : null;
    }
}
