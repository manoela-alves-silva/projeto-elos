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

### Painel com o andamento de cada fase

O Início soma todas as exposições em andamento (pendências, atrasadas,
prazos da semana e quanto já foi resolvido) e mostra um gráfico com o
**andamento de cada fase** da exposição em destaque. Clicar numa barra
abre o checklist já filtrado naquela fase.

![Painel do Início com os números e o gráfico de andamento por fase](docs/gifs/painel.gif)

### Criar uma exposição

A pessoa preenche o nome, o local, as datas e o que vai precisar. Se o
local já estiver ocupado nesse período, o ELOS **avisa antes de salvar**
e mostra qual exposição está lá.

![Criando uma exposição e recebendo o aviso de local ocupado](docs/gifs/nova-exposicao.gif)

### Acompanhar o checklist

Cada exposição tem uma lista do que precisa ser feito, organizada em
**fases** (Curadoria, Montagem, Divulgação…), cada uma com a sua cor e o
seu progresso. Filtros no topo mostram só uma fase ou só o que vence nos
próximos 7 dias. É só marcar o item como feito, e o progresso se
atualiza. Um item que passa da data sem ser concluído aparece como
**atrasado** sozinho.

![Filtrando o checklist por fase, marcando um item como feito e adicionando outro](docs/gifs/checklist.gif)

### Cada exposição com a sua identidade

Cada exposição pode ter uma **imagem de capa** e uma cor. A imagem aparece
nos cards e some num degradê, sem atrapalhar a leitura. Ao enviar a
imagem, o ELOS sugere uma cor tirada dela.

![Enviando a capa de uma exposição e vendo os cards com a nova identidade](docs/gifs/capa.gif)

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

## Rodando no seu computador

Precisa de **PHP 8.1+** (testado no 8.5) e **MySQL**. Em quatro passos:

```bash
# 1. banco de dados
mysql -u SEU_USUARIO -p < backend/database/elos.sql

# 2. configurações (preencha DB_USER, DB_PASSWORD...)
cp backend/.env.example backend/.env

# 3. liga a API (porta 8000) e as telas (porta 8081)
./iniciar-dev.sh

# 4. abra http://127.0.0.1:8081 e clique em "Criar conta"
```

Num banco vazio, a primeira conta já vira gestora. O passo a passo
completo, com Google e banco antigo, está em
[docs/instalacao.md](docs/instalacao.md).

---

## Documentação

| Guia | O que explica |
|------|---------------|
| [Como o ELOS funciona](docs/como-funciona.md) | a exposição no centro de tudo, as regras de negócio e os perfis (gestor e colaborador) |
| [Como o código está organizado](docs/codigo.md) | as duas aplicações, as pastas, o caminho de um clique da tela até o banco e as combinações para mexer no código |
| [Rodando no seu computador](docs/instalacao.md) | instalação passo a passo, migrações e configurações |
| [API](docs/api.md) | todas as rotas do backend e quem pode usar cada uma |
| [Segurança](docs/seguranca.md) | senhas, CSRF, XSS, limite de tentativas, anexos e cabeçalhos |
| [Colocando no ar](docs/deploy.md) | VPS, hospedagem compartilhada e alwaysdata (onde o ELOS está hoje) |

---

## Princípio

O ELOS não precisa ser complicado para parecer sofisticado.

```text
CLAREZA → ORGANIZAÇÃO → FUNCIONALIDADE → EXPERIÊNCIA DE USO
```

---

Feito por **Manoela Silva** ·
[LinkedIn](https://www.linkedin.com/in/manoela-silva-592641221/)