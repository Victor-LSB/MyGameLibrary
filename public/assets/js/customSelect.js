// customSelect.js — troca o <select> nativo do navegador por um menu no estilo do site.
// Uso: adicione data-custom-select no <select>. O <select> original continua no DOM
// (escondido), então formulários, .value e o evento "change" funcionam como antes.
(function () {
    var uid = 0;
    var CHEV = '<svg class="cs-chev" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var CHECK = '<svg class="cs-check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m4 10.5 4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    function enhance(select) {
        if (select.dataset.csReady) return;
        select.dataset.csReady = '1';

        var id = 'cs' + (++uid);
        var btnClasses = select.className.replace(/\b(appearance-none|filterStatus)\b/g, '');

        var wrap = document.createElement('div');
        wrap.className = 'cs-wrap' + (select.classList.contains('w-full') ? ' cs-block' : '');
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.classList.add('cs-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = btnClasses + ' cs-btn';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', id + '-list');
        var label = document.createElement('span');
        label.className = 'cs-label';
        btn.appendChild(label);
        btn.insertAdjacentHTML('beforeend', CHEV);

        var list = document.createElement('ul');
        list.id = id + '-list';
        list.className = 'cs-list';
        list.setAttribute('role', 'listbox');
        list.hidden = true;

        Array.prototype.forEach.call(select.options, function (opt, i) {
            var li = document.createElement('li');
            li.className = 'cs-opt';
            li.setAttribute('role', 'option');
            li.dataset.index = i;
            var text = document.createElement('span');
            text.textContent = opt.text;
            li.appendChild(text);
            li.insertAdjacentHTML('beforeend', CHECK);
            list.appendChild(li);
        });

        wrap.appendChild(btn);
        wrap.appendChild(list);

        var items = list.children;
        var active = -1;

        function sync() {
            var sel = select.selectedIndex;
            label.textContent = select.options[sel] ? select.options[sel].text : '';
            Array.prototype.forEach.call(items, function (li, i) {
                li.setAttribute('aria-selected', i === sel ? 'true' : 'false');
            });
        }

        function setActive(i) {
            if (i < 0 || i >= items.length) return;
            if (items[active]) items[active].classList.remove('is-active');
            active = i;
            items[i].classList.add('is-active');
            if (items[i].scrollIntoView) items[i].scrollIntoView({ block: 'nearest' });
        }

        function isOpen() { return !list.hidden; }

        function open() {
            document.dispatchEvent(new CustomEvent('mgl:dropdown-open', { detail: { id: id } }));
            list.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            list.classList.remove('cs-right');
            if (list.getBoundingClientRect().right > window.innerWidth - 8) list.classList.add('cs-right');
            setActive(select.selectedIndex);
        }

        function close() {
            list.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
            if (items[active]) items[active].classList.remove('is-active');
        }

        function choose(i) {
            var changed = i !== select.selectedIndex;
            if (changed) select.selectedIndex = i;
            sync();
            close();
            btn.focus();
            if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        btn.addEventListener('click', function () { isOpen() ? close() : open(); });

        btn.addEventListener('keydown', function (e) {
            var k = e.key;
            if (!isOpen()) {
                if (k === 'ArrowDown' || k === 'ArrowUp' || k === 'Enter' || k === ' ') { e.preventDefault(); open(); }
                return;
            }
            if (k === 'ArrowDown') { e.preventDefault(); setActive(Math.min(active + 1, items.length - 1)); }
            else if (k === 'ArrowUp') { e.preventDefault(); setActive(Math.max(active - 1, 0)); }
            else if (k === 'Home') { e.preventDefault(); setActive(0); }
            else if (k === 'End') { e.preventDefault(); setActive(items.length - 1); }
            else if (k === 'Enter' || k === ' ') { e.preventDefault(); choose(active); }
            else if (k === 'Escape') { e.preventDefault(); close(); }
            else if (k === 'Tab') { close(); }
            else if (k.length === 1) {
                // digitar uma letra pula para a próxima opção que começa com ela
                var ch = k.toLowerCase();
                for (var n = 1; n <= items.length; n++) {
                    var j = (active + n) % items.length;
                    if (items[j].textContent.trim().toLowerCase().indexOf(ch) === 0) { setActive(j); break; }
                }
            }
        });

        list.addEventListener('mousedown', function (e) { e.preventDefault(); });
        list.addEventListener('click', function (e) {
            var li = e.target.closest('.cs-opt');
            if (li) choose(parseInt(li.dataset.index, 10));
        });

        document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });
        document.addEventListener('mgl:dropdown-open', function (e) { if (e.detail.id !== id) close(); });
        select.addEventListener('change', sync);

        sync();
    }

    function init() {
        document.querySelectorAll('select[data-custom-select]').forEach(enhance);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
