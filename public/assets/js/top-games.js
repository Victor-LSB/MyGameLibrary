// top-games.js - Top 5 do perfil: slots fixos (#1 a #5), arrastar para trocar, salvar automático.

(function () {
    const root = document.getElementById('top5');
    if (!root) return;

    const SIZE = 5;
    const editable = root.dataset.editable === '1';
    const username = root.dataset.username || '';
    const grid = root.querySelector('[data-top5-grid]');
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    let slots = parseSlots(root.dataset.slots);
    let lastSaved = clone(slots);
    let sortables = [];
    let saveQueue = Promise.resolve();

    function parseSlots(raw) {
        try {
            const arr = JSON.parse(raw);
            if (Array.isArray(arr)) {
                return Array.from({ length: SIZE }, (_, i) => arr[i] || null);
            }
        } catch (e) { /* cai no padrão vazio */ }
        return new Array(SIZE).fill(null);
    }

    function clone(v) { return JSON.parse(JSON.stringify(v)); }

    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    function toast(message, isError) {
        let el = document.getElementById('top5Toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'top5Toast';
            document.body.appendChild(el);
        }
        el.textContent = message;
        el.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] px-4 py-2 rounded-sm text-sm font-bold shadow-xl transition-opacity '
            + (isError ? 'bg-red-700 text-white' : 'bg-zinc-800 text-zinc-100 border border-zinc-700');
        el.style.opacity = '1';
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { el.style.opacity = '0'; }, 2500);
    }

    // ---------- Render ----------

    function rankClasses(i) {
        return i === 0 ? 'text-amber-400' : 'text-violet-400';
    }

    function cardHtml(game, i) {
        const href = 'index.php?action=details&id=' + game.id + '&u=' + encodeURIComponent(username);
        const cover = game.cover
            ? '<img src="' + esc(game.cover) + '" alt="' + esc(game.title) + '" draggable="false" class="w-full h-full object-cover object-center bg-zinc-950">'
            : '<div class="w-full h-full flex items-center justify-center text-zinc-600 font-bold uppercase text-xs text-center p-2">' + esc(game.title) + '</div>';
        const remove = editable
            ? '<button type="button" class="top5-remove absolute top-1 right-1 w-6 h-6 rounded-sm bg-black/70 hover:bg-red-700 text-white text-xs font-black leading-none z-10" data-remove="' + i + '" aria-label="Remover ' + esc(game.title) + ' do Top 5">✕</button>'
            : '';
        const border = i === 0 ? 'border-amber-500/70' : 'border-zinc-800';
        return '<div class="top5-card relative w-full h-full bg-zinc-900 border-2 ' + border + ' rounded-sm overflow-hidden shadow-xl ' + (editable ? 'cursor-grab active:cursor-grabbing' : '') + '" data-game-id="' + game.id + '">'
            + '<a href="' + href + '" draggable="false" class="block w-full h-full" title="' + esc(game.title) + '">' + cover + '</a>'
            + remove
            + '<div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/90 to-transparent px-2 pt-6 pb-1.5 pointer-events-none">'
            + '<p class="text-[11px] sm:text-xs font-bold text-white line-clamp-2 leading-tight">' + esc(game.title) + '</p></div>'
            + '</div>';
    }

    function slotHtml(game, i) {
        const empty = !game;
        const label = '<div class="text-center text-lg sm:text-2xl font-black ' + rankClasses(i) + ' mb-1">#' + (i + 1) + '</div>';
        const frame = '<div class="relative aspect-[3/4]">';
        let inner;
        if (empty) {
            inner = editable
                ? '<button type="button" data-add="' + i + '" class="absolute inset-0 w-full h-full border-2 border-dashed border-zinc-700 hover:border-violet-500 hover:text-violet-300 text-zinc-600 rounded-sm flex flex-col items-center justify-center gap-1 transition-colors">'
                  + '<span class="text-3xl font-black leading-none">+</span><span class="text-[10px] font-bold uppercase tracking-wider">Adicionar</span></button>'
                : '<div class="absolute inset-0 border-2 border-dashed border-zinc-800 rounded-sm"></div>';
        } else {
            inner = '';
        }
        // A zona de soltar fica por cima e só tem o card como filho (o Sortable precisa disso).
        const drop = '<div class="top5-drop absolute inset-0 ' + (empty ? 'pointer-events-none' : '') + '" data-pos="' + i + '">' + (empty ? '' : cardHtml(game, i)) + '</div>';
        return '<div class="top5-slot">' + label + frame + inner + drop + '</div></div>';
    }

    function render() {
        sortables.forEach((s) => s.destroy());
        sortables = [];

        grid.innerHTML = slots.map((g, i) => slotHtml(g, i)).join('');

        if (!editable) return;

        grid.querySelectorAll('[data-add]').forEach((btn) => {
            btn.addEventListener('click', () => openPicker(parseInt(btn.dataset.add, 10)));
        });
        grid.querySelectorAll('[data-remove]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                slots[parseInt(btn.dataset.remove, 10)] = null;
                render();
                persist();
            });
        });

        if (typeof Sortable === 'undefined') return;
        grid.querySelectorAll('.top5-drop').forEach((zone) => {
            sortables.push(new Sortable(zone, {
                group: 'top5',
                draggable: '.top5-card',
                filter: '.top5-remove',
                preventOnFilter: false,
                animation: 150,
                emptyInsertThreshold: 40,
                ghostClass: 'top5-ghost',
                onAdd(evt) {
                    // Card veio da biblioteca (não de outro slot do Top 5): substitui o jogo do slot
                    if (!evt.from.hasAttribute('data-top5-source')) return;
                    const to = parseInt(evt.to.dataset.pos, 10);
                    const game = gameFromLibraryCard(evt.item);
                    evt.item.remove(); // tira o card da biblioteca que o Sortable jogou no slot
                    if (!game || Number.isNaN(to)) { setTimeout(render, 0); return; }
                    placeGame(to, game);
                },
                // Arrastando entre slots do Top 5: mostra a troca antes de soltar
                // (o jogo do slot de destino aparece no slot de onde o card saiu)
                onMove(evt) {
                    showSwapPreview(evt.from, evt.to);
                    return true;
                },
                onEnd(evt) {
                    clearSwapPreview();
                    const from = parseInt(evt.from.dataset.pos, 10);
                    const to = parseInt(evt.to.dataset.pos, 10);
                    if (from !== to && !Number.isNaN(from) && !Number.isNaN(to)) {
                        const tmp = slots[from];
                        slots[from] = slots[to];
                        slots[to] = tmp;
                        persist();
                    }
                    // Redesenha a partir do estado (garante 1 card por slot e rank certo)
                    setTimeout(render, 0);
                },
            }));
        });
    }

    // ---------- Prévia da troca entre slots ----------

    function clearSwapPreview() {
        grid.querySelectorAll('.top5-swap-preview').forEach((el) => el.remove());
        grid.querySelectorAll('.top5-swapping').forEach((el) => el.classList.remove('top5-swapping'));
    }

    function showSwapPreview(fromZone, toZone) {
        clearSwapPreview();
        if (!fromZone || !toZone || fromZone === toZone) return;
        if (!toZone.classList.contains('top5-drop')) return;
        // O card que já está no slot de destino (não o que está sendo arrastado)
        const target = Array.from(toZone.children).find((c) => c.classList.contains('top5-card') && !c.classList.contains('top5-ghost'));
        if (!target) return; // slot vazio: só move
        const preview = target.cloneNode(true);
        preview.classList.remove('top5-card'); // não vira item arrastável
        preview.classList.add('top5-swap-preview');
        preview.querySelectorAll('[data-remove]').forEach((b) => b.remove());
        fromZone.appendChild(preview);
        toZone.classList.add('top5-swapping');
    }

    // ---------- Salvar ----------

    function persist() {
        saveQueue = saveQueue.then(async () => {
            const payload = slots.map((g) => (g ? g.id : null));
            try {
                const res = await fetch('index.php?action=top_games_save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
                    body: JSON.stringify({ slots: payload }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Não foi possível salvar.');
                }
                lastSaved = clone(slots);
                toast('Top 5 salvo');
            } catch (err) {
                slots = clone(lastSaved);
                render();
                toast(err.message || 'Não foi possível salvar.', true);
            }
        });
    }

    // ---------- Seletor de jogo (modal) ----------

    function openPicker(pos) {
        closePicker();

        const modal = document.createElement('div');
        modal.id = 'top5Picker';
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70';
        modal.innerHTML =
            '<div class="w-full max-w-lg max-h-[85vh] flex flex-col bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-2xl" role="dialog" aria-modal="true" aria-label="Escolher jogo para o rank ' + (pos + 1) + '">'
            + '<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">'
            + '<h3 class="font-black uppercase tracking-tight text-white">Escolher o <span class="' + rankClasses(pos) + '">#' + (pos + 1) + '</span></h3>'
            + '<button type="button" data-close class="text-zinc-400 hover:text-white text-xl leading-none" aria-label="Fechar">✕</button></div>'
            + '<div class="p-4 border-b border-zinc-800"><input type="search" data-q placeholder="Buscar na sua biblioteca..." autocomplete="off" class="w-full bg-zinc-950 border border-zinc-700 focus:border-violet-500 outline-none rounded-sm px-3 py-2 text-sm text-white"></div>'
            + '<ul data-results class="flex-1 overflow-y-auto divide-y divide-zinc-800"></ul></div>';
        document.body.appendChild(modal);

        const input = modal.querySelector('[data-q]');
        const list = modal.querySelector('[data-results]');
        let timer = null;
        let reqId = 0;

        modal.addEventListener('click', (e) => { if (e.target === modal) closePicker(); });
        modal.querySelector('[data-close]').addEventListener('click', closePicker);
        document.addEventListener('keydown', onKey);

        async function load(q) {
            const id = ++reqId;
            list.innerHTML = '<li class="p-4 text-sm text-zinc-500">Buscando...</li>';
            try {
                const res = await fetch('index.php?action=top_games_library&q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (id !== reqId) return;
                if (!data.success || !data.games.length) {
                    list.innerHTML = '<li class="p-4 text-sm text-zinc-500">Nenhum jogo encontrado na sua biblioteca.</li>';
                    return;
                }
                list.innerHTML = data.games.map((g) => {
                    const at = slots.findIndex((s) => s && s.id === g.id);
                    const note = at >= 0 ? '<span class="text-[10px] font-bold uppercase text-zinc-500">hoje no #' + (at + 1) + '</span>' : '';
                    const thumb = g.cover
                        ? '<img src="' + esc(g.cover) + '" alt="" class="w-10 h-14 object-cover rounded-sm bg-zinc-950 shrink-0">'
                        : '<div class="w-10 h-14 rounded-sm bg-zinc-800 shrink-0"></div>';
                    return '<li><button type="button" data-pick="' + g.id + '" class="w-full flex items-center gap-3 px-4 py-2 text-left hover:bg-zinc-800 transition-colors">'
                        + thumb + '<span class="flex-1 min-w-0"><span class="block text-sm font-bold text-white truncate">' + esc(g.title) + '</span>' + note + '</span></button></li>';
                }).join('');

                list.querySelectorAll('[data-pick]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const game = data.games.find((g) => String(g.id) === btn.dataset.pick);
                        if (game) pick(pos, game);
                    });
                });
            } catch (err) {
                if (id === reqId) list.innerHTML = '<li class="p-4 text-sm text-red-400">Erro ao buscar. Tente novamente.</li>';
            }
        }

        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => load(input.value.trim()), 250);
        });

        load('');
        input.focus();
    }

    function onKey(e) { if (e.key === 'Escape') closePicker(); }

    function closePicker() {
        document.removeEventListener('keydown', onKey);
        document.getElementById('top5Picker')?.remove();
    }

    function pick(pos, game) {
        closePicker();
        placeGame(pos, game);
    }

    // Coloca um jogo no rank `pos`. O jogo que estava ali sai do Top 5.
    // Se o jogo já estava em outro rank, ele muda de lugar (não duplica).
    function placeGame(pos, game) {
        const old = slots.findIndex((s) => s && s.id === game.id);
        if (old === pos) { setTimeout(render, 0); return; }
        if (old >= 0) slots[old] = null;
        slots[pos] = { id: game.id, title: game.title, cover: game.cover };
        setTimeout(render, 0);
        persist();
    }

    // ---------- Arrastar da biblioteca para o Top 5 ----------

    function gameFromLibraryCard(el) {
        const card = el && el.closest ? el.closest('[data-top5-game]') : null;
        if (!card) return null;
        const id = parseInt(card.dataset.gameId, 10);
        if (!id) return null;
        return { id, title: card.dataset.gameTitle || '', cover: card.dataset.gameCover || null };
    }

    let librarySortable = null;

    // Destaca os slots enquanto um jogo da biblioteca está sendo arrastado
    if (editable && !document.getElementById('top5ReceivingStyle')) {
        const style = document.createElement('style');
        style.id = 'top5ReceivingStyle';
        style.textContent =
            // Enquanto arrasta da biblioteca, o Top 5 "gruda" no topo da tela para estar sempre alcançável
            // (margens negativas compensam o padding, então nada pula de lugar)
            '#top5.top5-receiving{position:sticky;top:8px;z-index:40;background:rgba(9,9,11,.96);padding:8px;margin:-8px -8px calc(2.5rem - 8px);border-radius:4px;box-shadow:0 12px 32px rgba(0,0,0,.7)}'
            + '#top5.top5-receiving .top5-drop{outline:2px dashed rgb(139 92 246);outline-offset:2px;border-radius:2px;pointer-events:auto}'
            // Prévia do card que está chegando: cobre o slot inteiro...
            + '#top5 .top5-drop > .top5-ghost{position:absolute;inset:0;opacity:.85;overflow:hidden;z-index:20;outline:2px solid rgb(139 92 246);border-radius:2px}'
            // ...e o jogo que já estava no slot some da prévia (é ele que vai sair)
            + '#top5 .top5-drop:has(> .top5-ghost) > :not(.top5-ghost){visibility:hidden}'
            + '#biblioteca .top5-ghost{opacity:.4}'
            // Prévia da troca: o jogo do destino aparece apagadinho no slot de origem
            + '#top5 .top5-swap-preview{position:absolute;inset:0;opacity:.6;pointer-events:none;z-index:10;outline:2px dashed rgb(139 92 246);border-radius:2px}';
        document.head.appendChild(style);
    }

    function bindLibrary() {
        if (librarySortable) { librarySortable.destroy(); librarySortable = null; }
        if (!editable || typeof Sortable === 'undefined') return;
        const source = document.querySelector('#biblioteca [data-top5-source]');
        if (!source) return;

        source.querySelectorAll('[data-top5-game] a, [data-top5-game] img').forEach((el) => {
            el.setAttribute('draggable', 'false');
        });

        librarySortable = new Sortable(source, {
            group: { name: 'top5', pull: 'clone', put: false },
            sort: false,
            draggable: '[data-top5-game]',
            animation: 150,
            ghostClass: 'top5-ghost',
            // No celular só começa a arrastar depois de segurar um pouco (pra não atrapalhar a rolagem)
            delay: 200,
            delayOnTouchOnly: true,
            scroll: true,
            scrollSensitivity: 120,
            scrollSpeed: 25,
            onStart() { root.classList.add('top5-receiving'); },
            onEnd() { root.classList.remove('top5-receiving'); },
        });
    }

    // A biblioteca é trocada inteira pelo profile-library.js (filtro/paginação), então religa quando mudar.
    const librarySection = document.getElementById('biblioteca');
    if (librarySection && librarySection.parentNode && editable) {
        new MutationObserver(() => bindLibrary())
            .observe(librarySection.parentNode, { childList: true });
    }

    render();
    bindLibrary();
})();
