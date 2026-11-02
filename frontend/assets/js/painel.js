/*
 * ELOS — Painel ao vivo.
 *
 * Desenha o painel a partir do JSON que a página entrega e busca de
 * novo a cada 30 segundos (mesmo endereço com ?dados=1). Nada aqui
 * altera dados: o painel só mostra.
 *
 *   - "Em foco": uma exposição ou projeto por vez (capa, andamento do
 *     checklist e próximos passos), trocando a cada 12 segundos. O
 *     botão de pausa no topo segura o que está na tela; as bolinhas
 *     escolhem qual mostrar.
 *   - "Agora": atrasados, o que vence hoje e quantos itens vencem em
 *     cada um dos próximos dias.
 *   - avisos rápidos quando entra um item novo ou alguém conclui um;
 *   - uma faixa fina no rodapé conta os itens de prioridade alta sem
 *     prazo e mostra um exemplo por vez.
 */
(function () {
    'use strict';

    const ATUALIZAR_A_CADA = 30 * 1000;
    const TENTAR_DE_NOVO_EM = 15 * 1000;
    const RECARREGAR_A_PAGINA_A_CADA = 6 * 60 * 60 * 1000; // pega versões novas do ELOS
    const TROCAR_FOCO_A_CADA = 12 * 1000;
    const COLUNAS = ['atrasados', 'prioridade', 'pendentes'];
    const MAXIMO_NO_BLOCO = 4;      // itens visíveis em Atrasados e em Hoje
    const DIAS_NA_BARRA = 5;        // dias em "Próximos dias"
    const TROCAR_EXEMPLO_A_CADA = 8 * 1000; // faixa dos itens sem prazo

    const FUSO = 'America/Sao_Paulo';
    const formatoHora = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: FUSO });
    const formatoData = new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: FUSO });
    const formatoDia = new Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit', timeZone: 'UTC' });

    const $ = function (id) { return document.getElementById(id); };

    let dados = null;
    let ultimaAtualizacao = null;
    let idsAbertos = null;   // para perceber itens novos
    let idsFeitos = null;    // para perceber itens concluídos
    let focoAtual = 0;       // posição em dados.em_foco
    let focoId = null;       // evento na tela (mantém o mesmo depois de atualizar)
    let pausado = false;
    let assinaturaFoco = null;

    // -------------------------------------------------------------
    // Utilidades
    // -------------------------------------------------------------

    function el(tag, classe, texto) {
        const no = document.createElement(tag);
        if (classe) { no.className = classe; }
        if (texto !== undefined && texto !== null) { no.textContent = texto; }
        return no;
    }

    /** "2026-10-27" -> número de dias desde 1970 (sem fuso). */
    function diaNumero(data) {
        const p = String(data).split('-');
        return Math.round(Date.UTC(+p[0], +p[1] - 1, +p[2]) / 86400000);
    }

    function dataCurta(data) {
        const p = String(data).split('-');
        return formatoDia.format(new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]))).replace('.', '');
    }

    function plural(n, um, varios) {
        return n + ' ' + (n === 1 ? um : varios);
    }

    /** Texto e classe do prazo: atrasado / hoje / em-breve / sem-prazo. */
    function situacaoDoPrazo(tarefa) {
        if (!tarefa.prazo) {
            return { classe: 'sem-prazo', texto: 'Sem prazo' };
        }

        const dias = diaNumero(tarefa.prazo) - diaNumero(dados.hoje);
        const hora = tarefa.horario ? ' · ' + tarefa.horario : '';

        if (dias < 0) {
            return { classe: 'atrasado', texto: 'Atrasado há ' + plural(-dias, 'dia', 'dias') };
        }
        if (dias === 0) {
            return { classe: 'hoje', texto: 'Hoje' + hora };
        }
        if (dias === 1) {
            return { classe: 'em-breve', texto: 'Amanhã' + hora };
        }
        return { classe: 'em-breve', texto: dataCurta(tarefa.prazo) + ' · em ' + dias + ' dias' + hora };
    }

    /** Cor do tipo de evento (Exposição, Cine Clube...), vinda da página. */
    function corDoTipo(evento) {
        return evento && /^#[0-9a-f]{6}$/i.test(evento.tipo_cor || '') ? evento.tipo_cor : '#35a7a7';
    }

    function todasAbertas() {
        const c = dados.colunas || {};
        return COLUNAS.reduce(function (lista, nome) { return lista.concat(c[nome] || []); }, []);
    }

    /** Abertas incluindo as sem prazo (que ficam fora das colunas). */
    function todasAbertasComSemPrazo() {
        return todasAbertas().concat(dados.sem_prazo || []);
    }

    // -------------------------------------------------------------
    // Relógio
    // -------------------------------------------------------------

    function atualizarRelogio() {
        const agora = new Date();
        $('painel-hora').textContent = formatoHora.format(agora);
        const data = formatoData.format(agora);
        $('painel-data').textContent = data.charAt(0).toUpperCase() + data.slice(1);

        const situacao = $('painel-situacao');
        if (ultimaAtualizacao && !situacao.classList.contains('sem-conexao')) {
            const minutos = Math.floor((Date.now() - ultimaAtualizacao) / 60000);
            const estado = pausado ? 'Pausado' : 'Ao vivo';
            $('painel-situacao-texto').textContent = minutos < 1
                ? estado
                : estado + ' · atualizado há ' + plural(minutos, 'minuto', 'minutos');
            situacao.classList.toggle('pausado', pausado);
        }
    }

    // -------------------------------------------------------------
    // Em foco
    // -------------------------------------------------------------

    /** Prazo curto: "há 2 dias", "hoje 14:00", "amanhã", "qua 04/11". */
    function prazoCurto(tarefa) {
        if (!tarefa.prazo) {
            return 'sem prazo';
        }
        const dias = diaNumero(tarefa.prazo) - diaNumero(dados.hoje);
        const hora = tarefa.horario ? ' ' + tarefa.horario : '';
        if (dias < 0) { return 'há ' + plural(-dias, 'dia', 'dias'); }
        if (dias === 0) { return 'hoje' + hora; }
        if (dias === 1) { return 'amanhã' + hora; }
        return dataCurta(tarefa.prazo);
    }

    function dataDiaMes(data) {
        const p = String(data).split('-');
        return p[2] + '/' + p[1];
    }

    /** "Abre em 3 dias · 04/11 → 20/11" ou "Em cartaz até 20/11". */
    function quando(evento) {
        if (!evento.data) { return ''; }
        const dias = diaNumero(evento.data) - diaNumero(dados.hoje);
        let texto;
        if (dias > 1) { texto = 'Abre em ' + dias + ' dias'; }
        else if (dias === 1) { texto = 'Abre amanhã'; }
        else if (dias === 0) { texto = 'Abre hoje'; }
        else { texto = 'Em cartaz'; }

        if (evento.ate && evento.ate !== evento.data) {
            texto += dias > 0 ? ' · ' + dataDiaMes(evento.data) + ' → ' + dataDiaMes(evento.ate) : ' até ' + dataDiaMes(evento.ate);
        } else if (evento.horario) {
            texto += ' · ' + evento.horario.replace(':00', 'h').replace(':', 'h');
        }
        if (evento.local) { texto += ' · ' + evento.local; }
        return texto;
    }

    function corDoEvento(evento) {
        return evento && /^#[0-9a-f]{6}$/i.test(evento.cor || '') ? evento.cor : '#35a7a7';
    }

    function eventosEmFoco() {
        return (dados && dados.em_foco) || [];
    }

    function desenharPontos() {
        const caixa = $('painel-foco-pontos');
        const eventos = eventosEmFoco();
        caixa.replaceChildren();
        if (eventos.length <= 1) { return; }
        eventos.forEach(function (evento, i) {
            const ponto = el('button');
            ponto.type = 'button';
            ponto.setAttribute('aria-label', 'Mostrar ' + evento.titulo);
            ponto.setAttribute('aria-current', i === focoAtual ? 'true' : 'false');
            ponto.addEventListener('click', function () { mostrarFoco(i); });
            caixa.append(ponto);
        });
    }

    function desenharFoco() {
        const eventos = eventosEmFoco();
        const caixa = $('painel-foco');
        const conteudo = $('painel-foco-conteudo');

        if (eventos.length === 0) {
            focoId = null;
            assinaturaFoco = null;
            caixa.style.removeProperty('--cor-foco');
            conteudo.replaceChildren(el('p', 'painel-foco-vazio', 'Nenhuma exposição ou projeto nos próximos 60 dias.'));
            desenharPontos();
            return;
        }

        // Depois de atualizar os dados, continua no mesmo evento.
        const mesmo = eventos.findIndex(function (e) { return e.id === focoId; });
        focoAtual = mesmo >= 0 ? mesmo : focoAtual % eventos.length;
        const evento = eventos[focoAtual];

        const assinatura = JSON.stringify(evento) + dados.hoje;
        desenharPontos();
        if (assinatura === assinaturaFoco) { return; }
        assinaturaFoco = assinatura;
        focoId = evento.id;

        caixa.style.setProperty('--cor-foco', corDoEvento(evento));

        const cabeca = el(evento.link ? 'a' : 'div', 'painel-foco-cabeca');
        if (evento.link) { cabeca.href = evento.link; }

        const moldura = el('span', 'painel-foco-moldura');
        if (evento.capa) {
            const img = el('img');
            img.src = evento.capa;
            img.alt = 'Capa de ' + evento.titulo;
            img.decoding = 'async';
            moldura.append(img);
        } else {
            moldura.append(el('span', 'painel-foco-inicial', evento.titulo.trim().charAt(0).toUpperCase()));
        }

        const texto = el('div', 'painel-foco-texto');
        if (evento.tipo) {
            const tipo = el('span', 'painel-foco-tipo', evento.tipo);
            tipo.style.setProperty('--cor-tipo', corDoTipo(evento));
            texto.append(tipo);
        }
        texto.append(el('h3', 'painel-foco-nome', evento.titulo));
        const q = quando(evento);
        if (q) { texto.append(el('span', 'painel-foco-quando', q)); }

        if (evento.total > 0) {
            const pct = Math.round((evento.feitas / evento.total) * 100);
            const andamento = el('div', 'painel-andamento');
            const barra = el('span', 'painel-andamento-barra');
            const cheio = el('span');
            cheio.style.width = pct + '%';
            barra.append(cheio);
            andamento.append(el('strong', null, pct + '%'), barra, el('small', null, evento.feitas + ' de ' + evento.total + ' prontos'));
            texto.append(andamento);
        }

        cabeca.append(moldura, texto);

        const passos = el('div', 'painel-passos');
        passos.append(el('h3', null, 'Próximos passos'));
        const lista = el('ul');
        (evento.passos || []).forEach(function (t) {
            const item = el('li', 'painel-passo situacao-' + situacaoDoPrazo(t).classe);
            item.append(
                el('span', 'painel-passo-marca'),
                el('span', 'painel-passo-titulo', t.titulo),
                el('span', 'painel-passo-quem', t.responsavel || ''),
                el('span', 'painel-passo-prazo', prazoCurto(t))
            );
            lista.append(item);
        });
        if (lista.children.length === 0) {
            lista.append(el('li', 'painel-bloco-vazio', '✓ Checklist em dia'));
        }
        passos.append(lista);

        // Um nó novo a cada troca: a animação de entrada roda de novo.
        const novo = el('div', 'painel-foco-conteudo');
        novo.id = 'painel-foco-conteudo';
        novo.append(cabeca, passos);
        conteudo.replaceWith(novo);
    }

    function mostrarFoco(i) {
        const eventos = eventosEmFoco();
        if (eventos.length === 0) { return; }
        focoAtual = (i + eventos.length) % eventos.length;
        focoId = eventos[focoAtual].id;
        desenharFoco();
    }

    function proximoFoco() {
        if (pausado || eventosEmFoco().length <= 1) { return; }
        mostrarFoco(focoAtual + 1);
    }

    function alternarPausa() {
        pausado = !pausado;
        const botao = $('painel-pausa');
        const rotulo = pausado ? 'Retomar a troca do Em foco' : 'Pausar a troca do Em foco';
        botao.setAttribute('aria-pressed', pausado ? 'true' : 'false');
        botao.setAttribute('aria-label', rotulo);
        botao.title = rotulo;
        botao.querySelector('svg').innerHTML = pausado
            ? '<path d="M7 4.5v15l12-7.5z"/>'
            : '<path d="M8 5v14"/><path d="M16 5v14"/>';
        atualizarRelogio();
    }

    // -------------------------------------------------------------
    // Agora: atrasados, hoje, próximos dias
    // -------------------------------------------------------------

    function desenharBloco(nome, tarefas, lado) {
        $('total-' + nome).textContent = String(tarefas.length);
        const lista = $('lista-' + nome);
        lista.replaceChildren();

        if (tarefas.length === 0) {
            lista.append(el('li', 'painel-bloco-vazio', '✓ ' + lista.dataset.vazio));
            return;
        }

        tarefas.slice(0, MAXIMO_NO_BLOCO).forEach(function (t) {
            const item = el('li');
            const texto = el('span', 'painel-bloco-item-texto');
            texto.append(el('span', null, t.titulo), el('small', null, t.evento.titulo));
            item.append(texto, el('span', 'painel-bloco-item-lado', lado(t)));
            lista.append(item);
        });

        if (tarefas.length > MAXIMO_NO_BLOCO) {
            lista.append(el('li', 'painel-bloco-mais', '+ ' + plural(tarefas.length - MAXIMO_NO_BLOCO, 'outro', 'outros')));
        }
    }

    function primeiroNome(nome) {
        return nome ? String(nome).trim().split(/\s+/)[0] : '';
    }

    function desenharDias() {
        const contagem = {};
        todasAbertas().forEach(function (t) {
            if (!t.prazo) { return; }
            const dias = diaNumero(t.prazo) - diaNumero(dados.hoje);
            if (dias >= 1) { contagem[dias] = (contagem[dias] || 0) + 1; }
        });

        const linhas = [];
        for (let d = 1; linhas.length < DIAS_NA_BARRA && d <= 30; d++) {
            if (contagem[d]) { linhas.push({ d: d, n: contagem[d] }); }
        }
        const maior = linhas.reduce(function (m, l) { return Math.max(m, l.n); }, 1);

        const lista = $('lista-dias');
        lista.replaceChildren();
        if (linhas.length === 0) {
            lista.append(el('li', 'painel-bloco-vazio', 'Nada vencendo nos próximos dias'));
            return;
        }

        const base = diaNumero(dados.hoje);
        linhas.forEach(function (l) {
            const iso = new Date((base + l.d) * 86400000).toISOString().slice(0, 10);
            const item = el('li');
            const barra = el('span', 'painel-dias-barra');
            const cheio = el('span');
            cheio.style.width = Math.max(8, Math.round((l.n / maior) * 100)) + '%';
            barra.append(cheio);
            item.append(
                el('span', 'painel-dias-dia', l.d === 1 ? 'amanhã' : dataCurta(iso).split(/[ ,]/)[0] + ' ' + iso.slice(8, 10)),
                barra,
                el('span', 'painel-dias-total', plural(l.n, 'item', 'itens'))
            );
            lista.append(item);
        });
    }

    function desenhar() {
        const colunas = dados.colunas || {};
        const atrasados = colunas.atrasados || [];
        const hoje = todasAbertas().filter(function (t) { return t.prazo === dados.hoje; });

        desenharFoco();
        desenharBloco('atrasados', atrasados, prazoCurto);
        desenharBloco('hoje', hoje, function (t) { return t.horario || primeiroNome(t.responsavel); });
        desenharDias();
        desenharLembrete();

        document.title = (atrasados.length > 0 ? '(' + atrasados.length + ') ' : '') + 'Painel ao vivo | ELOS';
    }

    // -------------------------------------------------------------
    // Avisos (item novo / concluído)
    // -------------------------------------------------------------

    function avisar(tipo, titulo, texto, cor) {
        const aviso = el('div', 'painel-aviso painel-aviso-' + tipo);
        if (cor) { aviso.style.setProperty('--cor-tipo', cor); }
        aviso.append(
            el('span', 'painel-aviso-icone', tipo === 'feito' ? '✓' : '+'),
            el('strong', null, titulo),
            el('span', null, texto)
        );
        $('painel-avisos').append(aviso);

        setTimeout(function () { aviso.classList.add('saindo'); }, 9000);
        setTimeout(function () { aviso.remove(); }, 9800);
    }

    function perceberMudancas() {
        const abertas = todasAbertasComSemPrazo();
        const feitas = dados.concluidas_hoje || [];
        const novosAbertos = new Set(abertas.map(function (t) { return t.id; }));
        const novosFeitos = new Set(feitas.map(function (t) { return t.id; }));

        // Na primeira carga só guarda: não há o que comparar.
        if (idsAbertos !== null) {
            feitas.forEach(function (t) {
                if (!idsFeitos.has(t.id)) {
                    avisar('feito', 'Concluído: ' + t.titulo, [t.responsavel, t.evento.titulo].filter(Boolean).join(' · '), corDoTipo(t.evento));
                }
            });
            abertas.forEach(function (t) {
                if (!idsAbertos.has(t.id) && !idsFeitos.has(t.id)) {
                    avisar('novo', 'Novo: ' + t.titulo, t.evento.titulo + ' · ' + situacaoDoPrazo(t).texto, corDoTipo(t.evento));
                }
            });
        }

        idsAbertos = novosAbertos;
        idsFeitos = novosFeitos;
    }

    // -------------------------------------------------------------
    // Faixa dos itens sem prazo
    // -------------------------------------------------------------

    let exemploAtual = 0;

    /** Conta os itens de prioridade alta sem prazo e mostra um exemplo. */
    function desenharLembrete() {
        const itens = (dados && dados.sem_prazo) || [];
        const faixa = $('painel-lembrete');

        faixa.hidden = itens.length === 0;
        if (itens.length === 0) { return; }

        $('painel-lembrete-titulo').textContent = itens.length === 1
            ? '1 item de prioridade alta sem prazo'
            : itens.length + ' itens de prioridade alta sem prazo';

        const t = itens[exemploAtual % itens.length];
        const exemplo = $('painel-lembrete-exemplo');
        exemplo.style.setProperty('--cor-tipo', corDoTipo(t.evento));
        exemplo.replaceChildren(el('b', null, t.titulo), document.createTextNode(' · ' + t.evento.titulo));
        exemplo.classList.remove('trocando');
        void exemplo.offsetWidth; // reinicia a animação de entrada
        exemplo.classList.add('trocando');
    }

    function proximoExemplo() {
        exemploAtual++;
        desenharLembrete();
    }

    // -------------------------------------------------------------
    // Ciclo
    // -------------------------------------------------------------

    function aplicar(novos) {
        dados = novos;
        perceberMudancas();
        ultimaAtualizacao = Date.now();
        $('painel-situacao').classList.remove('sem-conexao');
        desenhar();
        atualizarRelogio();
    }

    function semConexao() {
        $('painel-situacao').classList.add('sem-conexao');
        $('painel-situacao-texto').textContent = 'Sem conexão · tentando de novo';
    }

    function bloquear() {
        $('painel-bloqueio').hidden = false;
    }

    function buscar() {
        fetch(location.pathname + '?dados=1', { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' })
            .then(function (resposta) {
                if (resposta.status === 403) {
                    bloquear();
                    return null;
                }
                if (resposta.redirected || !resposta.ok) {
                    throw new Error('HTTP ' + resposta.status);
                }
                return resposta.json();
            })
            .then(function (novos) {
                if (novos === null) { return; }
                if (!novos || !novos.colunas) { throw new Error('resposta inesperada'); }
                aplicar(novos);
                setTimeout(buscar, ATUALIZAR_A_CADA);
            })
            .catch(function () {
                semConexao();
                setTimeout(buscar, TENTAR_DE_NOVO_EM);
            });
    }

    // -------------------------------------------------------------
    // Monitor: tela cheia, tela sempre acesa, cursor escondido
    // -------------------------------------------------------------

    let travaDaTela = null;

    function manterTelaAcesa() {
        if (!('wakeLock' in navigator) || document.visibilityState !== 'visible') { return; }
        navigator.wakeLock.request('screen').then(function (trava) {
            travaDaTela = trava;
            trava.addEventListener('release', function () { travaDaTela = null; });
        }).catch(function () { /* o navegador não deixou; segue normal */ });
    }

    function prepararFerramentas() {
        const botaoTelaCheia = $('painel-tela-cheia');
        botaoTelaCheia.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(function () {});
            }
        });
        document.addEventListener('fullscreenchange', function () {
            botaoTelaCheia.textContent = document.fullscreenElement ? 'Sair da tela cheia' : 'Tela cheia';
        });

        // Cursor e botões somem depois de 4 s sem mexer o mouse.
        let parado = null;
        const acordar = function () {
            document.body.classList.remove('ocioso');
            clearTimeout(parado);
            parado = setTimeout(function () { document.body.classList.add('ocioso'); }, 4000);
        };
        ['mousemove', 'mousedown', 'keydown', 'touchstart'].forEach(function (evento) {
            document.addEventListener(evento, acordar, { passive: true });
        });
        acordar();

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible' && travaDaTela === null) { manterTelaAcesa(); }
        });
        manterTelaAcesa();

        $('painel-pausa').addEventListener('click', alternarPausa);

        // Janela do link do monitor (gestores).
        document.querySelectorAll('[data-abrir]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                const janela = $(botao.dataset.abrir);
                if (janela && janela.showModal) { janela.showModal(); }
            });
        });
        document.querySelectorAll('dialog[data-abrir-ao-carregar]').forEach(function (janela) {
            if (janela.showModal) { janela.showModal(); }
            // Tira o ?link=1 do endereço (recarregar não reabre a janela).
            history.replaceState(null, '', location.pathname);
        });
        document.querySelectorAll('[data-copiar]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                const campo = $(botao.dataset.copiar);
                campo.select();
                const pronto = function () { botao.textContent = 'Copiado ✓'; };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(campo.value).then(pronto, function () { document.execCommand('copy'); pronto(); });
                } else {
                    document.execCommand('copy');
                    pronto();
                }
            });
        });
    }

    // -------------------------------------------------------------
    // Início
    // -------------------------------------------------------------

    function iniciar() {
        prepararFerramentas();
        atualizarRelogio();
        setInterval(atualizarRelogio, 1000);

        let inicial = null;
        try {
            inicial = JSON.parse($('painel-dados-iniciais').textContent);
        } catch (e) {
            inicial = null;
        }

        if (inicial && inicial.status === 403) {
            bloquear();
            return;
        }

        if (inicial && inicial.status === 200 && inicial.dados && inicial.dados.colunas) {
            aplicar(inicial.dados);
            setTimeout(buscar, ATUALIZAR_A_CADA);
        } else {
            semConexao();
            setTimeout(buscar, 2000);
        }

        setInterval(proximoExemplo, TROCAR_EXEMPLO_A_CADA);

        setInterval(proximoFoco, TROCAR_FOCO_A_CADA);
        setTimeout(function () { location.reload(); }, RECARREGAR_A_PAGINA_A_CADA);
    }

    iniciar();
})();
