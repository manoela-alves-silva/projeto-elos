<?php

declare(strict_types=1);

namespace Elos\Services;

/**
 * Freia quem tenta adivinhar senhas: conta os logins errados por e-mail
 * e por endereço de quem acessa, e bloqueia por um tempo quando passam
 * do limite. Guarda as tentativas em arquivos (sem tabela no banco).
 */
final class LimiteLoginService
{
    private const JANELA_SEGUNDOS = 15 * 60;
    private const MAXIMO_POR_EMAIL = 5;
    private const MAXIMO_POR_IP = 20;

    public function __construct(private readonly string $diretorio)
    {
    }

    /**
     * Segundos que faltam para liberar este e-mail/endereço (0 = liberado).
     */
    public function segundosBloqueado(string $email, string $ip): int
    {
        return max(
            $this->restante($this->chaveEmail($email), self::MAXIMO_POR_EMAIL),
            $this->restante($this->chaveIp($ip), self::MAXIMO_POR_IP)
        );
    }

    public function registrarFalha(string $email, string $ip): void
    {
        $this->acrescentar($this->chaveEmail($email));
        $this->acrescentar($this->chaveIp($ip));
    }

    /**
     * Login certo zera as falhas do e-mail (as do endereço continuam).
     */
    public function limpar(string $email): void
    {
        $arquivo = $this->arquivo($this->chaveEmail($email));

        if (is_file($arquivo)) {
            unlink($arquivo);
        }
    }

    private function restante(string $chave, int $maximo): int
    {
        $tentativas = $this->ler($chave);

        if (count($tentativas) < $maximo) {
            return 0;
        }

        // Libera quando a tentativa que atingiu o limite sair da janela.
        $marco = $tentativas[count($tentativas) - $maximo];

        return max(0, $marco + self::JANELA_SEGUNDOS - time());
    }

    /**
     * @return list<int> horários (timestamp) das falhas dentro da janela
     */
    private function ler(string $chave): array
    {
        $arquivo = $this->arquivo($chave);

        if (!is_file($arquivo)) {
            return [];
        }

        $dados = json_decode((string) file_get_contents($arquivo), true);

        if (!is_array($dados)) {
            return [];
        }

        $limite = time() - self::JANELA_SEGUNDOS;

        return array_values(array_filter(
            array_map('intval', $dados),
            static fn (int $momento): bool => $momento > $limite
        ));
    }

    private function acrescentar(string $chave): void
    {
        if (!is_dir($this->diretorio)) {
            mkdir($this->diretorio, 0775, true);
        }

        $tentativas = $this->ler($chave);
        $tentativas[] = time();

        file_put_contents(
            $this->arquivo($chave),
            json_encode($tentativas),
            LOCK_EX
        );
    }

    private function chaveEmail(string $email): string
    {
        return 'email:' . mb_strtolower(trim($email));
    }

    private function chaveIp(string $ip): string
    {
        return 'ip:' . $ip;
    }

    private function arquivo(string $chave): string
    {
        return $this->diretorio . '/' . hash('sha256', $chave) . '.json';
    }
}
