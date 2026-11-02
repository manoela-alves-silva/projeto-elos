# Como o código está organizado

As duas aplicações (API e telas), as pastas e as combinações para quem for mexer no código.

[← Voltar para o README](../README.md)

## Duas aplicações que conversam

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
[Colocando no ar](deploy.md)).

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
    ├── roteador.php              endereços limpos (/exposicoes, /exposicao/luz-e-memoria…)
    ├── .htaccess                 manda tudo para o roteador (menos assets/)
    ├── index.php                 Início: números do painel e andamento por fase
    ├── capa.php                  entrega a imagem de capa (só para quem está logado)
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
    │   ├── Fases.php             agrupa o checklist por fase (pela categoria), com cores
    │   ├── Identidade.php        capa e cor de cada exposição
    │   └── MenuLateral.php       menu (Início, Exposições, Calendário, Equipe)
    ├── assets/css/               base.css + um CSS por tela
    └── assets/js/elos.js

storage/capas/                    imagens de capa das exposições (fora do git)
docs/                             guias (como funciona, código, instalação, API, segurança, deploy)
docs/gifs/                        GIFs usados no README
deploy/                           modelos de Nginx e Apache para VPS
iniciar-dev.sh                    liga o backend e o frontend no seu computador
```

---

## O caminho de um clique

Um exemplo para ver as camadas trabalhando juntas: alguém marca um item
do checklist como feito.

```text
1. Navegador     clica na caixinha do item
                 → envia o formulário para /exposicao/luz-e-memoria

2. Roteador      frontend/roteador.php vê "/exposicao/<nome>" e abre
                 frontend/pages/evento.php, que acha a exposição pelo nome

3. Tela          evento.php confere a chave CSRF, lê "marcar_necessidade"
                 e pede à API (frontend/src/Api.php):
                 PUT /api/eventos/5/tarefas/42/status  {"status": "CONCLUIDA"}

4. API           backend/public/index.php → routes/api.php
                 → routes/eventos_dispatcher_routes.php
                 → routes/tarefa_routes.php (handleTarefaStatusRequest)
                 confere quem está logado e se pode marcar esse item

5. Regras        src/controllers/tarefa_controller.php valida o status

6. Banco         src/repositories/tarefa_repository.php faz o UPDATE
                 e services/historico_service.php registra "Item concluído"

7. Volta         a API responde em JSON, a tela guarda o aviso
                 "Marcado como concluído." e redireciona para o item
```

Todas as outras ações seguem o mesmo caminho: a **tela** recebe o
pedido, a **rota** confere permissões, o **controller** valida e o
**repository** fala com o banco.

---

## Para quem for mexer no código

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
