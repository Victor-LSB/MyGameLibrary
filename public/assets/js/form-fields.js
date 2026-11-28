/**
 * Campos de formulário do site:
 *
 * 1) Calendário próprio (no estilo do calendário do dashboard) para
 *    <input type="date" data-datepicker> e <input type="datetime-local" data-datepicker>.
 *    O campo original vira oculto e continua sendo o que vai no formulário (mesmo name/id,
 *    mesmo formato de valor), então nada muda no servidor nem nos outros scripts. Quem mudar
 *    o .value por código (ex.: status.js abrindo a janela de "Zerado") atualiza a tela também.
 *    Em datetime-local, só a data é escolhida; o horário que já estava é mantido.
 *
 * 2) Campo de horas (<input data-hours>): aceita "3.1", "3,1", "3:20", "3h20", "3h 20min",
 *    "45min". A conversão de verdade é feita no servidor (mgl_parse_hours); aqui só avisa
 *    quando o texto não dá para entender.
 */
(function () {
    'use strict';

    var MONTHS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    var WEEK = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
    var pad = function (n) { return String(n).padStart(2, '0'); };
    var ymd = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
    var parseYmd = function (s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || '');
        return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
    };
    var fmtBr = function (d) { return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear(); };

    var openPicker = null; // só um calendário aberto por vez

    function setupDatePicker(input) {
        if (input.dataset.dpReady) return;
        input.dataset.dpReady = '1';
        var isDateTime = input.type === 'datetime-local';
        var nativeDesc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

        // Campo visível (botão com a data) + o input original oculto
        var wrap = document.createElement('div');
        wrap.className = 'mdp';
        var display = document.createElement('button');
        display.type = 'button';
        display.className = input.className + ' mdp__display';
        display.setAttribute('aria-haspopup', 'dialog');
        display.setAttribute('aria-expanded', 'false');
        if (input.getAttribute('aria-label')) display.setAttribute('aria-label', input.getAttribute('aria-label'));
        // o <label for> do campo original passa a apontar para o botão
        if (input.id) {
            var lbl = document.querySelector('label[for="' + input.id + '"]');
            if (lbl) { display.id = input.id + '__display'; lbl.htmlFor = display.id; }
        }
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(display);
        wrap.appendChild(input);
        input.type = 'hidden';

        var pop = document.createElement('div');
        pop.className = 'mdp__pop';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', 'Escolher data');
        pop.hidden = true;
        wrap.appendChild(pop);

        var view = null;    // mês mostrado
        var focusDay = null;

        function current() { return parseYmd(nativeDesc.get.call(input)); }
        function maxDate() {
            var m = parseYmd(input.max || input.dataset.max || '');
            if (m) return m;
            var t = new Date(); return new Date(t.getFullYear(), t.getMonth(), t.getDate());
        }
        function refresh() {
            var d = current();
            display.textContent = d ? fmtBr(d) : 'dd/mm/aaaa';
            display.classList.toggle('is-empty', !d);
        }

        // Mudanças por código no .value (ex.: status.js) também atualizam a tela
        Object.defineProperty(input, 'value', {
            configurable: true,
            get: function () { return nativeDesc.get.call(input); },
            set: function (v) { nativeDesc.set.call(input, v); refresh(); }
        });

        function choose(d) {
            var v = ymd(d);
            if (isDateTime) {
                var old = nativeDesc.get.call(input);
                var time = /T(\d{2}:\d{2})/.exec(old || '');
                var now = new Date();
                var t = time ? time[1] : pad(now.getHours()) + ':' + pad(now.getMinutes());
                // escolher hoje com um horário no futuro: usa o horário de agora
                if (v === ymd(now) && t > pad(now.getHours()) + ':' + pad(now.getMinutes())) {
                    t = pad(now.getHours()) + ':' + pad(now.getMinutes());
                }
                v = v + 'T' + t;
            }
            input.value = v;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            close(true);
        }

        function render() {
            var max = maxDate();
            var sel = current();
            var today = new Date();
            var first = new Date(view.getFullYear(), view.getMonth(), 1);
            var start = new Date(first); start.setDate(1 - first.getDay());
            var nextMonth = new Date(view.getFullYear(), view.getMonth() + 1, 1);
            var html = '<div class="mdp__head">'
                + '<button type="button" data-step="-1" aria-label="Mês anterior">‹</button>'
                + '<b>' + MONTHS[view.getMonth()] + ' ' + view.getFullYear() + '</b>'
                + '<button type="button" data-step="1" aria-label="Próximo mês"' + (nextMonth > max ? ' disabled' : '') + '>›</button></div>'
                + '<div class="mdp__grid mdp__grid--head">' + WEEK.map(function (w) { return '<span>' + w + '</span>'; }).join('') + '</div>'
                + '<div class="mdp__grid" role="grid">';
            for (var i = 0; i < 42; i++) {
                var d = new Date(start); d.setDate(start.getDate() + i);
                var key = ymd(d);
                var cls = 'mdp__day';
                if (d.getMonth() !== view.getMonth()) cls += ' is-other';
                if (sel && key === ymd(sel)) cls += ' is-sel';
                if (key === ymd(today)) cls += ' is-today';
                var off = d > max;
                html += '<button type="button" class="' + cls + '" data-day="' + key + '"' + (off ? ' disabled' : '')
                    + ' tabindex="' + (focusDay && key === ymd(focusDay) ? '0' : '-1') + '"'
                    + ' aria-label="' + d.getDate() + ' de ' + MONTHS[d.getMonth()] + ' de ' + d.getFullYear() + '"'
                    + (sel && key === ymd(sel) ? ' aria-pressed="true"' : '') + '>' + d.getDate() + '</button>';
            }
            html += '</div><div class="mdp__foot">'
                + '<button type="button" data-clear>Limpar</button>'
                + '<button type="button" data-today>Hoje</button></div>';
            pop.innerHTML = html;
        }

        function focusCurrent() {
            var b = focusDay && pop.querySelector('[data-day="' + ymd(focusDay) + '"]');
            if (b && !b.disabled) b.focus();
        }

        function open() {
            if (openPicker && openPicker !== api) openPicker.close(false);
            var sel = current() || maxDate();
            view = new Date(sel.getFullYear(), sel.getMonth(), 1);
            focusDay = sel;
            render();
            pop.hidden = false;
            display.setAttribute('aria-expanded', 'true');
            openPicker = api;
            focusCurrent();
        }
        function close(returnFocus) {
            if (pop.hidden) return;
            pop.hidden = true;
            display.setAttribute('aria-expanded', 'false');
            if (openPicker === api) openPicker = null;
            if (returnFocus) display.focus();
        }
        var api = { close: close };

        display.addEventListener('click', function (e) {
            e.stopPropagation();
            pop.hidden ? open() : close(false);
        });
        pop.addEventListener('click', function (e) {
            e.stopPropagation();
            var t = e.target.closest('button');
            // O campo costuma estar dentro de um <label>: clique em área vazia do calendário
            // não pode "clicar" no campo de novo (isso fecharia o calendário)
            if (!t || t.disabled) { e.preventDefault(); return; }
            if (t.dataset.step) {
                view = new Date(view.getFullYear(), view.getMonth() + Number(t.dataset.step), 1);
                focusDay = new Date(view);
                render();
            } else if (t.dataset.day) {
                choose(parseYmd(t.dataset.day));
            } else if (t.hasAttribute('data-today')) {
                var now = new Date();
                choose(new Date(now.getFullYear(), now.getMonth(), now.getDate()));
            } else if (t.hasAttribute('data-clear')) {
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
                close(true);
            }
        });
        // Teclado: setas andam pelos dias, PageUp/PageDown trocam o mês, Esc fecha
        pop.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); return; }
            var step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
            if (!step && e.key !== 'PageUp' && e.key !== 'PageDown') return;
            if (!e.target.dataset.day) return;
            e.preventDefault();
            var d = new Date(focusDay);
            if (step) d.setDate(d.getDate() + step);
            else d.setMonth(d.getMonth() + (e.key === 'PageUp' ? -1 : 1));
            if (d > maxDate()) return;
            focusDay = d;
            if (d.getMonth() !== view.getMonth() || d.getFullYear() !== view.getFullYear()) {
                view = new Date(d.getFullYear(), d.getMonth(), 1);
            }
            render();
            focusCurrent();
        });

        refresh();
    }

    document.addEventListener('click', function () { if (openPicker) openPicker.close(false); });

    // ---------- horas ----------
    function parseHours(raw) {
        var s = String(raw || '').trim().toLowerCase().replace(/\s+/g, '');
        if (s === '') return 0;
        var m;
        if ((m = /^(\d+):([0-5]?\d)$/.exec(s))) return +m[1] + +m[2] / 60;
        if ((m = /^(\d+)h([0-5]?\d)$/.exec(s))) return +m[1] + +m[2] / 60; // 3h20
        if ((m = /^(?:(\d+(?:[.,]\d+)?)h)?(?:(\d+)(?:m|min))?$/.exec(s)) && (m[1] || m[2])) {
            return (m[1] ? parseFloat(m[1].replace(',', '.')) : 0) + (m[2] ? +m[2] / 60 : 0);
        }
        if (/^\d+([.,]\d+)?$/.test(s)) return parseFloat(s.replace(',', '.'));
        return null;
    }

    function setupHours(input) {
        if (input.dataset.hoursReady) return;
        input.dataset.hoursReady = '1';
        var check = function () {
            var ok = parseHours(input.value) !== null;
            input.classList.toggle('is-invalid', !ok);
            input.setCustomValidity(ok ? '' : 'Use, por exemplo, 3,1 ou 3:20');
        };
        input.addEventListener('input', check);
        input.addEventListener('blur', check);
    }

    function init(root) {
        (root || document).querySelectorAll('input[data-datepicker]').forEach(setupDatePicker);
        (root || document).querySelectorAll('input[data-hours]').forEach(setupHours);
    }

    window.mglFormFields = { init: init, parseHours: parseHours };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(); });
    else init();
})();
