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
| `/api/eventos/{id}/agenda` | GET, POST, PUT | datas da exposição |
| `/api/eventos/{id}/tarefas[/{id}]` | GET, POST, PUT, DELETE | checklist |
| `/api/eventos/{id}/tarefas/{id}/status` | PUT | marcar como feito (colaborador: só os seus) |
| `/api/eventos/{id}/cursos[/{id}]` | GET, POST, DELETE | |
| `/api/eventos/{id}/anexos[/{id}]` | GET, POST, DELETE | até 10 MB. PDF, imagens, Office/LibreOffice, TXT, CSV |
| `/api/eventos/{id}/historico` | GET, POST | gravado automaticamente pelas outras rotas |
| `/api/tipos-evento`, `/api/responsaveis`, `/api/locais`, `/api/cursos`, `/api/categorias-tarefa` (e `/{id}`) | GET, POST, PUT | cadastros de apoio |

Os detalhes de cada rota (campos aceitos e respostas) estão nos arquivos
de `backend/routes/`.
