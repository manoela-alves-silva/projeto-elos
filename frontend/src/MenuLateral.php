<?php

declare(strict_types=1);

namespace Elos\Frontend;

/**
 * Menu lateral único do frontend.
 *
 * Antes cada página tinha uma cópia própria do array de itens e do
 * <aside>, e as cópias ficaram desatualizadas entre si (links "#" para
 * páginas que já existiam). Agora todas as páginas chamam
 * MenuLateral::renderizar() e os destinos ficam definidos só aqui.
 *
 * Só entram no menu páginas que existem.
 *
 * O menu é agrupado: no topo o que se usa todo dia (Início, Calendário,
 * Painel ao vivo); depois "Projetos" (a área de cada projeto da galeria)
 * e "Gestão" (Equipe e Relatório), cada grupo abre e fecha. "Minha
 * conta" fica no bloco da pessoa, no rodapé.
 *
 * No celular o menu vira uma gaveta: um cabeçalho com o botão de menu e
 * o logo, e uma barra de atalhos embaixo (Início, Pendências,
 * Calendário e Menu). Tudo isso sai daqui, então vale para todas as
 * telas que já usam o menu.
 *
 * Uso:
 *   MenuLateral::renderizar('eventos', false, $nomeUsuario, $primeiroNome, $perfilUsuario);
 */
final class MenuLateral
{
    /**
     * O ELOS é organizado pela exposição: tudo (checklist, visitas,
     * transportes, documentos) fica dentro dela. Por isso o menu tem
     * só o que não pertence a uma exposição específica.
     *
     * Destino relativo à raiz do site.
     */
    private const TOPO = [
        ['rota' => 'inicio', 'titulo' => 'Início', 'icone' => 'inicio', 'pagina' => './'],
        ['rota' => 'agenda', 'titulo' => 'Calendário', 'icone' => 'calendario', 'pagina' => 'calendario'],
        ['rota' => 'painel', 'titulo' => 'Painel ao vivo', 'icone' => 'monitor', 'pagina' => 'painel'],
    ];

    private const GRUPOS = [
        [
            'chave' => 'projetos',
            'titulo' => 'Projetos',
            'itens' => [
                ['rota' => 'eventos', 'titulo' => 'Exposições', 'icone' => 'quadro', 'pagina' => 'exposicoes'],
                ['rota' => 'cine-clube', 'titulo' => 'Cine Clube', 'icone' => 'camadas', 'pagina' => 'cine-clube'],
                ['rota' => 'cafe-cultural', 'titulo' => 'Café Cultural', 'icone' => 'camadas', 'pagina' => 'cafe-cultural'],
                ['rota' => 'arte-ao-ar-livre', 'titulo' => 'Arte ao Ar Livre', 'icone' => 'camadas', 'pagina' => 'arte-ao-ar-livre'],
                ['rota' => 'amostra-de-talentos', 'titulo' => 'Amostra de Talentos', 'icone' => 'camadas', 'pagina' => 'amostra-de-talentos'],
            ],
        ],
        [
            'chave' => 'gestao',
            'titulo' => 'Gestão',
            'itens' => [
                ['rota' => 'equipe', 'titulo' => 'Equipe', 'icone' => 'equipe', 'pagina' => 'equipe', 'somenteGestor' => true],
                ['rota' => 'relatorio', 'titulo' => 'Relatório', 'icone' => 'grafico', 'pagina' => 'relatorio?projeto=exposicoes'],
            ],
        ],
    ];

    /**
     * Ícones de traço fino, numa cor só (a do texto). Acompanham o
     * nome do item; não substituem.
     */
    private const ICONES = [
        'inicio' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'calendario' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'monitor' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'quadro' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/>',
        'camadas' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'equipe' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/>',
        'grafico' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'pessoa' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'sair' => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 16l-4-4 4-4M6 12h10"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'busca' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'pendencias' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
        'seta' => '<path d="M9 6l6 6-6 6"/>',
        'fechar' => '<path d="M6 6l12 12M18 6L6 18"/>',
    ];

    /**
     * @param bool $naRaiz não é mais usado (os endereços são da raiz); fica pela compatibilidade.
     */
    public static function renderizar(
        string $rotaAtual,
        bool $naRaiz,
        string $nomeUsuario,
        string $primeiroNome,
        string $perfilUsuario
    ): void {
        // Os endereços são todos da raiz do site (ver roteador.php),
        // então são iguais em qualquer tela.
        $hrefInicio = raizDoSite() . '/';
        $hrefLogout = raizDoSite() . '/sair';
        $ehGestor = in_array($perfilUsuario, ['GESTOR', 'ADMIN'], true);
        ?>
    <!-- Celular: cabeçalho com o menu (a gaveta é o próprio menu lateral) -->
    <header class="menu-celular-topo">
        <button type="button" class="menu-celular-botao" data-menu-abrir aria-controls="menu-lateral" aria-expanded="false" aria-label="Abrir menu">
            <?= self::icone('menu') ?>
        </button>
        <a href="<?= self::escapar($hrefInicio) ?>" class="menu-celular-logo" aria-label="ELOS, início">EL<span>O</span>S</a>
        <a href="<?= self::escapar(self::href('exposicoes')) ?>" class="menu-celular-botao" aria-label="Buscar exposição">
            <?= self::icone('busca') ?>
        </a>
    </header>

    <div class="menu-fundo" data-menu-fechar hidden></div>

    <aside class="sidebar" id="menu-lateral">

        <div class="sidebar-decoration sidebar-decoration-top"></div>

        <div class="sidebar-top">

            <div class="sidebar-logo-linha">
                <a href="<?= self::escapar($hrefInicio) ?>" class="sidebar-logo" aria-label="ELOS">

                    <strong class="sidebar-logo-text">
                        EL<span>O</span>S
                    </strong>

                    <small>
                        EVENTOS QUE<br>
                        CONECTAM
                    </small>

                </a>

                <button type="button" class="sidebar-fechar" data-menu-fechar aria-label="Fechar menu">
                    <?= self::icone('fechar') ?>
                </button>
            </div>

            <nav class="sidebar-navigation" aria-label="Navegação principal">

                <?php foreach (self::TOPO as $item): ?>
                    <?php self::item($item, $rotaAtual); ?>
                <?php endforeach; ?>

                <?php foreach (self::GRUPOS as $grupo): ?>
                    <?php
                    $itens = array_values(array_filter(
                        $grupo['itens'],
                        static fn(array $i): bool => empty($i['somenteGestor']) || $ehGestor
                    ));
                    if ($itens === []) {
                        continue;
                    }
                    // Projetos começa aberto; os outros grupos abrem quando a
                    // tela atual está dentro deles.
                    $aberto = $grupo['chave'] === 'projetos'
                        || in_array($rotaAtual, array_column($itens, 'rota'), true);
                    ?>
                    <details class="sidebar-grupo" <?= $aberto ? 'open' : '' ?>>
                        <summary class="sidebar-grupo-titulo">
                            <span><?= self::escapar($grupo['titulo']) ?></span>
                            <span class="sidebar-grupo-seta" aria-hidden="true"><?= self::icone('seta') ?></span>
                        </summary>
                        <?php foreach ($itens as $item): ?>
                            <?php self::item($item, $rotaAtual); ?>
                        <?php endforeach; ?>
                    </details>
                <?php endforeach; ?>

            </nav>

        </div>

        <div class="sidebar-footer">

            <div class="sidebar-divider"></div>

            <a href="<?= self::escapar(self::href('minha-conta')) ?>"
               class="sidebar-user<?= $rotaAtual === 'conta' ? ' active' : '' ?>"
               <?= $rotaAtual === 'conta' ? 'aria-current="page"' : '' ?>
               title="Minha conta">

                <span class="user-avatar">
                    <?= self::escapar(mb_substr($nomeUsuario, 0, 1)) ?>
                </span>

                <span>
                    <strong><?= self::escapar($primeiroNome) ?></strong>
                    <small><?= self::escapar($perfilUsuario) ?> · Minha conta</small>
                </span>

            </a>

            <a href="<?= self::escapar($hrefLogout) ?>" class="sidebar-logout">
                <span class="sidebar-icon" aria-hidden="true"><?= self::icone('sair') ?></span>
                <span>Sair</span>
            </a>

        </div>

        <div class="sidebar-decoration sidebar-decoration-bottom">
            <span class="shape-yellow"></span>
            <span class="shape-blue"></span>
            <span class="shape-cream"></span>
        </div>

    </aside>

    <!-- Celular: atalhos fixos embaixo -->
    <nav class="menu-celular-atalhos" aria-label="Atalhos">
        <?php
        $atalhos = [
            ['rota' => 'inicio', 'titulo' => 'Início', 'icone' => 'inicio', 'href' => self::href('./')],
            ['rota' => 'pendencias', 'titulo' => 'Pendências', 'icone' => 'pendencias', 'href' => self::href('./') . '#para-voce'],
            ['rota' => 'agenda', 'titulo' => 'Calendário', 'icone' => 'calendario', 'href' => self::href('calendario')],
        ];
        ?>
        <?php foreach ($atalhos as $atalho): ?>
            <a href="<?= self::escapar($atalho['href']) ?>"
               class="menu-celular-atalho<?= $atalho['rota'] === $rotaAtual ? ' active' : '' ?>"
               <?= $atalho['rota'] === $rotaAtual ? 'aria-current="page"' : '' ?>>
                <?= self::icone($atalho['icone']) ?>
                <span><?= self::escapar($atalho['titulo']) ?></span>
            </a>
        <?php endforeach; ?>
        <button type="button" class="menu-celular-atalho" data-menu-abrir aria-controls="menu-lateral" aria-expanded="false">
            <?= self::icone('menu') ?>
            <span>Menu</span>
        </button>
    </nav>

    <script>
    (function () {
        // Gaveta do menu no celular: abre pelo botão, fecha pelo X, pelo
        // fundo escuro ou pela tecla Esc.
        var corpo = document.body;
        var fundo = document.querySelector('.menu-fundo');
        var abrir = document.querySelectorAll('[data-menu-abrir]');

        function marcar(aberto) {
            corpo.classList.toggle('menu-aberto', aberto);
            fundo.hidden = !aberto;
            abrir.forEach(function (b) { b.setAttribute('aria-expanded', aberto ? 'true' : 'false'); });
            if (aberto) {
                var primeiro = document.querySelector('#menu-lateral .sidebar-fechar');
                if (primeiro) { primeiro.focus(); }
            }
        }

        abrir.forEach(function (b) { b.addEventListener('click', function () { marcar(true); }); });
        document.querySelectorAll('[data-menu-fechar]').forEach(function (b) {
            b.addEventListener('click', function () { marcar(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && corpo.classList.contains('menu-aberto')) { marcar(false); }
        });
    })();
    </script>
        <?php
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function item(array $item, string $rotaAtual): void
    {
        $atual = $item['rota'] === $rotaAtual;
        ?>
                    <a
                        href="<?= self::escapar(self::href($item['pagina'])) ?>"
                        class="sidebar-item<?= $atual ? ' active' : '' ?>"
                        <?= $atual ? 'aria-current="page"' : '' ?>
                    >
                        <span class="sidebar-icon" aria-hidden="true"><?= self::icone($item['icone']) ?></span>
                        <span><?= self::escapar($item['titulo']) ?></span>
                    </a>
        <?php
    }

    /** Ícone em SVG inline; o desenho vem da lista fixa ICONES. */
    private static function icone(string $nome): string
    {
        return '<svg class="menu-icone" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . (self::ICONES[$nome] ?? '')
            . '</svg>';
    }

    private static function href(?string $pagina): string
    {
        if ($pagina === null) {
            return '#';
        }

        return raizDoSite() . '/' . ($pagina === './' ? '' : $pagina);
    }

    private static function escapar(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    }
}
