<?php

declare(strict_types=1);

namespace Elos\Repositories;

use PDO;

final class EventoRepository
{
    // Período em que a exposição ocupa o local: do primeiro dia marcado
    // (normalmente a montagem) ao último (normalmente a desmontagem).
    private const OCUPACAO_INICIO = 'COALESCE(a.montagem_inicio, a.montagem_fim, a.abertura,
        a.permanencia_inicio, a.permanencia_fim, a.desmontagem_inicio, a.desmontagem_fim)';
    private const OCUPACAO_FIM = 'COALESCE(a.desmontagem_fim, a.desmontagem_inicio, a.permanencia_fim,
        a.permanencia_inicio, a.abertura, a.montagem_fim, a.montagem_inicio)';

    // O status vem das datas; só "cancelado" é escolhido por alguém.
    // Sem datas, a exposição continua em planejamento.
    private const STATUS_CALCULADO = "CASE
            WHEN e.status = 'CANCELADO' THEN 'CANCELADO'
            WHEN " . self::OCUPACAO_FIM . " IS NULL THEN 'PLANEJAMENTO'
            WHEN " . self::OCUPACAO_FIM . " < CURDATE() THEN 'CONCLUIDO'
            WHEN " . self::OCUPACAO_INICIO . " <= CURDATE() THEN 'EM_ANDAMENTO'
            ELSE 'PLANEJAMENTO'
        END";

    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Lista todos os eventos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $statement = $this->connection->prepare(
            'SELECT
                e.id,
                e.tipo_evento_id,
                te.nome AS tipo_evento_nome,
                e.responsavel_id,
                r.nome AS responsavel_nome,
                e.local_id,
                l.nome AS local_nome,
                e.titulo,
                e.descricao,
                e.prioridade,
                ' . self::STATUS_CALCULADO . ' AS status,
                e.observacoes,
                e.created_at,
                e.updated_at
             FROM eventos e
             INNER JOIN tipos_evento te
                ON te.id = e.tipo_evento_id
             INNER JOIN responsaveis r
                ON r.id = e.responsavel_id
             INNER JOIN locais l
                ON l.id = e.local_id
             LEFT JOIN agenda_eventos a
                ON a.evento_id = e.id
             ORDER BY e.created_at DESC'
        );

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um evento pelo ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT
                e.id,
                e.tipo_evento_id,
                te.nome AS tipo_evento_nome,
                e.responsavel_id,
                r.nome AS responsavel_nome,
                e.local_id,
                l.nome AS local_nome,
                e.titulo,
                e.descricao,
                e.prioridade,
                ' . self::STATUS_CALCULADO . ' AS status,
                e.observacoes,
                e.created_at,
                e.updated_at
             FROM eventos e
             INNER JOIN tipos_evento te
                ON te.id = e.tipo_evento_id
             INNER JOIN responsaveis r
                ON r.id = e.responsavel_id
             INNER JOIN locais l
                ON l.id = e.local_id
             LEFT JOIN agenda_eventos a
                ON a.evento_id = e.id
             WHERE e.id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $id,
        ]);

        $evento = $statement->fetch(PDO::FETCH_ASSOC);

        return $evento !== false ? $evento : null;
    }

    /**
     * Exposições não canceladas no mesmo local cujo período de ocupação
     * cruza o período informado (datas no formato AAAA-MM-DD).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findConflitos(int $localId, string $inicio, string $fim, ?int $excetoId): array
    {
        $statement = $this->connection->prepare(
            'SELECT
                e.id,
                e.titulo,
                ' . self::OCUPACAO_INICIO . ' AS ocupacao_inicio,
                ' . self::OCUPACAO_FIM . ' AS ocupacao_fim
             FROM eventos e
             INNER JOIN agenda_eventos a
                ON a.evento_id = e.id
             WHERE e.local_id = :local_id
               AND e.status <> \'CANCELADO\'
               AND e.id <> :exceto_id
               AND ' . self::OCUPACAO_INICIO . ' <= :fim
               AND ' . self::OCUPACAO_FIM . ' >= :inicio
             ORDER BY ocupacao_inicio ASC'
        );

        $statement->execute([
            'local_id' => $localId,
            'exceto_id' => $excetoId ?? 0,
            'inicio' => $inicio,
            'fim' => $fim,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um novo evento.
     *
     * @return array<string, mixed>|null
     */
    public function create(
        int $tipoEventoId,
        int $responsavelId,
        int $localId,
        string $titulo,
        ?string $descricao,
        string $prioridade,
        string $status,
        ?string $observacoes
    ): ?array {
        $statement = $this->connection->prepare(
            'INSERT INTO eventos (
                tipo_evento_id,
                responsavel_id,
                local_id,
                titulo,
                descricao,
                prioridade,
                status,
                observacoes
             ) VALUES (
                :tipo_evento_id,
                :responsavel_id,
                :local_id,
                :titulo,
                :descricao,
                :prioridade,
                :status,
                :observacoes
             )'
        );

        $statement->execute([
            'tipo_evento_id' => $tipoEventoId,
            'responsavel_id' => $responsavelId,
            'local_id' => $localId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'prioridade' => $prioridade,
            'status' => $status,
            'observacoes' => $observacoes,
        ]);

        return $this->findById(
            (int) $this->connection->lastInsertId()
        );
    }

    /**
     * Atualiza um evento.
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        int $tipoEventoId,
        int $responsavelId,
        int $localId,
        string $titulo,
        ?string $descricao,
        string $prioridade,
        string $status,
        ?string $observacoes
    ): ?array {
        $statement = $this->connection->prepare(
            'UPDATE eventos
             SET tipo_evento_id = :tipo_evento_id,
                 responsavel_id = :responsavel_id,
                 local_id = :local_id,
                 titulo = :titulo,
                 descricao = :descricao,
                 prioridade = :prioridade,
                 status = :status,
                 observacoes = :observacoes
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'tipo_evento_id' => $tipoEventoId,
            'responsavel_id' => $responsavelId,
            'local_id' => $localId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'prioridade' => $prioridade,
            'status' => $status,
            'observacoes' => $observacoes,
        ]);

        if (
            $statement->rowCount() === 0
            && $this->findById($id) === null
        ) {
            return null;
        }

        return $this->findById($id);
    }
}