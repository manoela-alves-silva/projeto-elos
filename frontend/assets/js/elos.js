/*
 * ELOS — comportamento compartilhado dos formulários.
 *
 * Listas abertas: um <select class="js-lista-aberta"> com a opção
 * "+ Novo…" (valor "__nova__") mostra, logo abaixo, o bloco
 * .js-novo-item para escrever o novo nome. Os campos marcados com
 * data-obrigatorio só são exigidos enquanto esse bloco está visível.
 */
(function () {
    const OPCAO_NOVO = '__nova__';

    function prepararListasAbertas(raiz) {
        (raiz || document).querySelectorAll('select.js-lista-aberta').forEach(function (select) {
            const bloco = select.parentElement.querySelector('.js-novo-item');

            if (!bloco) {
                return;
            }

            const atualizar = function (focar) {
                const ativo = select.value === OPCAO_NOVO;
                bloco.hidden = !ativo;

                bloco.querySelectorAll('[data-obrigatorio]').forEach(function (campo) {
                    campo.required = ativo;
                });

                if (ativo && focar) {
                    const campo = bloco.querySelector('input');
                    if (campo) {
                        campo.focus();
                    }
                }
            };

            if (!select.dataset.listaAbertaPronta) {
                select.addEventListener('change', function () {
                    atualizar(true);
                });
                select.dataset.listaAbertaPronta = '1';
            }

            atualizar(false);
        });
    }

    window.ELOS = window.ELOS || {};
    window.ELOS.prepararListasAbertas = prepararListasAbertas;

    document.addEventListener('DOMContentLoaded', function () {
        prepararListasAbertas(document);
    });
})();
