# Colocando no ar

Como publicar o ELOS: o que vale para qualquer hospedagem e três jeitos de hospedar.

[← Voltar para o README](../README.md)

## Vale para qualquer hospedagem

- PHP 8.1+ (recomendado 8.5), com `pdo_mysql`, `fileinfo`, `gd` e **`allow_url_fopen` ligado**
  (o frontend usa isso para chamar a API).
- **HTTPS é obrigatório.** Sem ele, as senhas viajam pela internet sem
  proteção. O ELOS reconhece HTTPS também atrás de proxy
  (`X-Forwarded-Proto`).
- Banco criado com `backend/database/elos.sql`.
- `ELOS_DEBUG` vazio. Assim os erros vão para o log, e a API responde só
  "Erro interno do servidor".
- As pastas `backend/storage/` e `storage/` (sessões e capas) precisam ter permissão de escrita para o PHP.
- O servidor precisa aceitar `.htaccess` com `mod_rewrite` (endereços limpos). No Nginx, o modelo já faz isso.
- Faça **backup** diário do banco, de `backend/storage/anexos/` e de `storage/capas/`.
- Envie o projeto **sem** `backend/.env`, `cookies*.txt` e o conteúdo de
  `backend/storage/`.

## Opção A: VPS ou servidor próprio (a mais recomendada)

Um único servidor web serve as duas partes, e a API fica **fechada para a
internet**, atendendo só a própria máquina:

```text
internet ──HTTPS──► elos.seusite.com.br  (frontend/)
                          │
                          └──► 127.0.0.1:8000  (backend/public/, só local)
```

Modelos prontos, é só trocar o domínio e as pastas:

- Nginx + PHP-FPM: [`deploy/nginx-elos.conf`](../deploy/nginx-elos.conf)
- Apache: [`deploy/apache-elos.conf`](../deploy/apache-elos.conf)

As variáveis `DB_*` ficam na configuração do servidor (pool do PHP-FPM ou
`SetEnv` do Apache), em arquivos que só o administrador (root) consegue
ler. HTTPS de graça com o `certbot`.

## Opção B: hospedagem compartilhada (cPanel, hPanel…)

Sem acesso total ao servidor, a API não consegue ficar "só local". Ela
ganha um **subdomínio** e se protege com a chave `ELOS_API_CHAVE`:

```text
elos.seusite.com.br      → pasta frontend/
api-elos.seusite.com.br  → pasta backend/public/
```

1. Crie o banco pelo painel e importe `backend/database/elos.sql`.
2. Envie `backend/` e `frontend/` para **fora** da `public_html` e aponte
   cada (sub)domínio para a sua pasta. Se o painel não deixar, o
   `backend/.htaccess` impede que o resto do backend fique acessível.
3. Gere uma chave: `php -r 'echo bin2hex(random_bytes(32));'`.
4. No servidor, crie o `backend/.env` a partir do `backend/.env.example`
   com `DB_*`, `ELOS_API_CHAVE`, `ELOS_API_URL=https://api-elos...` e, se
   usar, as `GOOGLE_*`.
5. Ative o HTTPS dos dois endereços pelo painel.

> O `backend/.env` com as senhas fica só no servidor, nunca no git.

## Opção C: alwaysdata (grátis), onde o ELOS está hoje

- Site PHP `projeto-elos.alwaysdata.net`, com a raiz em `elos/frontend/`.
- O plano grátis tem **um processo PHP só**. Por isso o frontend não
  consegue chamar a API por HTTP: ela ficaria esperando um processo livre
  e responderia com erro 503. A solução foi colocar
  `ELOS_API_CGI=/usr/bin/php-cgi` no `backend/.env` do servidor. Assim o
  frontend roda a API diretamente, e ela nem precisa de endereço público.
- Banco MySQL `projeto-elos_elos`, criado pelo painel e importado com o
  `elos.sql` sem as linhas `CREATE DATABASE`/`USE`.
- Para atualizar: enviar `backend/` e `frontend/` por SSH (sem o `.env` e
  sem as pastas `storage/`). Se houver migração nova em
  `backend/database/migracoes/`, rodar no banco **antes** de enviar o código.
- Versão do PHP: no painel, em **Ambiente → PHP**.
