# Segurança

O que o ELOS já faz para se proteger.

[← Voltar para o README](../README.md)

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
