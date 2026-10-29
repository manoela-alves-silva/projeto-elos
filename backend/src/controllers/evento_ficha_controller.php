<?php

declare(strict_types=1);

namespace Elos\Controllers;

use Elos\Repositories\EventoFichaRepository;

/**
 * Ficha dos eventos de projeto. A API só garante o formato (campos
 * simples, nomes e tamanhos limitados); quais campos cada projeto tem
 * e o que vale em cada um é conferido pelo frontend (Projetos.php).
 */
final class EventoFichaController
{
    private const MAXIMO_CAMPOS = 30;
    private const MAXIMO_TEXTO = 2000;

    public function __construct(
        private readonly EventoFichaRepository $repository
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(int $eventoId): ?array
    {
        return $this->repository->findByEventoId($eventoId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function index(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Confere o formato e grava. Devolve [ficha, erro].
     *
     * @return array{0: ?array<string, mixed>, 1: string}
     */
    public function save(int $eventoId, mixed $dados): array
    {
        if (!is_array($dados) || ($dados !== [] && array_is_list($dados))) {
            return [null, 'Ficha inválida.'];
        }

        if (count($dados) > self::MAXIMO_CAMPOS) {
            return [null, 'Campos demais na ficha.'];
        }

        $limpos = [];

        foreach ($dados as $campo => $valor) {
            if (!is_string($campo) || preg_match('/^[a-z][a-z0-9_]{0,39}$/', $campo) !== 1) {
                return [null, 'Nome de campo inválido na ficha.'];
            }

            if (is_string($valor)) {
                $valor = trim($valor);

                if (mb_strlen($valor) > self::MAXIMO_TEXTO) {
                    return [null, 'Texto longo demais na ficha.'];
                }

                $valor = $valor === '' ? null : $valor;
            } elseif (!is_int($valor) && !is_bool($valor) && $valor !== null) {
                return [null, 'Valor inválido na ficha.'];
            }

            $limpos[$campo] = $valor;
        }

        $ficha = $this->repository->save($eventoId, $limpos);

        return [$ficha, $ficha === null ? 'Não foi possível salvar a ficha.' : ''];
    }
}
