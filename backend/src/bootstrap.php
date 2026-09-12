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