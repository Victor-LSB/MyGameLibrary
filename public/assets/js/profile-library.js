/**
 * Biblioteca do perfil sem recarregar a página.
 *
 * "Ver biblioteca completa", "Voltar aos recentes", filtro/ordenação e paginação
 * continuam sendo links e formulário normais (funcionam sem JS). Aqui interceptamos
 * esses cliques, pedimos ao servidor só a seção #biblioteca (?partial=library),
 * trocamos o trecho na tela e atualizamos a URL com o histórico do navegador.
 */
(function () {
    'use strict';

    var SECTION_ID = 'biblioteca';
    var controller = null;

    function getSection() {
        return document.getElementById(SECTION_ID);
    }

    function load(url, options) {
        options = options || {};
        var current = getSection();
        if (!current) {
            window.location.href = url.href;
            return;
        }

        // Se o usuário clicar rápido em outra coisa, cancela o pedido anterior
        if (controller) controller.abort();
        controller = new AbortController();

        var requestUrl = new URL(url.href);
        requestUrl.searchParams.set('partial', 'library');
        requestUrl.hash = '';

        current.setAttribute('aria-busy', 'true');
        current.style.transition = 'opacity .15s';
        current.style.opacity = '0.5';

        fetch(requestUrl.href, {
            signal: controller.signal,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' }
        })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(function (html) {
                var template = document.createElement('template');
                template.innerHTML = html.trim();
                var fresh = template.content.firstElementChild;
                if (!fresh || fresh.id !== SECTION_ID) throw new Error('Resposta inesperada');

                var old = getSection();
                var searchHadFocus = document.activeElement &&
                    document.activeElement.name === 'q' && old.contains(document.activeElement);

                old.replaceWith(fresh);

                if (searchHadFocus) {
                    var input = fresh.querySelector('input[name="q"]');
                    if (input) {
                        input.focus();
                        var end = input.value.length;
                        input.setSelectionRange(end, end);
                    }
                }

                if (options.push) {
                    var pageUrl = new URL(url.href);
                    pageUrl.searchParams.delete('partial');
                    pageUrl.hash = '#' + SECTION_ID;
                    window.history.pushState(null, '', pageUrl.href);
                }

                if (options.scroll) {
                    fresh.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            })
            .catch(function (error) {
                if (error && error.name === 'AbortError') return;
                // Qualquer falha: cai no comportamento antigo (navegação normal)
                window.location.href = url.href;
            });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest && event.target.closest('a[data-library-link]');
        if (!link || event.defaultPrevented) return;
        // Deixa abrir em nova aba/janela normalmente
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        load(new URL(link.href, window.location.href), { push: true, scroll: true });
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches || !form.matches('form[data-library-form]')) return;

        event.preventDefault();
        var url = new URL(form.getAttribute('action') || 'index.php', window.location.href);
        url.search = '';
        new FormData(form).forEach(function (value, key) {
            url.searchParams.set(key, value);
        });
        load(url, { push: true });
    });

    // Botão voltar/avançar do navegador
    window.addEventListener('popstate', function () {
        if (getSection()) {
            load(new URL(window.location.href), { push: false });
        }
    });
})();
