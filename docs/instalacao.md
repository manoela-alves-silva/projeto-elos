# Rodando no seu computador

Do zero até o ELOS aberto no navegador.

[← Voltar para o README](../README.md)

## O que você precisa ter instalado

- PHP 8.1 ou mais novo (testado no 8.5), com as extensões `pdo_mysql`, `fileinfo` e `gd` (a `gd` reduz as imagens de capa);
- MySQL ou MariaDB;
- Git.

## Passo 1: criar o banco de dados

**Se é a primeira vez:**

```bash
mysql -u SEU_USUARIO -p < backend/database/elos.sql
```

Esse comando cria o banco `elos_db`, as tabelas e as categorias
sugeridas.

**Se você já tem o banco de uma versão antiga:** rode uma vez cada arquivo
de `backend/database/migracoes/` que ainda não foi aplicado, na ordem das
datas:

```bash
mysql -u SEU_USUARIO -p elos_db < backend/database/migracoes/2026-09-22_responsavel_livre.sql
mysql -u SEU_USUARIO -p elos_db < backend/database/migracoes/2026-09-23_checklist_unico.sql
mysql -u SEU_USUARIO -p elos_db < backend/database/migracoes/2026-10-14_codigo_recuperacao.sql
mysql -u SEU_USUARIO -p elos_db < backend/database/migracoes/2026-10-27_identidade_visual.sql
```

## Passo 2: configurar

As configurações (senha do banco, chaves etc.) ficam no arquivo
`backend/.env`, que o backend e o frontend leem. Ele fica fora das pastas
públicas e **nunca vai para o git**. Crie o seu a partir do modelo:

```bash
cp backend/.env.example backend/.env
# abra backend/.env e preencha DB_USER, DB_PASSWORD...
```

| Variável        | Usada por | Para quê                                                       |
|-----------------|-----------|----------------------------------------------------------------|
| `DB_*`          | backend   | conexão com o banco (obrigatórias)                             |
| `ELOS_DEBUG`    | backend   | `1` mostra detalhes dos erros. Deixe vazio em produção         |
| `ELOS_GESTORES` | backend   | e-mails (separados por vírgula) que já entram como gestores. Opcional |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | backend | "Entrar com Google" (ver abaixo). Se ficarem vazias, o botão avisa que não está configurado |
| `ELOS_API_URL`  | frontend  | endereço da API. Vazio = `http://127.0.0.1:8000`               |
| `ELOS_API_CHAVE` | os dois  | a mesma chave nos dois lados. Com ela, a API recusa quem não envia a chave. Obrigatória se a API ficar num endereço público |
| `ELOS_API_CGI`  | frontend  | caminho do `php-cgi` (ex.: `/usr/bin/php-cgi`). Faz o frontend rodar a API ali mesmo, em vez de chamar por HTTP. Serve para hospedagens com um processo PHP só, como o plano grátis da alwaysdata |
| `ELOS_SESSOES_DIR` | frontend | pasta onde ficam os logins. Vazio = `storage/sessoes/` na raiz do projeto |

> Se uma variável já existir no ambiente do servidor, ela vale mais que a
> do arquivo `.env`.

**Entrar com Google.** As variáveis `GOOGLE_*` vêm de um cliente OAuth do
tipo "Aplicativo da Web", criado no Google Cloud Console (Google Auth
Platform → Clientes). Em "URIs de redirecionamento autorizados", cadastre
o endereço de retorno exatamente como você acessa o sistema, por exemplo
`http://127.0.0.1:8081/pages/google_retorno.php` (e a versão com
`localhost`, se usar). Enquanto o app estiver em modo "Testando" no
Google, só os e-mails da lista de usuários de teste conseguem entrar.
Quem entra com Google sem ter conta ganha uma conta pendente, igual ao
cadastro normal.

**Logins.** As sessões ficam em `storage/sessoes/` (frontend) e
`backend/storage/sessoes/` (backend), pastas criadas sozinhas. Com
"Lembrar de mim", a pessoa fica logada por 30 dias. Sem ele, o login
acaba ao fechar o navegador ou depois de 2 horas sem uso.

## Passo 3: ligar os servidores

```bash
./iniciar-dev.sh
```

O script lê o `backend/.env` e liga os dois servidores do PHP: a API em
`127.0.0.1:8000` e as telas em `127.0.0.1:8081`. Para desligar os dois,
aperte **Ctrl+C**.

Abra **http://127.0.0.1:8081**, clique em **Criar conta** e entre. Num
banco vazio, a primeira conta já vira gestora.

<details>
<summary>Prefere ligar cada um à mão, em dois terminais?</summary>

```bash
# Terminal 1: backend (com as variáveis DB_* exportadas)
php -S 127.0.0.1:8000 -t backend/public

# Terminal 2: frontend (o roteador.php faz os endereços limpos)
php -S 127.0.0.1:8081 -t frontend frontend/roteador.php
```

</details>

> Os erros do backend aparecem no terminal. Se uma tela disser que não
> conseguiu falar com o servidor, confira se o backend está ligado e se as
> variáveis `DB_*` estão certas.
