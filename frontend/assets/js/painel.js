/*
 * ELOS — Painel ao vivo.
 *
 * Desenha as três colunas (atrasados, prioridade alta, pendentes) a
 * partir do JSON que a página entrega e busca de novo a cada 30
 * segundos (mesmo endereço com ?dados=1). Nada aqui altera dados: o
 * painel só mostra.
 *
 * O que se mexe na tela:
 *   - coluna que não cabe na tela vira de página a cada 12 segundos
 *     (os itens ficam parados para dar tempo de ler);
 *   - atrasados pulsam devagar (uma vez a cada 2,4 s) até alguém
 *     concluir — devagar de propósito, para não cansar quem passa o
 *     dia perto do monitor;
 *   - avisos rápidos quando entra um item novo ou alguém conclui um;
 *   - uma faixa fina no rodapé conta os itens de prioridade alta sem
 *     prazo (eles ficam fora das colunas) e mostra um exemplo por vez.
 */
(function () {
    'use strict';

    const ATUALIZAR_A_CADA = 30 * 1000;
    const TENTAR_DE_NOVO_EM = 15 * 1000;
    const RECARREGAR_A_PAGINA_A_CADA = 6 * 60 * 60 * 1000; // pega versões novas do ELOS
    const VIRAR_PAGINA_A_CADA = 12 * 1000;
    const COLUNAS = ['atrasados', 'prioridade', 'pendentes'];
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
    const assinaturas = {};  // coluna -> conteúdo desenhado (evita reiniciar a rolagem)

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
            $('painel-situacao-texto').textContent = minutos < 1
                ? 'Ao vivo'
                : 'Ao vivo · atualizado há ' + plural(minutos, 'minuto', 'minutos');
        }
    }

    // -------------------------------------------------------------
    // Colunas
    // -------------------------------------------------------------

    /** Prazo curto, para a direita da linha: "hoje", "amanhã", "ter 03/11". */
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

    /** "Cine Clube · em 8 dias" — o tipo e quando o evento acontece. */
    function sobreOEvento(evento) {
        const partes = [];
        if (evento.tipo) { partes.push(evento.tipo); }
        if (evento.data) {
            const dias = diaNumero(evento.data) - diaNumero(dados.hoje);
            if (dias === 0) { partes.push('é hoje'); }
            else if (dias === 1) { partes.push('amanhã'); }
            else if (dias > 1) { partes.push('em ' + dias + ' dias'); }
        }
        return partes.join(' · ');
    }

    /** Junta as tarefas da coluna por evento, na ordem em que aparecem. */
    function agruparPorEvento(tarefas) {
        const grupos = new Map();
        tarefas.forEach(function (t) {
            if (!grupos.has(t.evento.id)) {
                grupos.set(t.evento.id, { evento: t.evento, tarefas: [] });
            }
            grupos.get(t.evento.id).tarefas.push(t);
        });
        return Array.from(grupos.values());
    }

    function cabecalhoDoGrupo(evento, continuacao) {
        const grupo = el('section', 'painel-grupo');
        grupo.style.setProperty('--cor-tipo', corDoTipo(evento));

        const topo = el(evento.link ? 'a' : 'header', 'painel-grupo-topo');
        if (evento.link) { topo.href = evento.link; }
        topo.append(el('strong', 'painel-grupo-nome', evento.titulo));
        const sobre = continuacao ? [evento.tipo, 'continuação'].filter(Boolean).join(' · ') : sobreOEvento(evento);
        if (sobre) {
            topo.append(el('span', 'painel-grupo-sobre', sobre));
        }

        grupo.append(topo, el('ul', 'painel-grupo-itens'));
        return grupo;
    }

    function linha(tarefa, nomeColuna) {
        const situacao = situacaoDoPrazo(tarefa);
        const item = el('li', 'painel-item situacao-' + situacao.classe);

        const texto = el('div', 'painel-item-texto');
        const titulo = el('span', 'painel-item-titulo', tarefa.titulo);
        if (tarefa.prioridade === 'ALTA' && nomeColuna !== 'prioridade') {
            titulo.prepend(el('b', 'painel-item-alta', '▲ '));
            titulo.title = 'Prioridade alta';
        }
        texto.append(titulo);
        if (tarefa.responsavel) {
            texto.append(el('span', 'painel-item-quem', tarefa.responsavel));
        }

        item.append(texto, el('span', 'painel-item-prazo', prazoCurto(tarefa)));
        return item;
    }

    const paginas = {};  // coluna -> { total, atual }

    function telaPequena() {
        return window.matchMedia('(max-width: 1000px)').matches;
    }

    function novaPagina(janela) {
        const pagina = el('div', 'painel-pagina');
        janela.append(pagina);
        return pagina;
    }

    function transbordou(pagina) {
        return !telaPequena() && pagina.scrollHeight > pagina.clientHeight + 1;
    }

    /**
     * Distribui os grupos em páginas do tamanho da coluna. Se um grupo
     * não cabe inteiro, o resto vai para a página seguinte, com o nome
     * do evento repetido ("continuação").
     */
    function desenharColuna(nome) {
        const tarefas = (dados.colunas || {})[nome] || [];
        $('total-' + nome).textContent = String(tarefas.length);

        const assinatura = JSON.stringify(tarefas.map(function (t) {
            return [t.id, t.titulo, t.prazo, t.horario, t.prioridade, t.responsavel, t.evento.titulo, t.evento.tipo, t.evento.tipo_cor, t.evento.data];
        })) + dados.hoje;

        // Só refaz quando muda: refazer volta para a primeira página.
        if (assinatura === assinaturas[nome]) {
            return;
        }
        assinaturas[nome] = assinatura;

        const janela = $('lista-' + nome);
        janela.replaceChildren();

        if (tarefas.length === 0) {
            janela.append(el('p', 'painel-coluna-vazia', '✓ ' + janela.dataset.vazio));
            paginas[nome] = { total: 1, atual: 0 };
            desenharMarcadores(nome);
            return;
        }

        let pagina = novaPagina(janela);
        pagina.classList.add('ativa');

        agruparPorEvento(tarefas).forEach(function (g) {
            let grupo = cabecalhoDoGrupo(g.evento, false);
            pagina.append(grupo);

            g.tarefas.forEach(function (t) {
                const item = linha(t, nome);
                grupo.lastChild.append(item);
                if (!transbordou(pagina)) { return; }

                // Não coube: tira daqui e começa outra página.
                item.remove();
                if (grupo.lastChild.children.length === 0) { grupo.remove(); }
                pagina = novaPagina(janela);
                grupo = cabecalhoDoGrupo(g.evento, true);
                grupo.lastChild.append(item);
                pagina.append(grupo);
            });
        });

        paginas[nome] = { total: janela.children.length, atual: 0 };
        desenharMarcadores(nome);
    }

    /** Bolinhas embaixo da coluna: em que página está. */
    function desenharMarcadores(nome) {
        const caixa = $('paginas-' + nome);
        const p = paginas[nome];
        caixa.replaceChildren();
        if (p.total <= 1) { return; }
        for (let i = 0; i < p.total; i++) {
            caixa.append(el('span', i === p.atual ? 'atual' : null));
        }
    }

    /** Passa para a página seguinte em cada coluna que tem mais de uma. */
    function virarPaginas() {
        COLUNAS.forEach(function (nome) {
            const p = paginas[nome];
            if (!p || p.total <= 1) { return; }
            const lista = $('lista-' + nome).children;
            lista[p.atual].classList.remove('ativa');
            p.atual = (p.atual + 1) % p.total;
            lista[p.atual].classList.add('ativa');
            desenharMarcadores(nome);
        });
    }

    function desenhar() {
        COLUNAS.forEach(desenharColuna);
        desenharLembrete();

        const atrasados = ((dados.colunas || {}).atrasados || []).length;
        document.title = (atrasados > 0 ? '(' + atrasados + ') ' : '') + 'Painel ao vivo | ELOS';
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

        // Mudou o tamanho da janela: as páginas precisam ser refeitas.
        let redimensionando = null;
        window.addEventListener('resize', function () {
            clearTimeout(redimensionando);
            redimensionando = setTimeout(function () {
                if (!dados) { return; }
                COLUNAS.forEach(function (nome) { assinaturas[nome] = null; });
                desenhar();
            }, 300);
        });

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

        setInterval(virarPaginas, VIRAR_PAGINA_A_CADA);
        setTimeout(function () { location.reload(); }, RECARREGAR_A_PAGINA_A_CADA);
    }

    iniciar();
})();
