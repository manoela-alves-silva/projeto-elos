<?php

declare(strict_types=1);

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'Elos\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $parts = explode('\\', $relativeClass);

        if (count($parts) < 2) {
            return;
        }

        $directoryMap = [
            'Config' => 'config',
            'Controllers' => 'controllers',
            'Models' => 'models',
            'Repositories' => 'repositories',
            'Services' => 'services',
        ];

        $namespaceDirectory = array_shift($parts);

        if (!isset($directoryMap[$namespaceDirectory])) {
            return;
        }

        $className = array_pop($parts);

        $snakeCaseClassName = strtolower(
            preg_replace(
                '/(?<!^)[A-Z]/',
                '_$0',
                $className
            )
        );

        $file = dirname(__DIR__)
            . '/src/'
            . $directoryMap[$namespaceDirectory]
            . '/';

        if ($parts !== []) {
            $file .= implode('/', $parts) . '/';
        }

        $file .= $snakeCaseClassName . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
);

/**
 * Lê as variáveis de um arquivo .env (CHAVE=valor, # comentário) e as
 * coloca no ambiente, sem passar por cima das que já existem. Assim
 * funciona em qualquer hospedagem: o arquivo fica fora das pastas
 * públicas e nunca vai para o git.
 */
function carregarArquivoEnv(string $arquivo): void
{
    if (!is_file($arquivo) || !is_readable($arquivo)) {
        return;
    }

    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linha) {
        $linha = trim($linha);

        if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
            continue;
        }

        [$nome, $valor] = array_map('trim', explode('=', $linha, 2));

        if (strlen($valor) >= 2 && in_array($valor[0], ['"', "'"], true) && $valor[-1] === $valor[0]) {
            $valor = substr($valor, 1, -1);
        }

        if ($nome !== '' && getenv($nome) === false) {
            putenv($nome . '=' . $valor);
        }
    }
}

carregarArquivoEnv(dirname(__DIR__) . '/.env');
