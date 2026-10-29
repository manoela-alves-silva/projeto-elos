# API

As rotas que o backend oferece. Todas respondem em JSON.

[← Voltar para o README](../README.md)

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
| `/api/eventos/planejamento` | GET | `?ids=1,2,3`: datas e checklist de várias exposições numa resposta só |
| `/api/eventos/{id}/agenda` | GET, POST, PUT | datas da exposição |
| `/api/eventos/{id}/tarefas[/{id}]` | GET, POST, PUT, DELETE | checklist |
| `/api/eventos/{id}/tarefas/{id}/status` | PUT | marcar como feito (colaborador: só os seus) |
| `/api/eventos/{id}/cursos[/{id}]` | GET, POST, DELETE | |
| `/api/eventos/{id}/anexos[/{id}]` | GET, POST, DELETE | até 10 MB. PDF, imagens, Office/LibreOffice, TXT, CSV |
| `/api/eventos/{id}/ficha` | GET, PUT | ficha de um evento de projeto (Cine Clube, Café Cultural...): `{"dados": {...}}`. PUT: Gestor |
| `/api/fichas` | GET | fichas de todos os eventos de projeto numa resposta só |
| `/api/eventos/{id}/historico` | GET, POST | gravado automaticamente pelas outras rotas |
| `/api/tipos-evento`, `/api/responsaveis`, `/api/locais`, `/api/cursos`, `/api/categorias-tarefa` (e `/{id}`) | GET, POST, PUT | cadastros de apoio |
| `/api/painel` | GET | painel ao vivo: itens em três colunas (atrasados, prioridade alta, pendentes), os de prioridade alta sem prazo e o que foi concluído hoje. Pessoa logada ou monitor com o código no cabeçalho `X-Elos-Painel` |
| `/api/painel/codigo` | GET, POST, DELETE | link do monitor: ver se há um valendo, gerar outro (o anterior para de valer), desligar. Gestor |
| `/api/lote` | GET | `?caminhos[]=...&caminhos[]=...`: até 20 leituras numa resposta só (ver abaixo) |

### Vários pedidos de uma vez

Na hospedagem atual, cada pedido à API abre um processo PHP novo. Por isso
as telas que precisam de muitos dados juntam os pedidos:

- `/api/eventos/planejamento` traz as datas e o checklist de várias
  exposições (Início, Exposições, Calendário e Relatório).
- `/api/lote` responde vários GETs de uma vez (a página da exposição usa).
  Cada caminho passa pelas mesmas rotas e permissões de sempre, e a
  resposta traz `caminho`, `status` e `dados` de cada um. Só aceita
  leituras das listas e das exposições, nunca gravações.

No frontend, `Api::preCarregar()` faz esse pedido e guarda as respostas.
Se a rota falhar, cada dado é buscado sozinho, como antes.

Os detalhes de cada rota (campos aceitos e respostas) estão nos arquivos
de `backend/routes/`.
