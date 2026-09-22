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
 * Uso:
 *   MenuLateral::renderizar('eventos', false, $nomeUsuario, $primeiroNome, $perfilUsuario);
 */
final class MenuLateral
{
    /**
     * O ELOS é organizado pela exposição: tudo (checklist, visitas,
     * transportes, documentos) fica dentro dela. Por isso o menu tem
     * só o que não pertence a uma exposição específica. As telas de
     * Tarefas, Transportes, Visitas e Formulários continuam existindo
     * e são abertas a partir da exposição.
     *
     * Destino relativo à pasta "pages/".
     */
    private const ITENS = [
        ['rota' => 'inicio', 'titulo' => 'Início', 'icone' => '⌂', 'pagina' => '../index.php'],
        ['rota' => 'eventos', 'titulo' => 'Exposições', 'icone' => '▣', 'pagina' => 'eventos.php'],
        ['rota' => 'agenda', 'titulo' => 'Calendário', 'icone' => '◷', 'pagina' => 'agenda.php'],
        ['rota' => 'equipe', 'titulo' => 'Equipe', 'icone' => '◎', 'pagina' => 'equipe.php', 'somenteGestor' => true],
    ];

    /**
     * @param bool $naRaiz true para index.php (raiz do frontend); false para arquivos em pages/.
     */
    public static function renderizar(
        string $rotaAtual,
        bool $naRaiz,
        string $nomeUsuario,
        string $primeiroNome,
        string $perfilUsuario
    ): void {
        $hrefInicio = $naRaiz ? 'index.php' : '../index.php';
        $hrefLogout = $naRaiz ? 'pages/logout.php' : 'logout.php';
        ?>
    <aside class="sidebar">

        <div class="sidebar-decoration sidebar-decoration-top"></div>

        <div class="sidebar-top">

            <a href="<?= self::escapar($hrefInicio) ?>" class="sidebar-logo" aria-label="ELOS">

                <strong class="sidebar-logo-text">
                    EL<span>O</span>S
                </strong>

                <small>
                    EVENTOS QUE<br>
                    CONECTAM
                </small>

            </a>

            <nav class="sidebar-navigation" aria-label="Navegação principal">

                <?php foreach (self::ITENS as $item): ?>

                    <?php if (!empty($item['somenteGestor']) && !in_array($perfilUsuario, ['GESTOR', 'ADMIN'], true)) { continue; } ?>

                    <a
                        href="<?= self::escapar(self::href($item['pagina'], $naRaiz)) ?>"
                        class="sidebar-item<?= $item['rota'] === $rotaAtual ? ' active' : '' ?>"
                        <?= $item['rota'] === $rotaAtual ? 'aria-current="page"' : '' ?>
                    >
                        <span class="sidebar-icon" aria-hidden="true">
                            <?= $item['icone'] ?>
                        </span>

                        <span><?= self::escapar($item['titulo']) ?></span>
                    </a>

                <?php endforeach; ?>

            </nav>

        </div>

        <div class="sidebar-footer">

            <div class="sidebar-divider"></div>

            <div class="sidebar-user">

                <span class="user-avatar">
                    <?= self::escapar(mb_substr($nomeUsuario, 0, 1)) ?>
                </span>

                <span>
                    <strong><?= self::escapar($primeiroNome) ?></strong>
                    <small><?= self::escapar($perfilUsuario) ?></small>
                </span>

            </div>

            <a href="<?= self::escapar($hrefLogout) ?>" class="sidebar-logout">
                <span class="sidebar-icon" aria-hidden="true">↪</span>
                <span>Sair</span>
            </a>

        </div>

        <div class="sidebar-decoration sidebar-decoration-bottom">
            <span class="shape-yellow"></span>
            <span class="shape-blue"></span>
            <span class="shape-cream"></span>
        </div>

    </aside>
        <?php
    }

    private static function href(?string $pagina, bool $naRaiz): string
    {
        if ($pagina === null) {
            return '#';
        }

        if (!$naRaiz) {
            return $pagina;
        }

        return $pagina === '../index.php' ? 'index.php' : 'pages/' . $pagina;
    }

    private static function escapar(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    }
}
