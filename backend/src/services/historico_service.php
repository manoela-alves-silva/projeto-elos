<?php

declare(strict_types=1);

namespace Elos\Services;

use Elos\Repositories\HistoricoRepository;
use Throwable;

/**
 * Registra no histórico da exposição quem fez o quê, a partir das
 * próprias rotas da API (ninguém precisa lembrar de anotar).
 *
 * As rotas são funções soltas; por isso a instância da requisição fica
 * guardada aqui (ativar) e as rotas chamam HistoricoService::registrar.
 * Uma falha ao registrar nunca desfaz a ação que já deu certo.
 */
final class HistoricoService
{
    private static ?self $ativo = null;

    public function __construct(
        private readonly HistoricoRepository $repository,
        private readonly SessionService $sessionService
    ) {
    }

    public static function ativar(self $servico): void
    {
        self::$ativo = $servico;
    }

    public static function registrar(int $eventoId, string $acao, ?string $descricao = null): void
    {
        if ($eventoId < 1) {
            return;
        }

        self::gravar($eventoId, $acao, $descricao);
    }

    /**
     * Registro que não pertence a nenhuma exposição (por exemplo, a
     * exposição que foi apagada): fica no histórico geral.
     */
    public static function registrarGeral(string $acao, ?string $descricao = null): void
    {
        self::gravar(null, $acao, $descricao);
    }

    private static function gravar(?int $eventoId, string $acao, ?string $descricao): void
    {
        if (self::$ativo === null) {
            return;
        }

        try {
            $usuario = self::$ativo->sessionService->user();
            $usuarioId = isset($usuario['id']) ? (int) $usuario['id'] : null;
            $descricao = $descricao !== null ? trim($descricao) : null;

            self::$ativo->repository->create(
                $eventoId,
                $usuarioId,
                mb_substr($acao, 0, 150),
                $descricao !== '' ? $descricao : null
            );
        } catch (Throwable $e) {
            error_log('ELOS: não foi possível registrar o histórico: ' . $e->getMessage());
        }
    }
}
