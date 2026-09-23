# ELOS — Planejamento de exposições

**Eventos que conectam pessoas, ideias e oportunidades.**

O ELOS é um sistema web para planejar e acompanhar as exposições da
Galeria de Arte La Salle / Unilasalle-RJ. A ideia é reunir num lugar só o
que antes ficava espalhado em planilhas, documentos e mensagens.

---

## 1. Como o sistema pensa

**A exposição é o centro.** Tudo o que existe no sistema pertence a uma
exposição, e as outras telas se montam sozinhas a partir dela:

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
     Início · Calendário · Relatório   ← ninguém preenche: são calculados
```

- **Necessidades** são livres: cada item do checklist diz o que precisa ser
  feito, com categoria, data, horário, responsável, prioridade e
  observações. O responsável pode ser qualquer pessoa, mesmo sem conta no
  sistema, como um técnico, um setor ou alguém de fora.
- O **Início** mostra a exposição em acompanhamento, as próximas atividades
  e as pendências.
- O **Calendário** junta as datas das exposições e dos itens do checklist.
- O **Relatório** gera uma versão para imprimir de cada exposição.

---

## 2. Regras de negócio

### Situação da exposição: vem das datas

Ninguém precisa lembrar de mudar a situação da exposição:

| Situação       | Quando                                                     |
|----------------|------------------------------------------------------------|
| Planejamento   | sem datas, ou antes do primeiro dia marcado (em geral a montagem) |
| Em andamento   | do primeiro ao último dia marcado (montagem → desmontagem) |
| Concluída      | depois do último dia marcado; sai do Início sozinha        |
| Cancelada      | **única escolhida à mão** (caixa "Exposição cancelada")    |

O cálculo é feito no backend (`EventoRepository`), então todas as telas
mostram a mesma situação. No banco fica guardado só se a exposição foi
cancelada.

### Local ocupado

Ao criar uma exposição, mudar as datas ou trocar o local, o sistema confere
se outra exposição não cancelada ocupa o mesmo local no mesmo período, da
montagem até a desmontagem. Se ocupar, aparece um aviso com a outra
exposição e nada é salvo até a pessoa escolher **"Salvar mesmo assim"**.
É um aviso, e não um bloqueio, porque duas mostras podem dividir o espaço
de propósito.

### Um checklist só

Transportes, visitas e documentos **são itens do checklist**. A categoria
diz o tipo (Transporte, Visita, Documentação…). O que é específico de cada
tipo vai nas observações:

| Exemplo de item | Categoria | Data e horário | Observações |
|-----------------|-----------|----------------|-------------|
| Buscar as obras no ateliê | Transporte | 10/10, 08:00 | Rua X → Galeria. Caminhão baú |
| Visita da Escola Municipal Y | Visita | 15/10, 14:00 | 30 alunos, 2 professores |
| Enviar termo de cessão de obras | Documentação | 01/10 | Assinado pela artista |

Não existe tela separada de transportes, visitas ou formulários: tudo fica
no mesmo lugar, e o Calendário e o Início mostram esses itens com data e
horário.

### Itens atrasados

"Atrasado" não se escolhe: um item que não foi marcado como feito até a
data aparece como atrasado sozinho.

### Histórico automático

A página da exposição tem um histórico de **quem fez o quê e quando**,
gravado pelo backend (ninguém precisa anotar e ninguém consegue forjar):

- exposição criada, cancelada ou reativada;
- informações alteradas (diz quais campos mudaram e o novo local);
- datas definidas ou alteradas (com o resumo das datas);
- item do checklist adicionado, editado, removido, concluído ou reaberto;
- anexo adicionado ou removido.

Salvar sem mudar nada não gera registro. Se o histórico falhar, a ação
continua valendo (o erro vai para o log).

### Prioridade só nos itens

A exposição não tem prioridade. Quem tem prioridade são os itens do
checklist, e os de prioridade alta com data próxima aparecem como alerta
no Início.

### Exposições não são apagadas

Uma exposição se **cancela**, não se exclui. Assim o histórico da galeria
não se perde.

---

## 3. Perfis e equipe

| Perfil          | O que faz                                                          |
|-----------------|--------------------------------------------------------------------|
| **Gestor**      | cria e edita exposições e checklist, aprova contas, gerencia a equipe |
| **Colaborador** | acompanha as exposições e marca como feitos os itens que estão com ele |

Também existe o perfil `ADMIN` no banco, com os mesmos poderes do gestor.
Ele não pode ser alterado pela tela Equipe.

### Como alguém entra na equipe

1. A pessoa clica em **Criar conta**, na tela de login, e usa o próprio e-mail.
2. A conta fica **aguardando aprovação**. Até lá, a pessoa não consegue entrar.
3. Um gestor abre **Equipe** e escolhe **Aprovar** ou **Recusar**. Recusar
   apaga o cadastro.
4. Aprovada, a pessoa entra como colaboradora. Um gestor pode torná-la
   gestora na mesma tela.

Regras da tela Equipe:

- O sistema **nunca fica sem gestor**. Para deixar de ser gestor, é preciso
  antes tornar outra pessoa gestora.
- A troca de perfil vale na hora, sem precisar sair e entrar de novo.
- **Primeiro acesso de um sistema novo:** se ainda não existe nenhum
  gestor, a primeira conta criada já nasce gestora e aprovada.

A autorização de verdade fica no backend. O frontend só esconde o que a
pessoa não pode usar.

---

## 4. Arquitetura

São duas aplicações PHP que conversam entre si:

```text
Navegador ──► FRONTEND (telas)  ──► BACKEND (API JSON) ──► MySQL
              frontend/              backend/public/
```

- **Backend**: API REST em JSON. Cuida de autenticação, permissões, regras
  de negócio e banco de dados.
- **Frontend**: páginas PHP que montam as telas. Ele chama a API **de
  servidor para servidor** (`frontend/src/Api.php`), guardando a sessão do
  backend dentro da sua própria sessão. O navegador nunca fala direto com
  a API.

Em desenvolvimento, cada um roda num servidor embutido do PHP (portas 8000
e 8081). Em produção, um único Apache ou Nginx serve os dois (ver seção 8).

### Estrutura

```text
projeto-elos/
├── backend/
│   ├── public/index.php          ponto de entrada da API
│   ├── routes/                   uma rota por recurso (api.php distribui)
│   ├── src/
│   │   ├── config/               conexão com o banco (variáveis DB_*)
│   │   ├── controllers/          validação e regras
│   │   ├── repositories/         SQL (PDO com parâmetros)
│   │   └── services/             sessão, login, permissões, histórico, limite de tentativas
│   ├── database/
│   │   ├── elos.sql              banco completo, para instalação nova
│   │   └── migracoes/            mudanças para bancos que já existem
│   ├── storage/                  anexos enviados e controle de login (fora do git)
│   └── .env.example              variáveis do backend
│
└── frontend/
    ├── index.php                 Início
    ├── pages/
    │   ├── eventos.php           lista de exposições
    │   ├── evento_novo.php       nova exposição
    │   ├── evento.php            a exposição: dados, datas, checklist, anexos
    │   ├── agenda.php            calendário
    │   ├── relatorio.php         relatório para imprimir
    │   ├── equipe.php            aprovar contas e trocar perfis (gestor)
    │   └── login.php, cadastro.php, logout.php
    ├── src/
    │   ├── Api.php               cliente da API, sessão e proteção CSRF
    │   ├── elos.php              funções compartilhadas pelas telas
    │   ├── Planejamento.php      junta datas e checklist de uma exposição
    │   └── MenuLateral.php       menu (Início, Exposições, Calendário, Equipe)
    ├── assets/css/               base.css + um CSS por tela
    ├── assets/js/elos.js
    └── .env.example              variáveis do frontend
```

---

## 5. Rodando no seu computador

### Requisitos

- PHP 8.1 ou superior, com as extensões `pdo_mysql` e `fileinfo`;
- MySQL ou MariaDB;
- Git.

### 1) Banco de dados

**Instalação nova:**

```bash
mysql -u SEU_USUARIO -p < backend/database/elos.sql
```

Isso cria o banco `elos_db`, as tabelas, as etapas e as categorias
sugeridas.

**Banco que já existe:** rode, uma vez, cada arquivo de
`backend/database/migracoes/` que ainda não foi aplicado, em ordem de data:

```bash
mysql -u SEU_USUARIO -p elos_db < backend/database/migracoes/2026-09-22_responsavel_livre.sql
mysql -u SEU_USUARIO -p elos_db < backend/database/migracoes/2026-09-23_checklist_unico.sql
```

### 2) Variáveis de ambiente

O backend **não lê arquivo `.env`**. As variáveis precisam estar
exportadas no terminal que vai rodar o servidor. Use `backend/.env.example`
como guia:

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=elos_db
export DB_USER=seu_usuario
export DB_PASSWORD=sua_senha
export ELOS_DEBUG=1        # mostra erros nas respostas da API (só no seu computador)
```

| Variável        | Onde     | Para quê                                                        |
|-----------------|----------|-----------------------------------------------------------------|
| `DB_*`          | backend  | conexão com o banco (obrigatórias)                              |
| `ELOS_DEBUG`    | backend  | `1` mostra detalhes de erro; vazio em produção                  |
| `ELOS_GESTORES` | backend  | e-mails (separados por vírgula) que já entram como gestores. Opcional. |
| `ELOS_API_URL`  | frontend | endereço da API. Vazio = `http://127.0.0.1:8000`                |

### 3) Servidores

São dois terminais.

**Terminal 1, backend** (com as variáveis acima exportadas):

```bash
php -S 127.0.0.1:8000 -t backend/public
```

**Terminal 2, frontend:**

```bash
php -S 127.0.0.1:8081 -t frontend
```

Acesse **http://127.0.0.1:8081**, crie sua conta em **Criar conta** e
entre. Num banco vazio, a primeira conta vira gestora automaticamente.

> Os erros do backend aparecem no terminal 1. Se uma tela disser que não
> conseguiu falar com o servidor, confira se o terminal 1 está rodando e
> com as variáveis `DB_*` exportadas.

---

## 6. API

Todas as rotas respondem JSON. Exceto login, logout e cadastro, todas
exigem sessão. "Gestor" indica as rotas que exigem esse perfil.

| Rota | Métodos | Observação |
|------|---------|------------|
| `/api/login`, `/api/logout` | POST | login com limite de tentativas |
| `/api/usuarios` | GET, POST | POST = criar conta (fica pendente) |
| `/api/usuarios/{id}` | PUT | trocar perfil. Gestor |
| `/api/usuarios/pendentes` | GET | contas aguardando aprovação. Gestor |
| `/api/usuarios/pendentes/{id}` | POST, DELETE | aprovar / recusar. Gestor |
| `/api/eventos` | GET, POST | exposições (`status` já calculado) |
| `/api/eventos/{id}` | GET, PUT | |
| `/api/eventos/conflitos` | GET | `?local_id=&inicio=&fim=&exceto=` |
| `/api/eventos/{id}/agenda` | GET, POST, PUT | datas da exposição |
| `/api/eventos/{id}/tarefas[/{id}]` | GET, POST, PUT, DELETE | checklist |
| `/api/eventos/{id}/tarefas/{id}/status` | PUT | marcar feito (colaborador: só os seus) |
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

O que já está no sistema:

- senhas guardadas com `password_hash`, e todo SQL com parâmetros (PDO);
- tudo o que aparece na tela passa por `esc()`, contra XSS;
- **CSRF**: todo formulário POST leva uma chave da sessão
  (`campoCsrf()`), conferida automaticamente em `iniciarSessao()`. **Todo
  formulário novo precisa de `<?= \Elos\Frontend\campoCsrf() ?>`**. Sem
  ele, o envio é recusado com "Esta página expirou";
- cookies de sessão com `HttpOnly` e `SameSite`, `Secure` sob HTTPS, e
  sessão nova a cada login;
- **login**: 5 erros por e-mail ou 20 por endereço em 15 minutos bloqueiam
  por 15 minutos;
- contas novas só entram depois de aprovadas por um gestor;
- anexos: tamanho e tipo conferidos pelo **conteúdo** do arquivo, salvos
  fora da pasta pública e com nome aleatório;
- em produção, os erros vão para o log e não para a tela.

---

## 8. Colocando no ar

- **HTTPS é obrigatório.** Sem ele, as senhas trafegam abertas.
- **Um só servidor web** (Apache ou Nginx com PHP-FPM):
  - o site aponta para `frontend/`;
  - a API aponta para `backend/public/` e fica acessível **só pela própria
    máquina** (por exemplo, `127.0.0.1:8000`). Se ficar em outro endereço,
    configure `ELOS_API_URL` no frontend.
- Variáveis `DB_*` configuradas no PHP-FPM ou no servidor, com
  `ELOS_DEBUG` vazio.
- `backend/storage/` com permissão de escrita para o PHP.
- **Backup** diário do banco e de `backend/storage/anexos/`.

---

## 9. Em aberto

- **Tabelas e rotas antigas de transportes, visitas e formulários**: as
  telas foram aposentadas (agora tudo é checklist), mas as tabelas e as
  rotas da API continuam no backend, sem uso. Podem ser removidas depois,
  com uma migração.
- **Coluna `eventos.prioridade`**: não é mais usada pelas telas (a API
  grava `MEDIA` quando não vem nada). Pode ser removida depois, com uma
  migração.

---

## 10. Para quem for mexer no código

1. **Cores**: use só os tokens de `frontend/assets/css/base.css`. A
   identidade visual (creme, azul-marinho, azul claro, turquesa, amarelo e
   areia, com vermelho só para alertas e erros) não deve mudar.
2. **Regras de negócio ficam no backend.** O frontend mostra; o backend
   decide e protege.
3. **Mudou o banco?** Atualize `backend/database/elos.sql` **e** crie um
   arquivo em `backend/database/migracoes/` com a data no nome.
4. **Formulário novo?** Coloque `campoCsrf()` dentro dele.
5. **Nunca envie para o git:** `.env`, `cookies*.txt`, conteúdo de
   `backend/storage/`. O `.gitignore` já cuida disso.
6. Teste no navegador antes de dar por pronto, inclusive no celular.

### Git

Trabalhe numa branch e escreva commits que expliquem o que mudou:

```bash
git checkout -b nome-da-mudanca
git commit -m "feat: aviso de local ocupado ao salvar datas"
```

Prefixos usados: `feat` (novidade), `fix` (correção), `style` (visual),
`refactor` (reorganização sem mudar comportamento), `docs` (documentação).

---

## 11. Princípios

O ELOS não precisa ser complexo para parecer sofisticado.

```text
CLAREZA → ORGANIZAÇÃO → FUNCIONALIDADE → EXPERIÊNCIA DE USO
```

