/**
 * Dashboard: trocar período (Semanal/Mensal/Anual), setas e calendário sem recarregar a página.
 *
 * Os links continuam sendo links normais (funcionam sem JS e abrem em nova aba).
 * Aqui interceptamos o clique, buscamos a página do período, trocamos só o <main>
 * e atualizamos a URL com o histórico do navegador (voltar/avançar funcionam).
 */
(function () {
    'use strict';

    var calAbort = null;  // listeners do calendário anterior, removidos a cada troca
    var request = null;   // pedido em andamento (cancelado se clicar em outro período)

        function initCalendar(signal) {
        const btn = document.getElementById('dashCalBtn');
        const box = document.getElementById('dashCal');
        if (!btn || !box) return;

        const dataEl = document.getElementById('dashCalData');
        if (!dataEl) return;
        const data = JSON.parse(dataEl.textContent);
        const period = data.period;
        const ref = data.ref;
        const today = data.today;
        const firstDay = data.firstDay;
        const days = data.days || {};
        const months = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
        const wk = ['seg','ter','qua','qui','sex','sáb','dom'];

        const pad = (n) => String(n).padStart(2, '0');
        const ymd = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        const parse = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
        const url = (d) => 'index.php?action=dashboard&period=' + period + '&d=' + d;
        const count = (prefix) => Object.keys(days).reduce((t, k) => t + (k.startsWith(prefix) ? days[k] : 0), 0);
        const firstYear = Number(firstDay.slice(0, 4));
        const thisYear = Number(today.slice(0, 4));

        let viewYear = Number(ref.slice(0, 4));
        let viewMonth = Number(ref.slice(5, 7)) - 1;

        function head(label, canPrev, canNext) {
            return '<div class="dash-cal__head">'
                + '<button type="button" data-step="-1" ' + (canPrev ? '' : 'disabled') + ' aria-label="Anterior">‹</button>'
                + '<b>' + label + '</b>'
                + '<button type="button" data-step="1" ' + (canNext ? '' : 'disabled') + ' aria-label="Próximo">›</button></div>';
        }

        function render() {
            let html = '';
            if (period === 'year') {
                html += '<p class="dash-cal__title">Escolha o ano</p><div class="dash-cal__grid dash-cal__grid--3">';
                for (let y = thisYear; y >= firstYear; y--) {
                    const n = count(y + '-');
                    const cur = Number(ref.slice(0, 4)) === y;
                    html += '<a href="' + url(y + '-01-01') + '" class="dash-cal__cell' + (cur ? ' is-cur' : '') + '">' + y
                        + (n ? '<small>' + n + ' zerado' + (n > 1 ? 's' : '') + '</small>' : '<small class="is-none">—</small>') + '</a>';
                }
                html += '</div>';
            } else if (period === 'month') {
                html += head(String(viewYear), viewYear > firstYear, viewYear < thisYear) + '<div class="dash-cal__grid dash-cal__grid--3">';
                for (let m = 0; m < 12; m++) {
                    const key = viewYear + '-' + pad(m + 1);
                    const off = key > today.slice(0, 7) || key < firstDay.slice(0, 7);
                    const cur = key === ref.slice(0, 7);
                    const n = count(key + '-');
                    const label = months[m].slice(0, 3);
                    html += off
                        ? '<span class="dash-cal__cell is-off">' + label + '</span>'
                        : '<a href="' + url(key + '-01') + '" class="dash-cal__cell' + (cur ? ' is-cur' : '') + '">' + label
                          + (n ? '<small>' + n + '</small>' : '') + '</a>';
                }
                html += '</div>';
            } else {
                // Semana: calendário do mês, uma linha por semana (segunda a domingo)
                const first = new Date(viewYear, viewMonth, 1);
                const last = new Date(viewYear, viewMonth + 1, 0);
                const canPrev = (viewYear * 12 + viewMonth) > (firstYear * 12 + Number(firstDay.slice(5, 7)) - 1);
                const canNext = (viewYear * 12 + viewMonth) < (thisYear * 12 + Number(today.slice(5, 7)) - 1);
                html += head(months[viewMonth] + ' ' + viewYear, canPrev, canNext);
                html += '<div class="dash-cal__week dash-cal__week--head">' + wk.map((d) => '<span>' + d + '</span>').join('') + '</div>';
                let start = new Date(first);
                start.setDate(start.getDate() - ((start.getDay() + 6) % 7));   // volta até a segunda-feira
                const refMonday = (() => { const r = parse(ref); r.setDate(r.getDate() - ((r.getDay() + 6) % 7)); return ymd(r); })();
                while (start <= last) {
                    const monday = ymd(start);
                    const sunday = new Date(start); sunday.setDate(sunday.getDate() + 6);
                    const off = monday > today || ymd(sunday) < firstDay;
                    const cur = monday === refMonday;
                    let cells = '';
                    for (let i = 0; i < 7; i++) {
                        const d = new Date(start); d.setDate(d.getDate() + i);
                        const key = ymd(d);
                        const other = d.getMonth() !== viewMonth;
                        cells += '<span class="' + (other ? 'is-other ' : '') + (days[key] ? 'has-game' : '') + '">' + d.getDate() + '</span>';
                    }
                    html += off
                        ? '<span class="dash-cal__week is-off">' + cells + '</span>'
                        : '<a href="' + url(monday) + '" class="dash-cal__week' + (cur ? ' is-cur' : '') + '">' + cells + '</a>';
                    start.setDate(start.getDate() + 7);
                }
                html += '<p class="dash-cal__hint"><i></i> dia com jogo zerado · clique numa semana</p>';
            }
            box.innerHTML = html;
        }

        function open() {
            viewYear = Number(ref.slice(0, 4));
            viewMonth = Number(ref.slice(5, 7)) - 1;
            render();
            box.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            btn.classList.add('is-active');
        }
        function close() {
            box.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
            btn.classList.remove('is-active');
        }

        btn.addEventListener('click', (e) => { e.stopPropagation(); box.hidden ? open() : close(); });
        box.addEventListener('click', (e) => {
            e.stopPropagation();
            const step = e.target.closest('[data-step]');
            if (!step || step.disabled) return;
            const dir = Number(step.dataset.step);
            if (period === 'month') {
                viewYear += dir;
            } else {
                viewMonth += dir;
                if (viewMonth < 0) { viewMonth = 11; viewYear--; }
                if (viewMonth > 11) { viewMonth = 0; viewYear++; }
            }
            render();
        });
        document.addEventListener('click', () => { if (!box.hidden) close(); }, { signal });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !box.hidden) { close(); btn.focus(); } }, { signal });
    }

    function startCalendar() {
        if (calAbort) calAbort.abort();
        calAbort = new AbortController();
        initCalendar(calAbort.signal);
    }

    function isDashboardLink(url) {
        if (url.origin !== location.origin) return false;
        return url.searchParams.get('action') === 'dashboard' || /\/dashboard\/?$/.test(url.pathname);
    }

    function load(url, push) {
        var main = document.querySelector('main');
        if (!main) { location.href = url.href; return; }

        if (request) request.abort();
        request = new AbortController();
        var finalUrl = url.href;

        main.setAttribute('aria-busy', 'true');
        main.style.transition = 'opacity .15s';
        main.style.opacity = '0.55';

        fetch(url.href, { signal: request.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                // o servidor pode redirecionar para o endereço bonito (/dashboard?period=...): usa o final
                finalUrl = res.url || url.href;
                return res.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.querySelector('main');
                if (!fresh || !fresh.querySelector('#dashCalData')) throw new Error('Resposta inesperada');

                main.replaceWith(fresh);
                if (doc.title) document.title = doc.title;
                if (push) history.pushState({ dash: true }, '', finalUrl);
                startCalendar();
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                location.href = url.href; // qualquer falha: navegação normal
            });
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('main a[href]');
        if (!a || e.defaultPrevented) return;
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || a.target === '_blank') return;
        var url = new URL(a.getAttribute('href'), location.href);
        if (!isDashboardLink(url)) return; // links de jogos etc. navegam normalmente
        e.preventDefault();
        load(url, true);
    });

    window.addEventListener('popstate', function () {
        if (document.getElementById('dashCalData')) load(new URL(location.href), false);
    });

    startCalendar();
})();
