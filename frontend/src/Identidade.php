<?php

declare(strict_types=1);

namespace Elos\Frontend;

require_once __DIR__ . '/elos.php';

/**
 * Identidade visual de cada exposição: imagem de capa e cor.
 *
 * A imagem fica numa pasta do projeto fora das pastas públicas
 * (storage/capas/) e é entregue por capa.php, só para quem está logado.
 * No banco fica apenas o nome do arquivo (coluna eventos.imagem_capa).
 *
 * Sem cor escolhida, a exposição ganha uma cor fixa calculada a partir
 * do nome: a mesma exposição tem sempre a mesma cor.
 */
final class Identidade
{
    public const TAMANHO_MAXIMO = 8 * 1024 * 1024;
    private const LARGURA_MAXIMA = 1600;

    /** Cores automáticas (escolhidas para texto branco e fundo claro legíveis). */
    private const CORES_AUTOMATICAS = [
        '#b5462b', // terracota
        '#2b6cb0', // azul
        '#6b46c1', // violeta
        '#0f766e', // verde-água
        '#a16207', // ocre
        '#be185d', // magenta
        '#3f6212', // oliva
        '#1e3a8a', // azul-marinho
    ];

    public static function pasta(): string
    {
        $base = getenv('ELOS_CAPAS_DIR');

        return is_string($base) && trim($base) !== ''
            ? rtrim(trim($base), '/')
            : dirname(__DIR__, 2) . '/storage/capas';
    }

    public static function nomeValido(?string $nome): bool
    {
        return is_string($nome) && preg_match('/^[a-f0-9]{32}\.(jpg|webp)$/', $nome) === 1;
    }

    /** Cor da exposição: a escolhida, ou a automática pelo nome. */
    public static function cor(array $evento): string
    {
        $cor = (string) ($evento['cor'] ?? '');

        if (preg_match('/^#[0-9a-f]{6}$/i', $cor) === 1) {
            return strtolower($cor);
        }

        $titulo = mb_strtolower(trim((string) ($evento['titulo'] ?? '')));

        return self::CORES_AUTOMATICAS[crc32($titulo) % count(self::CORES_AUTOMATICAS)];
    }

    /**
     * Endereço da capa a partir da raiz do site, ou null se não tem.
     * Precisa ser absoluto: dentro do CSS, um endereço relativo seria
     * lido a partir da pasta do arquivo .css, e não da página.
     */
    public static function urlCapa(array $evento): ?string
    {
        $nome = $evento['imagem_capa'] ?? null;

        return self::nomeValido($nome) ? raizDoSite() . '/capa?f=' . $nome : null;
    }

    /** Variáveis CSS para style="": --expo-cor e, se houver, --expo-capa. */
    public static function estilo(array $evento): string
    {
        $estilo = '--expo-cor: ' . self::cor($evento) . ';';
        $url = self::urlCapa($evento);

        if ($url !== null) {
            $estilo .= " --expo-capa: url('" . $url . "');";
        }

        return $estilo;
    }

    /**
     * Confere e guarda a imagem enviada. Reduz para no máximo 1600 px de
     * largura e grava como JPEG (tira dados escondidos do arquivo).
     *
     * @param array<string, mixed> $arquivo item de $_FILES
     * @return array{nome: ?string, cor: ?string, erro: string}
     */
    public static function salvarEnvio(array $arquivo): array
    {
        $falha = static fn(string $erro): array => ['nome' => null, 'cor' => null, 'erro' => $erro];
        $codigo = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return $falha('A imagem é grande demais. Envie uma de até 8 MB.');
        }

        if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($arquivo['tmp_name'] ?? ''))) {
            return $falha('Não foi possível receber a imagem. Tente de novo.');
        }

        $caminho = (string) $arquivo['tmp_name'];

        if (filesize($caminho) > self::TAMANHO_MAXIMO) {
            return $falha('A imagem é grande demais. Envie uma de até 8 MB.');
        }

        // O tipo vem do conteúdo, não do nome do arquivo.
        $tipo = (new \finfo(FILEINFO_MIME_TYPE))->file($caminho);

        if (!in_array($tipo, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return $falha('Envie uma imagem JPG, PNG ou WebP.');
        }

        $pasta = self::pasta();

        if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
            return $falha('Não foi possível guardar a imagem no servidor.');
        }

        $nome = bin2hex(random_bytes(16)) . '.jpg';
        $destino = $pasta . '/' . $nome;

        if (!function_exists('imagecreatefromstring')) {
            // Servidor sem GD: guarda como veio, se já for JPEG.
            if ($tipo !== 'image/jpeg' || !move_uploaded_file($caminho, $destino)) {
                return $falha('O servidor não consegue processar essa imagem. Envie um JPG.');
            }

            return ['nome' => $nome, 'cor' => null, 'erro' => ''];
        }

        $imagem = @imagecreatefromstring((string) file_get_contents($caminho));

        if ($imagem === false) {
            return $falha('Essa imagem parece estar corrompida.');
        }

        $largura = imagesx($imagem);

        if ($largura > self::LARGURA_MAXIMA) {
            $reduzida = imagescale($imagem, self::LARGURA_MAXIMA);
            if ($reduzida !== false) {
                $imagem = $reduzida;
            }
        }

        $cor = self::corSugerida($imagem);
        $ok = imagejpeg($imagem, $destino, 82);

        if (!$ok) {
            return $falha('Não foi possível guardar a imagem no servidor.');
        }

        return ['nome' => $nome, 'cor' => $cor, 'erro' => ''];
    }

    public static function apagarArquivo(?string $nome): void
    {
        if (self::nomeValido($nome) && is_file(self::pasta() . '/' . $nome)) {
            @unlink(self::pasta() . '/' . $nome);
        }
    }

    /**
     * Cor marcante da imagem: entre os tons mais saturados de uma versão
     * pequena, escurecida até o texto branco ficar legível em cima.
     */
    private static function corSugerida(\GdImage $imagem): string
    {
        $mini = imagescale($imagem, 24, 14);

        if ($mini === false) {
            return self::CORES_AUTOMATICAS[0];
        }

        $melhor = null;
        $melhorPeso = -1.0;

        for ($x = 0; $x < imagesx($mini); $x++) {
            for ($y = 0; $y < imagesy($mini); $y++) {
                $rgb = imagecolorat($mini, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $saturacao = $max === 0 ? 0 : ($max - $min) / $max;
                // Prefere tons vivos e de brilho médio.
                $peso = $saturacao * (1 - abs($max / 255 - 0.65));

                if ($peso > $melhorPeso) {
                    $melhorPeso = $peso;
                    $melhor = [$r, $g, $b];
                }
            }
        }

        [$r, $g, $b] = $melhor ?? [181, 70, 43];

        // Escurece até ter contraste de pelo menos 4.5:1 com o branco.
        for ($i = 0; $i < 20 && self::contrasteComBranco($r, $g, $b) < 4.5; $i++) {
            $r = (int) ($r * 0.9);
            $g = (int) ($g * 0.9);
            $b = (int) ($b * 0.9);
        }

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private static function contrasteComBranco(int $r, int $g, int $b): float
    {
        $canal = static function (int $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $luminancia = 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b);

        return 1.05 / ($luminancia + 0.05);
    }
}
