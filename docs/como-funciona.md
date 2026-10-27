# Como o ELOS funciona

O que o sistema faz, as regras que ele segue e quem pode fazer o quê.

[← Voltar para o README](../README.md)

## Tudo gira em torno da exposição

Cada informação pertence a uma exposição, e as outras telas são
montadas automaticamente a partir dela:

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
- O **Início** soma todas as exposições em andamento e mostra a exposição
  que está sendo acompanhada: o andamento de cada fase, as próximas
  atividades e o que está pendente.
- As **fases** saem da categoria de cada item do checklist (Curadoria,
  Montagem, Divulgação…), agrupadas em cinco etapas com cores próprias.
- O **Calendário** junta as datas das exposições e dos itens do checklist.
- O **Relatório** gera uma versão de cada exposição pronta para imprimir.

---

## Regras de negócio

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

## Perfis e equipe

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
