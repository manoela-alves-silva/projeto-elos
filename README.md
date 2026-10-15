# ELOS: planejamento de exposições

**Eventos que conectam pessoas, ideias e oportunidades.**

O ELOS é um sistema web que organiza as exposições da Galeria de Arte
La Salle (Unilasalle-RJ). Antes, as informações de cada exposição ficavam
espalhadas em planilhas, documentos e conversas. Agora ficam todas num
lugar só, e o próprio sistema avisa o que está atrasado ou perto do prazo.

🔗 **Veja no ar:** [projeto-elos.alwaysdata.net](https://projeto-elos.alwaysdata.net)
· 💼 **Contato:** [LinkedIn: Manoela Silva](https://www.linkedin.com/in/manoela-silva-592641221/)

> Projeto desenvolvido sozinha por mim: telas, banco de dados, API,
> segurança e hospedagem.

![Visão geral do ELOS: login, página inicial, exposição e calendário](docs/gifs/visao-geral.gif)

---

## O ELOS em ação

> Os GIFs usam dados inventados, só para demonstração.

### Criar uma exposição

A pessoa preenche o nome, o local, as datas e o que vai precisar. Se o
local já estiver ocupado nesse período, o ELOS **avisa antes de salvar**
e mostra qual exposição está lá.

![Criando uma exposição e recebendo o aviso de local ocupado](docs/gifs/nova-exposicao.gif)

### Acompanhar o checklist

Cada exposição tem uma lista do que precisa ser feito. É só marcar o item
como feito, e a barra de progresso se atualiza. Um item que passa da data
sem ser concluído aparece como **atrasado** sozinho.

![Marcando um item como feito e adicionando uma nova necessidade](docs/gifs/checklist.gif)

### Calendário que se monta sozinho

Ninguém precisa preencher o calendário. Ele junta as datas de todas as
exposições e dos itens do checklist. Dá para filtrar por exposição.

![Calendário com troca de mês e filtro por exposição](docs/gifs/calendario.gif)

### Equipe com aprovação

Quem quer entrar cria a própria conta, mas só consegue usar o sistema
depois que um gestor aprova. O gestor também pode promover alguém a
gestor.

![Aprovando uma conta e tornando um colaborador gestor](docs/gifs/equipe.gif)

### Funciona no celular

<img src="docs/gifs/celular.gif" alt="ELOS no celular" width="300">

---

## O que eu usei e aprendi

| Área | O que tem no projeto |
|------|----------------------|
| **Back-end** | PHP 8 puro, sem framework, organizado em camadas (rotas → controllers → repositories). API REST que responde em JSON |
| **Banco de dados** | MySQL, com consultas protegidas por parâmetros (PDO) e arquivos de migração para cada mudança no banco |
| **Front-end** | PHP, HTML, CSS e JavaScript, sem bibliotecas. Layout responsivo (computador e celular) e identidade visual própria |
| **Regras de negócio** | situação da exposição calculada pelas datas, aviso de local ocupado, itens atrasados, histórico automático de quem fez o quê |
| **Login e permissões** | dois perfis (gestor e colaborador), aprovação de contas, "Entrar com Google" (OAuth), código para recuperar a senha |
| **Segurança** | senhas com hash, proteção contra CSRF e XSS, limite de tentativas de login, conferência do tipo real dos anexos |
| **Hospedagem** | no ar na alwaysdata, com HTTPS. Também deixei modelos de configuração para Nginx e Apache |
| **Git** | trabalho em branches e commits descritivos (`feat`, `fix`, `docs`…) |

---

## Sumário da parte técnica

1. [Como o sistema funciona](#1-como-o-sistema-funciona)
2. [Regras de negócio](#2-regras-de-negócio)
3. [Perfis e equipe](#3-perfis-e-equipe)
4. [Como o código está organizado](#4-como-o-código-está-organizado)
5. [Rodando no seu computador](#5-rodando-no-seu-computador)
6. [API](#6-api)
7. [Segurança](#7-segurança)
8. [Colocando no ar](#8-colocando-no-ar)
9. [O que ainda falta](#9-o-que-ainda-falta)
10. [Para quem for mexer no código](#10-para-quem-for-mexer-no-código)

---

## 1. Como o sistema funciona

**Tudo gira em torno da exposição.** Cada informação pertence a uma
exposição, e as outras telas são montadas automaticamente a partir dela:

```text
                 ┌──────────────┐
                 │  EXPOSIÇÃO   │  nome, tipo, responsável, local, cursos
                 └──────┬───────┘
        ┌───────────────┼────────────────┐
        ▼               ▼                ▼
     Datas        Necessidades        Anexos
 (montagem,        (checklist)
  abertura,
  em cartaz,
  desmontagem)
        │               │
        └───────┬───────┘
                ▼
     Início · Calendário · Relatório   ← ninguém preenche: o sistema monta
```

- **Necessidades** são os itens do checklist. Cada um diz o que precisa
  ser feito, com categoria, data, horário, responsável, prioridade e
  observações. O responsável pode ser qualquer pessoa, mesmo sem conta no
  sistema (um técnico, um setor, alguém de fora).
- O **Início** mostra a exposição que está sendo acompanhada, as próximas
  atividades e o que está pendente.
- O **Calendário** junta as datas das exposições e dos itens do checklist.
- O **Relatório** gera uma versão de cada exposição pronta para imprimir.

---

## 2. Regras de negócio

As regras de negócio são as "regras do jogo": o que o sistema faz sozinho
e o que ele permite ou impede.

### A situação da exposição vem das datas

Ninguém precisa lembrar de mudar a situação da exposição. O sistema
calcula:

| Situação       | Quando                                                     |
|----------------|------------------------------------------------------------|
| Planejamento   | ainda sem datas, ou antes do primeiro dia marcado (em geral, a montagem) |
| Em andamento   | do primeiro ao último dia marcado (da montagem à desmontagem) |
| Concluída      | depois do último dia marcado. Sai do Início sozinha        |
| Cancelada      | **a única escolhida à mão** (caixa "Exposição cancelada")  |

Esse cálculo é feito no backend (`EventoRepository`), então todas as telas
mostram a mesma situação. No banco fica guardado apenas se a exposição foi
cancelada.

### Aviso de local ocupado

Ao criar uma exposição, mudar as datas ou trocar o local, o sistema
confere se outra exposição (não cancelada) usa o mesmo local no mesmo
período, da montagem até a desmontagem. Se usar, aparece um aviso com o
nome da outra exposição, e nada é salvo até a pessoa clicar em
**"Salvar mesmo assim"**. É só um aviso, e não um bloqueio, porque duas
mostras podem dividir o espaço de propósito.

### Um checklist só

Transportes, visitas e documentos **são itens do checklist**. A categoria
diz de que tipo é cada um (Transporte, Visita, Documentação…), e os
detalhes vão nas observações:

| Exemplo de item | Categoria | Data e horário | Observações |
|-----------------|-----------|----------------|-------------|
| Buscar as obras no ateliê | Transporte | 10/10, 08:00 | Rua X → Galeria. Caminhão baú |
| Visita da Escola Municipal Y | Visita | 15/10, 14:00 | 30 alunos, 2 professores |
| Enviar termo de cessão de obras | Documentação | 01/10 | Assinado pela artista |

Não existe uma tela separada para transportes, visitas ou formulários.
Tudo fica no mesmo lugar, e o Calendário e o Início mostram esses itens
com data e horário.

### Itens atrasados

Ninguém marca um item como "atrasado". Se ele não foi concluído até a
data, o sistema mostra como atrasado sozinho.

### Histórico automático

A página de cada exposição guarda **quem fez o quê e quando**. Quem grava
é o backend, então ninguém precisa anotar e ninguém consegue inventar um
registro. Ficam registrados:

- exposição criada, cancelada, reativada ou apagada;
- informações alteradas (quais campos mudaram e o novo local);
- datas definidas ou alteradas (com um resumo das datas);
- item do checklist adicionado, editado, removido, concluído ou reaberto;
- anexo adicionado ou removido.

Salvar sem mudar nada não cria registro. Se a gravação do histórico
falhar, a ação continua valendo (o erro vai para o log).

### Prioridade só nos itens

A exposição não tem prioridade. Quem tem são os itens do checklist, e os
de prioridade alta com data próxima aparecem como alerta no Início.

### Cancelar ou apagar uma exposição

- **Cancelar** é o caminho normal: a exposição continua guardada, só sai
  das listas de acompanhamento. Assim a história da galeria não se perde.
- **Apagar** é definitivo e só um gestor pode fazer. Apaga a exposição e
  tudo dela (datas, checklist, cursos e anexos). Para evitar acidentes, o
  sistema mostra o que vai ser apagado e pede para digitar o nome da
  exposição. O histórico ganha o registro "Exposição apagada", com quem
  apagou.

---

## 3. Perfis e equipe

| Perfil          | O que pode fazer                                                   |
|-----------------|--------------------------------------------------------------------|
| **Gestor**      | cria e edita exposições e checklist, aprova contas e cuida da equipe |
| **Colaborador** | acompanha as exposições e marca como feitos os itens que estão com ele |

Também existe o perfil `ADMIN` no banco, com os mesmos poderes do gestor.
Ele não pode ser alterado nem excluído pela tela Equipe.

### Como alguém entra na equipe

1. A pessoa clica em **Criar conta**, na tela de login, e usa o próprio
   e-mail.
2. A conta fica **aguardando aprovação**. Até lá, a pessoa não consegue
   entrar.
3. Um gestor abre **Equipe** e escolhe **Aprovar** ou **Recusar**. Recusar
   apaga o cadastro.
4. Depois de aprovada, a pessoa entra como colaboradora. Um gestor pode
   torná-la gestora na mesma tela.

### Regras da tela Equipe

- O sistema **nunca fica sem gestor**. Para deixar de ser gestor (ou para
  excluir o último gestor), é preciso antes tornar outra pessoa gestora.
- Um gestor pode **excluir** a conta de outra pessoa, mas não a própria.
- Um gestor pode **redefinir a senha** de alguém, gerando uma senha
  provisória.
- A troca de perfil vale na hora, sem precisar sair e entrar de novo.
- **Primeiro acesso de um sistema novo:** se ainda não existe nenhum
  gestor, a primeira conta criada já nasce gestora e aprovada.

Quem decide o que cada pessoa pode fazer é o backend. O frontend só
esconde os botões que a pessoa não pode usar.

---

## 4. Como o código está organizado

O ELOS é formado por **duas aplicações PHP** que conversam entre si:

```text
Navegador ──► FRONTEND (telas)  ──► BACKEND (API JSON) ──► MySQL
              frontend/              backend/public/
```

- **Backend** (a parte que o usuário não vê): uma API que recebe pedidos e
  responde em JSON. Cuida do login, das permissões, das regras de negócio
  e do banco de dados.
- **Frontend** (as telas): páginas PHP que mostram as informações. Elas
  pedem os dados à API **de servidor para servidor**
  (`frontend/src/Api.php`). O navegador nunca fala direto com a API, o que
  deixa o sistema mais seguro.

No seu computador, cada parte roda num servidor do próprio PHP (portas
8000 e 8081). Em produção, um único Apache ou Nginx serve as duas (ver
seção 8).

### Pastas

```text
projeto-elos/
├── backend/
│   ├── public/index.php          porta de entrada da API
│   ├── routes/                   uma rota por recurso (api.php distribui)
│   ├── src/
│   │   ├── config/               conexão com o banco (variáveis DB_*)
│   │   ├── controllers/          validação e regras
│   │   ├── repositories/         consultas SQL (PDO com parâmetros)
│   │   └── services/             sessão, login, permissões, histórico, limite de tentativas
│   ├── database/
│   │   ├── elos.sql              banco completo, para instalação nova
│   │   └── migracoes/            mudanças para bancos que já existem
│   ├── storage/                  anexos enviados e controle de login (fora do git)
│   └── .env.example              modelo das configurações
│
└── frontend/
    ├── index.php                 Início
    ├── pages/
    │   ├── eventos.php           lista de exposições
    │   ├── evento_novo.php       nova exposição
    │   ├── evento.php            a exposição: dados, datas, checklist, anexos
    │   ├── apagar_exposicao.php  confirmação antes de apagar
    │   ├── agenda.php            calendário
    │   ├── relatorio.php         relatório para imprimir
    │   ├── equipe.php            aprovar contas e trocar perfis (gestor)
    │   ├── minha_conta.php       dados da conta, senha e código de recuperação
    │   └── login, cadastro, recuperar_senha, google_*, logout
    ├── src/
    │   ├── Api.php               conversa com a API, sessão e proteção CSRF
    │   ├── elos.php              funções usadas por várias telas
    │   ├── Planejamento.php      junta datas e checklist de uma exposição
    │   └── MenuLateral.php       menu (Início, Exposições, Calendário, Equipe)
    ├── assets/css/               base.css + um CSS por tela
    └── assets/js/elos.js

docs/gifs/                        GIFs usados neste README
deploy/                           modelos de Nginx e Apache para VPS
iniciar-dev.sh                    liga o backend e o frontend no seu computador
```

---

## 5. Rodando no seu computador

### O que você precisa ter instalado

- PHP 8.1 ou mais novo, com as extensões `pdo_mysql` e `fileinfo`;
- MySQL ou MariaDB;
- Git.

### Passo 1: criar o banco de dados

**Se é a primeira vez:**

```bash
mysql -u SEU_USUARIO -p < backend/database/elos.sql
```

Esse comando cria o banco `elos_db`, as tabelas, as etapas e as categorias
sugeridas.

**Se você já tem o banco de uma versão antiga:** rode uma vez cada arquivo
de `backend/database/migracoes/` que ainda não foi aplicado, na ordem das
datas:

```bash
mysql -u SEU_USUARIO -p elos_db < backend/database/migracoes/2026-09-22_responsavel_livre.sql
mysql -u SEU_USUARIO -p elos_db < backend/database/migracoes/2026-09-23_checklist_unico.sql
mysql -u SEU_USUARIO -p elos_db < backend/database/migracoes/2026-10-14_codigo_recuperacao.sql
```

### Passo 2: configurar

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

### Passo 3: ligar os servidores

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

# Terminal 2: frontend
php -S 127.0.0.1:8081 -t frontend
```

</details>

> Os erros do backend aparecem no terminal. Se uma tela disser que não
> conseguiu falar com o servidor, confira se o backend está ligado e se as
> variáveis `DB_*` estão certas.

---

## 6. API

A API é a "porta" que o frontend usa para buscar e salvar dados. Todas as
rotas respondem em JSON. Com exceção de login, logout e cadastro, todas
exigem que a pessoa esteja logada. Onde está escrito "Gestor", só esse
perfil pode usar.

| Rota | Métodos | Observação |
|------|---------|------------|
| `/api/login`, `/api/logout` | POST | login com limite de tentativas |
| `/api/login/google/inicio`, `/api/login/google` | POST | entrar com Google (endereço da tela do Google; confirmação do código) |
| `/api/usuarios` | GET, POST | POST = criar conta (fica pendente) |
| `/api/usuarios/{id}` | PUT, DELETE | trocar perfil / excluir conta. Gestor |
| `/api/usuarios/pendentes` | GET | contas aguardando aprovação. Gestor |
| `/api/usuarios/pendentes/{id}` | POST, DELETE | aprovar / recusar. Gestor |
| `/api/eventos` | GET, POST | exposições (`status` já vem calculado) |
| `/api/eventos/{id}` | GET, PUT, DELETE | DELETE apaga a exposição e tudo dela (definitivo). Gestor |
| `/api/eventos/conflitos` | GET | `?local_id=&inicio=&fim=&exceto=` |
| `/api/eventos/{id}/agenda` | GET, POST, PUT | datas da exposição |
| `/api/eventos/{id}/tarefas[/{id}]` | GET, POST, PUT, DELETE | checklist |
| `/api/eventos/{id}/tarefas/{id}/status` | PUT | marcar como feito (colaborador: só os seus) |
| `/api/eventos/{id}/cursos[/{id}]` | GET, POST, DELETE | |
| `/api/eventos/{id}/formularios[/{id}]` | GET, POST, PUT, DELETE | antigas, sem uso nas telas (ver seção 9) |
| `/api/eventos/{id}/transportes[/{id}]` | GET, POST, PUT, DELETE | antigas, sem uso nas telas |
| `/api/eventos/{id}/visitas[/{id}]` | GET, POST, PUT, DELETE | antigas, sem uso nas telas |
| `/api/eventos/{id}/anexos[/{id}]` | GET, POST, DELETE | até 10 MB. PDF, imagens, Office/LibreOffice, TXT, CSV |
| `/api/eventos/{id}/historico` | GET, POST | gravado automaticamente pelas outras rotas |
| `/api/tipos-evento`, `/api/responsaveis`, `/api/locais`, `/api/cursos`, `/api/etapas`, `/api/categorias-tarefa` (e `/{id}`) | GET, POST, PUT | cadastros de apoio |

Os detalhes de cada rota (campos aceitos e respostas) estão nos arquivos
de `backend/routes/`.

---

## 7. Segurança

O que o ELOS já faz para se proteger:

- **Senhas** guardadas com `password_hash` (nunca em texto puro).
- **Banco de dados:** todo SQL usa parâmetros (PDO), o que impede SQL
  Injection.
- **XSS** (quando alguém tenta colocar código malicioso num texto): tudo o
  que aparece na tela passa pela função `esc()`.
- **CSRF** (quando outro site tenta enviar um formulário em nome da
  pessoa): todo formulário leva uma chave secreta da sessão
  (`campoCsrf()`), conferida em `iniciarSessao()`. **Todo formulário novo
  precisa de `<?= \Elos\Frontend\campoCsrf() ?>`.** Sem ela, o envio é
  recusado com a mensagem "Esta página expirou".
- **Cookies de sessão** com `HttpOnly` e `SameSite`, `Secure` quando há
  HTTPS, e uma sessão nova a cada login.
- **Limite de tentativas:** 5 erros no mesmo e-mail, ou 20 vindos do
  mesmo endereço, em 5 minutos, bloqueiam o login por 5 minutos. Vale
  também para a senha atual e para o código de recuperação.
- **Senha esquecida:** cada conta recebe um **código de recuperação** ao
  ser criada (ou gera um novo em **Minha conta**). Com e-mail e código, a
  pessoa cria uma senha nova em **Esqueceu a senha?**. O código é
  guardado com hash e só funciona uma vez. Quem perdeu o código pede a um
  gestor uma senha provisória, na tela **Equipe**.
- **Contas novas** só entram depois de aprovadas por um gestor.
- **Anexos:** o tamanho e o tipo são conferidos pelo **conteúdo** do
  arquivo (e não só pelo nome). Os arquivos ficam fora da pasta pública,
  com nome aleatório.
- **Erros em produção** vão para o log, e não para a tela.
- **Cabeçalhos de proteção:** impedem que o ELOS seja aberto dentro de
  outro site (clickjacking) e, com HTTPS, obrigam o navegador a usar
  sempre HTTPS.
- **Chave da API:** a API pode exigir a `ELOS_API_CHAVE`, que só o
  frontend envia.
- **`.htaccess`** bloqueia `frontend/src/`, arquivos ocultos e tudo do
  backend que está fora de `public/`.
- **Sair do sistema** encerra a sessão no frontend **e** na API.

---

## 8. Colocando no ar

### Vale para qualquer hospedagem

- PHP 8.1+, com `pdo_mysql`, `fileinfo` e **`allow_url_fopen` ligado**
  (o frontend usa isso para chamar a API).
- **HTTPS é obrigatório.** Sem ele, as senhas viajam pela internet sem
  proteção. O ELOS reconhece HTTPS também atrás de proxy
  (`X-Forwarded-Proto`).
- Banco criado com `backend/database/elos.sql`.
- `ELOS_DEBUG` vazio. Assim os erros vão para o log, e a API responde só
  "Erro interno do servidor".
- A pasta `backend/storage/` precisa ter permissão de escrita para o PHP.
- Faça **backup** diário do banco e de `backend/storage/anexos/`.
- Envie o projeto **sem** `backend/.env`, `cookies*.txt` e o conteúdo de
  `backend/storage/`.

### Opção A: VPS ou servidor próprio (a mais recomendada)

Um único servidor web serve as duas partes, e a API fica **fechada para a
internet**, atendendo só a própria máquina:

```text
internet ──HTTPS──► elos.seusite.com.br  (frontend/)
                          │
                          └──► 127.0.0.1:8000  (backend/public/, só local)
```

Modelos prontos, é só trocar o domínio e as pastas:

- Nginx + PHP-FPM: [`deploy/nginx-elos.conf`](deploy/nginx-elos.conf)
- Apache: [`deploy/apache-elos.conf`](deploy/apache-elos.conf)

As variáveis `DB_*` ficam na configuração do servidor (pool do PHP-FPM ou
`SetEnv` do Apache), em arquivos que só o administrador (root) consegue
ler. HTTPS de graça com o `certbot`.

### Opção B: hospedagem compartilhada (cPanel, hPanel…)

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

### Opção C: alwaysdata (grátis), onde o ELOS está hoje

- Site PHP `projeto-elos.alwaysdata.net`, com a raiz em `elos/frontend/`.
- O plano grátis tem **um processo PHP só**. Por isso o frontend não
  consegue chamar a API por HTTP: ela ficaria esperando um processo livre
  e responderia com erro 503. A solução foi colocar
  `ELOS_API_CGI=/usr/bin/php-cgi` no `backend/.env` do servidor. Assim o
  frontend roda a API diretamente, e ela nem precisa de endereço público.
- Banco MySQL `projeto-elos_elos`, criado pelo painel e importado com o
  `elos.sql` sem as linhas `CREATE DATABASE`/`USE`.
- Para atualizar: enviar `backend/` e `frontend/` por SSH (sem o `.env` e
  sem a pasta `storage/`).

---

## 9. O que ainda falta

- **Tabelas e rotas antigas de transportes, visitas e formulários:** as
  telas foram aposentadas (agora tudo é checklist), mas as tabelas e as
  rotas continuam no backend, sem uso. Podem ser removidas depois, com
  uma migração.
- **Coluna `eventos.prioridade`:** as telas não usam mais (a API grava
  `MEDIA` quando não recebe nada). Também pode ser removida com uma
  migração.

---

## 10. Para quem for mexer no código

1. **Cores:** use só as variáveis de `frontend/assets/css/base.css`. A
   identidade visual (creme, azul-marinho, azul-claro, turquesa, amarelo
   e areia, com vermelho só para alertas e erros) não deve mudar.
2. **As regras ficam no backend.** O frontend mostra; o backend decide e
   protege.
3. **Mudou o banco?** Atualize o `backend/database/elos.sql` **e** crie um
   arquivo em `backend/database/migracoes/` com a data no nome.
4. **Criou um formulário?** Coloque `campoCsrf()` dentro dele.
5. **Nunca envie para o git:** `.env`, `cookies*.txt` e o conteúdo de
   `backend/storage/`. O `.gitignore` já cuida disso.
6. Teste no navegador antes de dar por pronto, inclusive no celular.

### Git

Trabalhe numa branch e escreva commits que expliquem o que mudou:

```bash
git checkout -b nome-da-mudanca
git commit -m "feat: aviso de local ocupado ao salvar datas"
```

Prefixos usados: `feat` (novidade), `fix` (correção), `style` (visual),
`refactor` (reorganização sem mudar o comportamento) e `docs`
(documentação).

---

## Princípio

O ELOS não precisa ser complicado para parecer sofisticado.

```text
CLAREZA → ORGANIZAÇÃO → FUNCIONALIDADE → EXPERIÊNCIA DE USO
```

---

Feito por **Manoela Silva** ·
[LinkedIn](https://www.linkedin.com/in/manoela-silva-592641221/)
