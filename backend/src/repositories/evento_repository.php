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
                te.cor AS tipo_evento_cor,
                te.projeto AS tipo_evento_projeto,
                e.responsavel_id,
                r.nome AS responsavel_nome,
                e.local_id,
                l.nome AS local_nome,
                e.titulo,
                e.slug,
                e.descricao,
                ' . self::STATUS_CALCULADO . ' AS status,
                e.observacoes,
                e.imagem_capa,
                e.cor,
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
        $eventos = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Eventos de antes do endereço gravado ganham o seu agora, do mais
        // antigo para o mais novo (o mais antigo fica com o nome puro, como
        // o frontend já fazia: os links que existem continuam iguais).
        $semEndereco = array_filter($eventos, static fn (array $e): bool => $e['slug'] === null);
        uasort($semEndereco, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

        foreach (array_keys($semEndereco) as $i) {
            $eventos[$i]['slug'] = $this->atualizarEndereco((int) $eventos[$i]['id'], (string) $eventos[$i]['titulo']);
        }

        return $eventos;
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
                te.cor AS tipo_evento_cor,
                te.projeto AS tipo_evento_projeto,
                e.responsavel_id,
                r.nome AS responsavel_nome,
                e.local_id,
                l.nome AS local_nome,
                e.titulo,
                e.slug,
                e.descricao,
                ' . self::STATUS_CALCULADO . ' AS status,
                e.observacoes,
                e.imagem_capa,
                e.cor,
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
     * Endereço do evento a partir do nome ("Luz e Memória" →
     * "luz-e-memoria"), com a mesma regra do frontend. Se o nome já é
     * (ou já foi) de outro evento, ganha -2, -3... Os endereços antigos
     * ficam em eventos_enderecos: links antigos continuam valendo.
     */
    public function atualizarEndereco(int $id, string $titulo): string
    {
        $base = self::slugDoTexto($titulo);
        $ocupado = $this->connection->prepare(
            'SELECT 1 FROM eventos WHERE slug = :slug AND id <> :id
             UNION SELECT 1 FROM eventos_enderecos WHERE slug = :slug2 AND evento_id <> :id2
             LIMIT 1'
        );

        $slug = $base;

        for ($n = 2; ; $n++) {
            $ocupado->execute(['slug' => $slug, 'id' => $id, 'slug2' => $slug, 'id2' => $id]);

            if ($ocupado->fetchColumn() === false) {
                break;
            }

            $slug = $base . '-' . $n;
        }

        $this->connection
            ->prepare('UPDATE eventos SET slug = :slug WHERE id = :id')
            ->execute(['slug' => $slug, 'id' => $id]);
        $this->connection
            ->prepare('INSERT IGNORE INTO eventos_enderecos (slug, evento_id) VALUES (:slug, :id)')
            ->execute(['slug' => $slug, 'id' => $id]);

        return $slug;
    }

    /** Id do evento dono de um endereço (atual ou antigo), ou null. */
    public function idDoEndereco(string $slug): ?int
    {
        $statement = $this->connection->prepare(
            'SELECT id FROM eventos WHERE slug = :slug
             UNION SELECT evento_id FROM eventos_enderecos WHERE slug = :slug2
             LIMIT 1'
        );
        $statement->execute(['slug' => $slug, 'slug2' => $slug]);
        $id = $statement->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** "Luz e Memória: fotografia" → "luz-e-memoria-fotografia" (igual ao frontend). */
    public static function slugDoTexto(string $texto): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($texto))) ?: '';
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');
        $slug = rtrim(substr($slug, 0, 80), '-');

        return $slug !== '' ? $slug : 'exposicao';
    }

    /**
     * Troca a identidade visual (capa e cor). null limpa o campo.
     *
     * @return array<string, mixed>|null o evento atualizado, ou null se não existe
     */
    public function updateIdentidade(int $id, ?string $imagemCapa, ?string $cor): ?array
    {
        $statement = $this->connection->prepare(
            'UPDATE eventos SET imagem_capa = :imagem_capa, cor = :cor WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'imagem_capa' => $imagemCapa,
            'cor' => $cor,
        ]);

        return $this->findById($id);
    }

    /**
     * Apaga a exposição e tudo o que é dela (datas, checklist, cursos,
     * anexos e as tabelas antigas), numa transação: ou sai tudo, ou nada.
     * O histórico fica, sem a ligação com a exposição (ON DELETE SET NULL).
     *
     * @return list<string>|null caminhos dos anexos (para apagar os
     *                           arquivos depois), ou null se não existe
     */
    public function delete(int $id): ?array
    {
        $this->connection->beginTransaction();

        try {
            $existe = $this->connection->prepare('SELECT id FROM eventos WHERE id = :id FOR UPDATE');
            $existe->execute(['id' => $id]);

            if ($existe->fetchColumn() === false) {
                $this->connection->rollBack();

                return null;
            }

            $anexos = $this->connection->prepare('SELECT caminho FROM anexos WHERE evento_id = :id');
            $anexos->execute(['id' => $id]);
            $caminhos = array_map('strval', $anexos->fetchAll(PDO::FETCH_COLUMN));

            foreach (['tarefas', 'agenda_eventos', 'evento_cursos', 'anexos', 'eventos'] as $tabela) {
                $coluna = $tabela === 'eventos' ? 'id' : 'evento_id';
                $this->connection
                    ->prepare("DELETE FROM {$tabela} WHERE {$coluna} = :id")
                    ->execute(['id' => $id]);
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }

        return $caminhos;
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
                status,
                observacoes
             ) VALUES (
                :tipo_evento_id,
                :responsavel_id,
                :local_id,
                :titulo,
                :descricao,
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
            'status' => $status,
            'observacoes' => $observacoes,
        ]);

        $novoId = (int) $this->connection->lastInsertId();
        $this->atualizarEndereco($novoId, $titulo);

        return $this->findById(
            $novoId
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
            'status' => $status,
            'observacoes' => $observacoes,
        ]);

        if (
            $statement->rowCount() === 0
            && $this->findById($id) === null
        ) {
            return null;
        }

        $this->atualizarEndereco($id, $titulo);

        return $this->findById($id);
    }
}