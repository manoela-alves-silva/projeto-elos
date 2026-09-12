# ELOS — Sistema de Gestão de Eventos

## 1. Sobre o projeto

O ELOS é um sistema web desenvolvido para auxiliar na organização, planejamento, execução e acompanhamento de eventos e exposições da Galeria de Arte La Salle / Unilasalle-RJ.

O sistema foi pensado para centralizar em um único ambiente as informações necessárias para organizar um evento, evitando que o controle dependa exclusivamente de planilhas, documentos, mensagens ou informações espalhadas em diferentes lugares.

O conceito principal do sistema é que o **evento seja a entidade central**. Todas as informações relacionadas à organização de um evento ficam vinculadas a ele.

Um evento pode possuir:

- tipo de evento;
- responsável;
- local;
- cursos relacionados;
- agenda;
- tarefas;
- formulários;
- transportes;
- visitas;
- anexos;
- histórico.

O objetivo final é permitir acompanhar o evento desde o planejamento inicial até sua conclusão.

---

# 2. Objetivos do sistema

O ELOS deve permitir:

- cadastrar eventos;
- consultar eventos;
- editar eventos;
- acompanhar o status dos eventos;
- definir prioridades;
- cadastrar responsáveis;
- cadastrar locais;
- cadastrar cursos;
- cadastrar tipos de evento;
- relacionar cursos aos eventos;
- organizar a agenda dos eventos;
- organizar as etapas de produção;
- criar e acompanhar tarefas;
- acompanhar formulários;
- acompanhar transportes;
- registrar visitas;
- anexar arquivos aos eventos;
- consultar o histórico dos eventos;
- controlar usuários;
- controlar permissões de acesso;
- disponibilizar diferentes interfaces de acordo com o perfil do usuário.

---

# 3. Perfis de usuário

O sistema possui três níveis de utilização planejados:

1. GESTOR
2. COLABORADOR
3. CONSULTA

O backend possui atualmente os perfis:

```text
ADMIN
GESTOR
COLABORADOR
```

O perfil de consulta representa uma interface de visualização e deverá ser implementado de acordo com as regras definidas para esse usuário.

---

# 4. Perfil GESTOR

O gestor é o usuário responsável pelo gerenciamento dos eventos e das informações administrativas.

A interface do gestor é o foco atual do desenvolvimento do frontend.

O gestor deverá conseguir acessar e administrar as principais áreas do sistema:

- Dashboard;
- Eventos;
- Agenda;
- Tarefas;
- Formulários;
- Transportes;
- Visitas;
- Anexos;
- Histórico;
- Relatórios;
- Usuários;
- Locais;
- Tipos de evento;
- Responsáveis;
- Cursos;
- Etapas;
- Categorias de tarefas.

O gestor possui permissões superiores às do colaborador.

---

# 5. Perfil COLABORADOR

O colaborador terá uma interface própria e mais enxuta.

A ideia é mostrar somente as funcionalidades necessárias para o trabalho e acompanhamento das atividades.

Entre as áreas previstas:

- Dashboard;
- Eventos permitidos;
- Agenda;
- Tarefas;
- Formulários;
- Transportes;
- Visitas;
- Anexos.

O colaborador não deve visualizar funcionalidades administrativas exclusivas do gestor.

A interface do colaborador ainda será desenvolvida.

---

# 6. Perfil CONSULTA

O perfil de consulta será destinado a usuários que precisam apenas visualizar informações.

Esse usuário não deverá possuir ações administrativas.

Não deverá ser possível:

- criar registros;
- editar registros;
- excluir registros;
- administrar usuários;
- alterar configurações.

A interface de consulta deverá permitir visualizar informações liberadas pelo sistema, principalmente:

- eventos;
- agenda;
- detalhes dos eventos;
- informações relacionadas aos eventos.

A interface de consulta ainda será desenvolvida.

---

# 7. Controle de acesso

O controle real de autorização é responsabilidade do backend.

O frontend deve respeitar as permissões recebidas do backend.

A interface não deve simplesmente disponibilizar botões de criação, edição ou exclusão para usuários que não possuem autorização.

Mesmo que uma ação esteja escondida no frontend, a segurança deve continuar sendo garantida pelo backend.

A matriz de acesso atual considera:

```text
ADMIN
    ↓
Acesso administrativo completo

GESTOR
    ↓
Funções de gestão
+
Funções permitidas ao colaborador

COLABORADOR
    ↓
Funções destinadas ao colaborador
```

---

# 8. Arquitetura do projeto

O projeto possui duas partes principais:

```text
backend/
frontend/
```

## Backend

O backend é responsável por:

- API;
- autenticação;
- autorização;
- regras de negócio;
- comunicação com o banco;
- criação de registros;
- consulta de registros;
- atualização de registros;
- exclusão de registros;
- relacionamentos entre entidades.

## Frontend

O frontend é responsável por:

- interface gráfica;
- navegação;
- dashboards;
- formulários;
- visualização de informações;
- interação com o usuário;
- consumo da API;
- apresentação das permissões de cada perfil.

O frontend não deve duplicar as regras de negócio que já pertencem ao backend.

---

# 9. Estrutura atual do projeto

A estrutura principal atual é:

```text
projeto-elos/
│
├── backend/
│   ├── database/
│   │   └── elos.sql
│   │
│   ├── public/
│   │   └── index.php
│   │
│   ├── routes/
│   │   ├── agenda_evento_routes.php
│   │   ├── anexo_routes.php
│   │   ├── api.php
│   │   ├── auth_routes.php
│   │   ├── categoria_tarefa_routes.php
│   │   ├── curso_routes.php
│   │   ├── etapa_routes.php
│   │   ├── evento_curso_routes.php
│   │   ├── evento_routes.php
│   │   ├── eventos_dispatcher_routes.php
│   │   ├── formulario_routes.php
│   │   ├── historico_routes.php
│   │   ├── local_routes.php
│   │   ├── responsavel_routes.php
│   │   ├── tarefa_routes.php
│   │   ├── tipo_evento_routes.php
│   │   ├── transporte_routes.php
│   │   └── visita_routes.php
│   │
│   ├── src/
│   │
│   ├── storage/
│   │
│   └── .env.example
│
├── frontend/
│   ├── assets/
│   │   ├── css/
│   │   │   ├── dashboard.css
│   │   │   ├── login.css
│   │   │   └── style.css
│   │   │
│   │   ├── images/
│   │   └── js/
│   │
│   ├── pages/
│   │   └── login.php
│   │
│   └── index.php
│
├── .gitignore
└── README.md
```

---

# 10. Backend — organização

O backend está organizado em:

```text
backend/public/
backend/routes/
backend/src/
backend/database/
backend/storage/
```

## backend/public/

Contém o ponto de entrada público da API:

```text
backend/public/index.php
```

Esse arquivo inicializa a aplicação e direciona as requisições para as rotas.

---

# 11. Rotas

As rotas ficam em:

```text
backend/routes/
```

Cada arquivo possui responsabilidade relacionada a uma funcionalidade.

## auth_routes.php

Responsável pelas operações de autenticação.

Inclui o processo de login, logout e autenticação relacionada aos usuários.

---

## tipo_evento_routes.php

Responsável pelo gerenciamento dos tipos de evento.

Os tipos servem para classificar os eventos.

Exemplos conceituais:

```text
Exposição
Palestra
Oficina
Evento institucional
```

Os tipos são configuráveis.

---

## responsavel_routes.php

Responsável pelo cadastro e gerenciamento dos responsáveis pelos eventos.

Um responsável pode ser:

```text
PESSOA
SETOR
CURSO
COLETIVO
INSTITUICAO
OUTRO
```

O cadastro possui informações como:

- nome;
- tipo;
- email;
- telefone;
- observações;
- situação ativa/inativa.

---

## curso_routes.php

Responsável pelo gerenciamento dos cursos.

Os cursos podem ser relacionados aos eventos.

Um evento pode possuir mais de um curso relacionado.

---

## local_routes.php

Responsável pelo gerenciamento dos locais.

Os locais representam os espaços onde os eventos podem ocorrer.

---

## evento_routes.php

Responsável pela entidade principal do sistema: o evento.

O evento possui:

- tipo;
- responsável;
- local;
- título;
- descrição;
- prioridade;
- status;
- observações.

---

## evento_curso_routes.php

Responsável pela relação entre eventos e cursos.

A relação é muitos-para-muitos.

Isso significa:

```text
Um evento → vários cursos

Um curso → vários eventos
```

Por isso existe uma tabela intermediária chamada:

```text
evento_cursos
```

---

## agenda_evento_routes.php

Responsável pela agenda de um evento.

A agenda permite organizar:

- início da montagem;
- fim da montagem;
- abertura;
- horário;
- início da permanência;
- fim da permanência;
- início da desmontagem;
- fim da desmontagem;
- tipo de horário;
- observações.

---

## etapa_routes.php

Responsável pelas etapas do processo de organização.

As etapas iniciais são:

```text
1. Contato inicial
2. Pré-produção
3. Produção
4. Infraestrutura
5. Divulgação
6. Montagem
7. Inauguração
8. Pós-produção
9. Desmontagem
10. Devolução
```

As etapas são configuráveis.

---

## categoria_tarefa_routes.php

Responsável pelas categorias das tarefas.

As categorias permitem organizar e classificar as atividades.

Também são configuráveis.

---

## tarefa_routes.php

Responsável pelas tarefas relacionadas aos eventos.

Uma tarefa possui:

- evento;
- usuário responsável;
- etapa;
- categoria;
- título;
- descrição;
- prazo;
- prioridade;
- status;
- observações.

Status disponíveis:

```text
PENDENTE
EM_ANDAMENTO
CONCLUIDA
BLOQUEADA
CANCELADA
```

A tarefa existe para representar uma atividade que precisa ser executada.

Exemplos:

```text
Solicitar material
Confirmar responsável
Preparar divulgação
Organizar montagem
Conferir equipamentos
Agendar transporte
Enviar formulário
```

---

## formulario_routes.php

Responsável pelo gerenciamento dos formulários relacionados aos eventos.

Um formulário possui:

- evento;
- tipo;
- data prevista;
- data de envio;
- status;
- observações.

Status:

```text
PENDENTE
EM_PREPARACAO
ENVIADO
ATRASADO
CANCELADO
```

O objetivo é acompanhar o processo do formulário e evitar que algo necessário fique sem acompanhamento.

---

## transporte_routes.php

Responsável pelos transportes relacionados aos eventos.

Os tipos de transporte são:

```text
OBRAS
MATERIAIS
EQUIPAMENTOS
DEVOLUCAO
OUTRO
```

Status:

```text
NAO_SOLICITADO
SOLICITADO
AGENDADO
REALIZADO
CANCELADO
```

Um transporte possui informações como:

- origem;
- destino;
- data da solicitação;
- data do transporte;
- horário;
- status;
- observações.

---

## visita_routes.php

Responsável pelas visitas relacionadas aos eventos.

Uma visita possui:

- instituição;
- responsável;
- quantidade de pessoas;
- data;
- horário;
- status;
- observações.

Status:

```text
AGENDADA
REALIZADA
CANCELADA
```

A funcionalidade existe para controlar visitas de instituições, grupos ou outros participantes relacionados aos eventos.

---

## anexo_routes.php

Responsável pelos anexos relacionados aos eventos.

Um anexo possui informações como:

- nome;
- nome original;
- caminho;
- tipo;
- tamanho;
- evento relacionado.

A finalidade é manter os arquivos relacionados ao evento organizados dentro do sistema.

---

## historico_routes.php

Responsável pelo histórico dos eventos.

O histórico registra ações e acontecimentos relacionados ao evento.

Exemplos:

```text
Evento criado
Informação atualizada
Tarefa adicionada
Documento anexado
```

A finalidade é permitir rastreabilidade e acompanhamento do que aconteceu com o evento.

---

## eventos_dispatcher_routes.php

Responsável pelo direcionamento das rotas relacionadas aos eventos.

Como o evento é a entidade central, existem várias funcionalidades dependentes dele.

Exemplos:

```text
/api/eventos/{id}
/api/eventos/{id}/cursos
/api/eventos/{id}/agenda
/api/eventos/{id}/tarefas
/api/eventos/{id}/formularios
/api/eventos/{id}/transportes
/api/eventos/{id}/visitas
/api/eventos/{id}/anexos
/api/eventos/{id}/historico
```

O dispatcher organiza esse direcionamento.

---

# 12. Banco de dados

O ELOS utiliza MySQL.

O script principal do banco está localizado em:

```text
backend/database/elos.sql
```

O banco possui **16 tabelas principais**.

São elas:

```text
1. usuarios
2. tipos_evento
3. responsaveis
4. locais
5. cursos
6. eventos
7. evento_cursos
8. agenda_eventos
9. etapas
10. categorias_tarefa
11. tarefas
12. formularios
13. transportes
14. visitas
15. anexos
16. historico
```

---

# 13. Tabela usuarios

A tabela `usuarios` armazena os usuários que possuem acesso ao sistema.

Informações principais:

```text
id
nome
email
senha
perfil
ativo
created_at
updated_at
```

O email é único.

A senha deve ser armazenada de forma segura utilizando hash.

Perfis existentes:

```text
ADMIN
GESTOR
COLABORADOR
```

---

# 14. Tabela tipos_evento

Armazena os tipos utilizados para classificar os eventos.

Possui:

```text
id
nome
ativo
```

A tabela permite que os tipos sejam administrados sem precisar alterar o código do sistema.

---

# 15. Tabela responsaveis

Armazena os responsáveis relacionados aos eventos.

Possui:

```text
id
nome
tipo
email
telefone
observacoes
ativo
created_at
updated_at
```

O campo `tipo` permite diferenciar:

```text
PESSOA
SETOR
CURSO
COLETIVO
INSTITUICAO
OUTRO
```

---

# 16. Tabela locais

Armazena os locais onde os eventos podem acontecer.

Possui:

```text
id
nome
descricao
ativo
created_at
updated_at
```

O nome do local é único.

---

# 17. Tabela cursos

Armazena os cursos que podem participar dos eventos.

Um curso pode estar relacionado a vários eventos.

---

# 18. Tabela eventos

É a tabela central do sistema.

Possui relacionamentos com várias outras tabelas.

Informações principais:

```text
tipo_evento_id
responsavel_id
local_id
titulo
descricao
prioridade
status
observacoes
created_at
updated_at
```

Status:

```text
PLANEJAMENTO
EM_ANDAMENTO
CONCLUIDO
CANCELADO
```

Prioridade:

```text
BAIXA
MEDIA
ALTA
```

A prioridade é definida manualmente.

O sistema pode utilizar prioridade, prazo e status para apresentar alertas, mas não deve inventar ou alterar a prioridade definida pelo usuário.

---

# 19. Tabela evento_cursos

É a tabela intermediária entre `eventos` e `cursos`.

Ela permite uma relação muitos-para-muitos.

Possui:

```text
evento_id
curso_id
```

A chave primária é composta pelos dois campos.

---

# 20. Tabela agenda_eventos

Armazena as informações de agenda de um evento.

Possui:

```text
id
evento_id
montagem_inicio
montagem_fim
abertura
horario
permanencia_inicio
permanencia_fim
desmontagem_inicio
desmontagem_fim
tipo_horario
observacoes
```

A tabela permite representar as diferentes fases temporais do evento.

---

# 21. Tabela etapas

Armazena as etapas utilizadas no processo de organização.

Possui:

```text
id
nome
ordem
ativa
```

As etapas podem ser alteradas/configuradas pelo sistema.

Etapas iniciais:

```text
Contato inicial
Pré-produção
Produção
Infraestrutura
Divulgação
Montagem
Inauguração
Pós-produção
Desmontagem
Devolução
```

---

# 22. Tabela categorias_tarefa

Armazena as categorias utilizadas para classificar tarefas.

Possui:

```text
id
nome
ativa
```

A utilização de categorias facilita a organização das atividades.

---

# 23. Tabela tarefas

Armazena as atividades relacionadas aos eventos.

Possui relacionamentos com:

```text
eventos
usuarios
etapas
categorias_tarefa
```

Também possui:

```text
titulo
descricao
prazo
prioridade
status
observacoes
created_at
updated_at
```

Status:

```text
PENDENTE
EM_ANDAMENTO
CONCLUIDA
BLOQUEADA
CANCELADA
```

---

# 24. Tabela formularios

Armazena formulários relacionados aos eventos.

Possui:

```text
id
evento_id
tipo
data_previsao
data_envio
status
observacoes
created_at
updated_at
```

Status:

```text
PENDENTE
EM_PREPARACAO
ENVIADO
ATRASADO
CANCELADO
```

---

# 25. Tabela transportes

Armazena informações de transporte relacionadas aos eventos.

Possui:

```text
id
evento_id
tipo
origem
destino
data_solicitacao
data_transporte
horario
status
observacoes
created_at
updated_at
```

Tipos:

```text
OBRAS
MATERIAIS
EQUIPAMENTOS
DEVOLUCAO
OUTRO
```

Status:

```text
NAO_SOLICITADO
SOLICITADO
AGENDADO
REALIZADO
CANCELADO
```

---

# 26. Tabela visitas

Armazena visitas relacionadas aos eventos.

Possui:

```text
id
evento_id
instituicao
responsavel
quantidade_pessoas
data
horario
status
observacoes
```

Status:

```text
AGENDADA
REALIZADA
CANCELADA
```

---

# 27. Tabela anexos

Armazena informações dos arquivos relacionados aos eventos.

Possui:

```text
id
evento_id
nome
nome_original
caminho
tipo
tamanho
created_at
```

O objetivo é manter documentos e arquivos associados ao contexto do evento.

---

# 28. Tabela historico

Registra ações relacionadas aos eventos.

Possui:

```text
id
evento_id
usuario_id
acao
descricao
created_at
```

O histórico existe para permitir rastreabilidade.

---

# 29. Relacionamentos principais

O modelo pode ser entendido desta forma:

```text
usuarios
│
├── tarefas
│
└── historico


tipos_evento
│
└── eventos


responsaveis
│
└── eventos


locais
│
└── eventos


eventos
│
├── evento_cursos ─── cursos
│
├── agenda_eventos
│
├── tarefas
│
├── formularios
│
├── transportes
│
├── visitas
│
├── anexos
│
└── historico
```

O evento é o ponto central que conecta essas informações.

---

# 30. Dashboard

O dashboard é a tela inicial de cada perfil.

No caso do gestor, sua função é apresentar uma visão geral do sistema.

O dashboard deve facilitar a identificação de:

- quantidade de eventos;
- eventos em andamento;
- próximos eventos;
- tarefas;
- tarefas pendentes;
- alertas;
- informações importantes;
- movimentações recentes.

O dashboard não substitui as páginas específicas.

Ele funciona como uma visão geral e como ponto inicial de navegação.

---

# 31. Eventos

A área de eventos é a principal área do sistema.

Ela deve permitir ao gestor:

- visualizar eventos;
- criar eventos;
- editar eventos;
- consultar detalhes;
- acompanhar status;
- visualizar prioridade;
- visualizar responsável;
- visualizar local;
- visualizar cursos relacionados;
- acessar agenda;
- acessar tarefas;
- acessar formulários;
- acessar transportes;
- acessar visitas;
- acessar anexos;
- acessar histórico.

A tela de detalhes do evento deve funcionar como um espaço central para todas as informações relacionadas àquele evento.

---

# 32. Agenda

A agenda organiza os compromissos e períodos relacionados aos eventos.

Ela deve permitir uma visualização clara das datas.

Deve ajudar a identificar:

- eventos próximos;
- montagem;
- abertura;
- permanência;
- desmontagem;
- outros períodos importantes.

---

# 33. Tarefas

As tarefas representam o trabalho necessário para organizar um evento.

Cada tarefa deve deixar claro:

- o que precisa ser feito;
- para qual evento;
- quem é o responsável;
- qual a etapa;
- qual a categoria;
- qual o prazo;
- qual a prioridade;
- qual o status.

A área de tarefas é especialmente importante para o perfil colaborador.

---

# 34. Formulários

A área de formulários existe para acompanhar documentos e formulários necessários para os eventos.

O objetivo é permitir saber:

- qual formulário existe;
- a qual evento pertence;
- qual a situação;
- quando deveria ser enviado;
- quando foi enviado.

---

# 35. Transportes

A área de transportes existe para controlar movimentações relacionadas aos eventos.

Pode envolver:

- obras;
- materiais;
- equipamentos;
- devoluções.

O objetivo é acompanhar o processo desde a solicitação até a realização.

---

# 36. Visitas

A área de visitas existe para registrar grupos, instituições e pessoas que visitarão eventos.

Permite controlar:

- instituição;
- responsável;
- quantidade de pessoas;
- data;
- horário;
- situação.

---

# 37. Anexos

A área de anexos existe para manter arquivos relacionados aos eventos.

A ideia é que documentos importantes não fiquem separados do evento ao qual pertencem.

---

# 38. Histórico

O histórico registra acontecimentos relacionados ao evento.

Ele permite entender a evolução do evento e identificar ações realizadas ao longo do processo.

---

# 39. Identidade visual

A identidade visual do ELOS segue o conceito:

**Contemporaneidade + memória + movimento.**

O sistema deve ter uma aparência moderna, institucional e acolhedora.

A interface deve transmitir organização sem parecer excessivamente tecnológica.

---

# 40. Paleta visual

As principais cores são:

- creme / off-white;
- azul-marinho;
- azul claro;
- turquesa;
- amarelo;
- bege / areia.

O vermelho deve ser utilizado principalmente para:

- alertas;
- erros;
- situações críticas;
- informações que precisam de atenção.
 43. Frontend atual

O frontend está localizado em:

```text
frontend/
```

A página principal atual é:

```text
frontend/index.php
```

A página de login é:

```text
frontend/pages/login.php
```

Os estilos estão em:

```text
frontend/assets/css/
```

Arquivos atuais:

```text
dashboard.css
login.css
style.css
```

O desenvolvimento atual está concentrado na interface do gestor.

---

# 41. Login

A página de login atual é:

```text
frontend/pages/login.php
```

O login utiliza o backend através da API.

Endpoint:

```text
POST /api/login
```

Após a autenticação, o backend cria uma sessão PHP.

As informações básicas do usuário autenticado incluem:

```text
id
nome
email
perfil
```

---

# 42. Backend e frontend

O frontend deve consumir a API existente.

Antes de criar uma nova funcionalidade no frontend:

1. verificar se existe endpoint no backend;
2. verificar qual método HTTP deve ser utilizado;
3. verificar os dados necessários;
4. verificar o formato da resposta;
5. verificar as permissões necessárias.

Não criar uma implementação paralela no frontend quando a funcionalidade já existe no backend.

---

# 43. Configuração do ambiente

## Requisitos

O projeto utiliza:

- PHP 8 ou superior;
- MySQL;
- Git;
- servidor web ou servidor embutido do PHP.

O desenvolvimento original foi realizado em ambiente Linux/Ubuntu.

---

# 44. Banco de dados local

O script do banco está em:

```text
backend/database/elos.sql
```

Para configurar o ambiente:

1. criar um banco MySQL;
2. importar `backend/database/elos.sql`;
3. configurar as credenciais do banco;
4. criar o arquivo `backend/.env`.

O projeto possui:

```text
backend/.env.example
```

Esse arquivo serve como modelo.

---

# 45. Arquivo .env

Cada desenvolvedor deve possuir seu próprio:

```text
backend/.env
```

Exemplo:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=elos_db
DB_USER=elos_user
DB_PASSWORD=SUA_SENHA

A API ficará disponível em:

```text
http://127.0.0.1:8000
```

---

# 46. Executando o frontend

O frontend deve ser servido pelo servidor web local.

Dependendo da configuração do ambiente:

```text
http://localhost/frontend/
```

ou:

```text
http://localhost/frontend/index.php
```

---

# 47. Git

O projeto utiliza Git para controle de versão.

Depois de clonar:

```bash
git clone URL_DO_REPOSITORIO
cd projeto-elos
```

Verifique:

```bash
git status
```

---

# 48. Branches

Recomenda-se trabalhar em branches separadas.

Exemplo:

```bash
git checkout -b frontend/dashboard-gestor
```

Outros exemplos:

```text
frontend/login
frontend/eventos
frontend/agenda
frontend/tarefas
frontend/colaborador
frontend/consulta
```

---

# 49. Commits

Os commits devem explicar claramente o que foi alterado.

Exemplos:

```bash
git commit -m "feat: cria dashboard do gestor"
```

```bash
git commit -m "fix: corrige responsividade do login"
```

```bash
git commit -m "style: ajusta identidade visual dos eventos"
```

```bash
git commit -m "feat: cria interface do colaborador"
```

---

# 50. Arquivos que NÃO devem ir para o GitHub

Nunca enviar:

```text
.env
cookies.txt
cookies-gestor.txt
cookies-tipos.txt
```

Esses arquivos podem conter:

- senhas;
- credenciais;
- sessões autenticadas;
- informações específicas do ambiente local.

O `.gitignore` deve impedir que esses arquivos sejam versionados.

---

# 51. .gitignore

O projeto utiliza `.gitignore` para impedir o envio de arquivos locais ou sensíveis.

Entre os arquivos ignorados:

```text
.env
cookies.txt
cookies-*.txt
vendor/
logs/
arquivos temporários
arquivos de IDE
```

O arquivo:

```text
backend/.env.example
```

pode ser enviado para o GitHub porque não deve conter credenciais reais.

---

# 52. Desenvolvimento em equipe

O projeto está sendo preparado para que diferentes pessoas possam trabalhar simultaneamente.

O foco atual do trabalho em equipe é o frontend.

O desenvolvedor que estiver trabalhando na interface deve priorizar:

```text
frontend/
```

O backend existente deve ser utilizado como base para as integrações.

Antes de modificar o backend, verificar se a API existente já atende à necessidade.

---

# 53. Ordem de desenvolvimento

O desenvolvimento do frontend seguirá esta ordem:

```text
1. Gestor
2. Colaborador
3. Consulta
4. Integração final
5. Testes
6. Limpeza e organização final
```

---

# 54. Situação atual do projeto

## Backend

O backend já possui estrutura para:

- autenticação;
- autorização;
- usuários;
- tipos de evento;
- responsáveis;
- cursos;
- locais;
- eventos;
- relacionamento entre eventos e cursos;
- agenda;
- etapas;
- categorias de tarefas;
- tarefas;
- formulários;
- transportes;
- visitas;
- anexos;
- histórico.

---

## Frontend

O frontend está em desenvolvimento.

A interface atual em desenvolvimento é a do:

```text
GESTOR
```

Já existe estrutura para:

- login;
- dashboard;
- identidade visual;
- navegação.

Ainda precisam ser desenvolvidas ou finalizadas as demais áreas do gestor e, posteriormente, as interfaces do colaborador e do usuário de consulta.

---

# 55. Próximas etapas do frontend

## Gestor

Finalizar:

- Dashboard;
- Eventos;
- Detalhes do evento;
- Agenda;
- Tarefas;
- Formulários;
- Transportes;
- Visitas;
- Anexos;
- Histórico;
- Relatórios;
- Usuários;
- Locais;
- Tipos de evento;
- Responsáveis;
- Cursos;
- Etapas;
- Categorias de tarefas.

---

## Colaborador

Criar uma interface específica para:

- Dashboard;
- Eventos;
- Agenda;
- Tarefas;
- Formulários;
- Transportes;
- Visitas;
- Anexos.

A interface deve respeitar as permissões do colaborador.

---

## Consulta

Criar uma interface específica para:

- Dashboard;
- Eventos;
- Agenda;
- Detalhes do evento;
- informações permitidas para visualização.

Sem funções administrativas.

---

# 56. Regras importantes para quem continuar o projeto

Antes de fazer uma alteração importante:

1. Ler este README.
2. Entender a arquitetura.
3. Verificar se a funcionalidade já existe no backend.
4. Não criar endpoints duplicados.
5. Não criar regras de negócio desnecessárias no frontend.
6. Não alterar o banco sem necessidade.
7. Não remover arquivos sem verificar referências.
8. Manter a identidade visual.
9. Manter a responsividade.
10. Testar a alteração.
11. Não enviar credenciais para o Git.
12. Não enviar cookies para o Git.
13. Trabalhar preferencialmente em uma branch.
14. Fazer commits claros.

---

# 57. Princípios do projeto

O ELOS não tem como objetivo ser complexo apenas para parecer sofisticado.

O sistema deve priorizar:

```text
CLAREZA
   ↓
ORGANIZAÇÃO
   ↓
FUNCIONALIDADE
   ↓
EXPERIÊNCIA DE USO
```

A interface deve ser fácil de entender para quem administra os eventos e para quem participa da execução das atividades.

---

# 58. Resumo rápido para novos desenvolvedores

Se você acabou de clonar o projeto:

```text
1. Leia o README
        ↓
2. Configure o MySQL
        ↓
3. Importe backend/database/elos.sql
        ↓
4. Crie backend/.env
        ↓
5. Inicie o backend
        ↓
6. Inicie/acesse o frontend
        ↓
7. Teste o login
        ↓
8. Entenda o dashboard do gestor
        ↓
9. Consulte os endpoints existentes
        ↓
10. Crie uma branch
        ↓
11. Desenvolva
        ↓
12. Teste
        ↓
13. Faça commit
        ↓
14. Envie a branch
```

---

# 59. Importante

O projeto possui uma separação clara entre:

```text
BACKEND
```

e

```text
FRONTEND
```

O backend deve ser tratado como a camada responsável pelos dados, regras e segurança.

O frontend deve ser tratado como a camada responsável pela experiência e interação do usuário.

O desenvolvimento atual deve priorizar o frontend, especialmente a interface do gestor.

As interfaces do colaborador e do usuário de consulta serão desenvolvidas posteriormente.

---

# ELOS
**Eventos que conectam pessoas, ideias e oportunidades.**