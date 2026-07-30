document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('liveSearchInput');
    const grid = document.getElementById('resultsGrid');
    const title = document.getElementById('searchTitle');
    const spinner = document.getElementById('searchSpinner');
    
    if (!input || !grid || !title) return;

    let typingTimer;
    const doneTypingInterval = 350; // debounce mais curto: menos espera após parar de digitar

    // Cache local simples (evita rebater a mesma busca na API se o usuário digitar/apagar/redigitar)
    const resultsCache = new Map();

    // Cancela requisições antigas ainda pendentes, evitando resultados
    // desatualizados "chegando atrasados" e sobrescrevendo os mais novos
    let activeController = null;

    input.addEventListener('input', () => {
        clearTimeout(typingTimer);
        const query = input.value.trim();

        if (query.length >= 3) {
            title.style.display = 'block';
            title.innerHTML = `Buscando por "<span class="text-violet-400">${query}</span>"... ⏳`;

            // Resposta instantânea se já tivermos essa busca em cache
            const cached = resultsCache.get(query.toLowerCase());
            if (cached) {
                renderResults(query, cached);
            }

            typingTimer = setTimeout(() => {
                fetchGames(query);
            }, doneTypingInterval);
        } else if (query.length === 0) {
            title.style.display = 'none';
            grid.innerHTML = '';
            if (activeController) activeController.abort();
        }
    });

    function renderResults(query, results) {
        if (results.length > 0) {
            title.innerHTML = `Resultados para "<span class="text-violet-400">${query}</span>"`;
            renderGames(results);
        } else {
            title.innerHTML = `Nenhum resultado 📡`;
            grid.innerHTML = `
                <div class="col-span-full bg-zinc-900 border-2 border-zinc-800 rounded-sm p-12 text-center shadow-xl mt-4">
                    <div class="text-zinc-700 mb-4 text-5xl">📡</div>
                    <h3 class="text-xl font-black text-white uppercase tracking-tight mb-2">Sinal Perdido</h3>
                    <p class="text-zinc-400 font-medium">Nenhum jogo encontrado para "<strong>${query}</strong>".</p>
                </div>
            `;
        }
    }

    async function fetchGames(query) {
        // Cancela a busca anterior ainda em andamento (o usuário já digitou algo novo)
        if (activeController) activeController.abort();
        activeController = new AbortController();

        if (spinner) spinner.classList.remove('hidden');

        try {
            const response = await fetch(`index.php?action=ajax_search&q=${encodeURIComponent(query)}`, {
                signal: activeController.signal
            });
            const data = await response.json();
            const results = data.results || [];

            resultsCache.set(query.toLowerCase(), results);
            renderResults(query, results);
        } catch (error) {
            if (error.name === 'AbortError') return; // busca cancelada porque uma nova começou, ignora
            console.error("Erro ao buscar jogos:", error);
            title.innerHTML = "Erro na conexão. Tente novamente.";
        } finally {
            if (spinner) spinner.classList.add('hidden');
        }
    }

    function renderGames(games) {
        grid.innerHTML = games.map(game => {
            const cover = game.background_image 
                ? `<img src="${game.background_image}" alt="${game.name}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">` 
                : `<div class="w-full h-full flex items-center justify-center text-zinc-700 font-bold uppercase">Sem Capa</div>`;

            const year = game.released ? game.released.substring(0, 4) : 'N/A';
            const genreNames = game.genres && game.genres.length > 0 ? game.genres.map(g => g.name) : ['Desconhecido'];
            const genres = genreNames.join(' • ');
            const genresForSubmit = genreNames.join(', ');
            const platforms = game.platforms ? game.platforms.map(p => p.platform.name).join(', ') : '';

            const safeName = game.name.replace(/"/g, '&quot;');
            const safePlatforms = platforms.replace(/"/g, '&quot;');
            const safeGenres = genresForSubmit.replace(/"/g, '&quot;');

            return `
            <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl flex flex-col overflow-hidden group">
                <div class="h-48 sm:h-56 bg-zinc-950 border-b-2 border-zinc-800 overflow-hidden relative">
                    ${cover}
                    <div class="absolute top-2 right-2 bg-zinc-900 text-zinc-300 text-[10px] font-black uppercase tracking-widest px-2 py-1 border border-zinc-700 rounded-sm">
                        ${year}
                    </div>
                </div>
                <div class="p-4 flex flex-col flex-1">
                    <h3 class="font-bold text-white mb-2 leading-tight line-clamp-2 text-lg" title="${safeName}">
                        ${safeName}
                    </h3>
                    <p class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-4 line-clamp-1">
                        ${genres}
                    </p>
                    <form action="index.php?action=add_game" method="post" class="mt-auto pt-4 border-t-2 border-zinc-800">
                        <input type="hidden" name="csrf_token" value="${document.querySelector('meta[name="csrf-token"]')?.content || ''}">
                        <input type="hidden" name="external_id" value="${game.id}">
                        <input type="hidden" name="title" value="${safeName}">
                        <input type="hidden" name="cover" value="${game.background_image || ''}">
                        <input type="hidden" name="platform" value="${safePlatforms}">
                        <input type="hidden" name="genre" value="${safeGenres}">
                        <input type="hidden" name="release_date" value="${game.released || ''}">
                        <button type="submit" class="w-full bg-violet-600 hover:bg-violet-500 text-white font-bold uppercase tracking-wider py-3 rounded-sm transition-colors text-xs flex items-center justify-center gap-2">
                            <span class="text-base leading-none">✚</span> Adicionar
                        </button>
                    </form>
                </div>
            </div>`;
        }).join('');
    }
});