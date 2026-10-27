<?php

declare(strict_types=1);

/*
 * Entrega a imagem de capa de uma exposição (capa.php?f=<arquivo>).
 *
 * As capas ficam em storage/capas/, fora das pastas públicas. Só quem
 * está logado recebe a imagem. O nome é aleatório e nunca muda (uma capa
 * nova ganha outro nome), então o navegador pode guardar por bastante tempo.
 */

use Elos\Frontend\Identidade;

use function Elos\Frontend\iniciarSessao;

require_once __DIR__ . '/src/Api.php';
require_once __DIR__ . '/src/Identidade.php';

iniciarSessao();
$logado = !empty($_SESSION['usuario']['id']);
session_write_close();

$nome = (string) ($_GET['f'] ?? '');
$arquivo = Identidade::pasta() . '/' . $nome;

if (!$logado || !Identidade::nomeValido($nome) || !is_file($arquivo)) {
    http_response_code(404);
    exit();
}

header('Content-Type: ' . (str_ends_with($nome, '.webp') ? 'image/webp' : 'image/jpeg'));
header('Content-Length: ' . filesize($arquivo));
header('Cache-Control: private, max-age=31536000, immutable');
readfile($arquivo);
