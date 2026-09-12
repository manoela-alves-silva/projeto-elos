<?php

declare(strict_types=1);

namespace Elos\Config;

use PDO;
use RuntimeException;

/**
 * Fornece uma conexão PDO configurada para o novo backend do ELOS.
 * As variáveis DB_* devem ser definidas pelo ambiente de execução.
 */
final class Database
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
        private readonly string $charset = 'utf8mb4'
    ) {
        if ($this->host === '' || $this->database === '' || $this->username === '') {
            throw new RuntimeException('As configurações de banco obrigatórias não foram informadas.');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new RuntimeException('A porta do banco de dados é inválida.');
        }

        if ($this->charset !== 'utf8mb4') {
            throw new RuntimeException('O charset do banco deve ser utf8mb4.');
        }
    }

    /**
     * Cria a configuração a partir das variáveis de ambiente DB_*.
     * Este método não lê arquivos .env e não estabelece conexão por si só.
     */
    public static function fromEnvironment(): self
    {
        return new self(
            self::requiredEnvironment('DB_HOST'),
            self::environmentPort(),
            self::requiredEnvironment('DB_NAME'),
            self::requiredEnvironment('DB_USER'),
            self::requiredEnvironment('DB_PASSWORD'),
            self::environmentValue('DB_CHARSET', 'utf8mb4')
        );
    }

    /**
     * Retorna a conexão única desta instância, criada somente quando necessária.
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->host,
                $this->port,
                $this->database,
                $this->charset
            );

            $this->connection = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return $this->connection;
    }

    private static function requiredEnvironment(string $name): string
    {
        $value = getenv($name);

        if ($value === false || trim($value) === '') {
            throw new RuntimeException(sprintf('A variável de ambiente %s é obrigatória.', $name));
        }

        return trim($value);
    }

    private static function environmentValue(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || trim($value) === '' ? $default : trim($value);
    }

    private static function environmentPort(): int
    {
        $port = self::environmentValue('DB_PORT', '3306');

        if (filter_var($port, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException('A variável de ambiente DB_PORT deve ser um número inteiro.');
        }

        return (int) $port;
    }
}
