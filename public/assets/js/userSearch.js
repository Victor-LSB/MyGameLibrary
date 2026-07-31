// userSearch.js - Busca de usuários no navbar (dropdown com debounce, igual ao liveSearch.js)

document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('userSearchInput');
    const dropdown = document.getElementById('userSearchDropdown');

    if (!input || !dropdown) return;

    let typingTimer;
    const doneTypingInterval = 300;

    // Cache local simples, evita rebater a mesma busca ao digitar/apagar/redigitar
    const resultsCache = new Map();

    // Cancela requisições antigas ainda pendentes
    let activeController = null;

    input.addEventListener('input', () => {
        clearTimeout(typingTimer);
        const query = input.value.trim();

        if (query.length >= 2) {
            const cached = resultsCache.get(query.toLowerCase());
            if (cached) {
                renderResults(cached);
            } else {
                showMessage('Buscando...');
            }

            typingTimer = setTimeout(() => {
                fetchUsers(query);
            }, doneTypingInterval);
        } else {
            hideDropdown();
            if (activeController) activeController.abort();
        }
    });

    // Fecha o dropdown ao clicar fora
    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && e.target !== input) {
            hideDropdown();
        }
    });

    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 2 && dropdown.innerHTML.trim() !== '') {
            dropdown.classList.remove('hidden');
        }
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            hideDropdown();
            input.blur();
        }
    });

    function hideDropdown() {
        dropdown.classList.add('hidden');
    }

    function showMessage(text) {
        dropdown.innerHTML = `<div class="p-4 text-center text-zinc-500 text-sm font-medium">${text}</div>`;
        dropdown.classList.remove('hidden');
    }

    async function fetchUsers(query) {
        if (activeController) activeController.abort();
        activeController = new AbortController();

        try {
            const response = await fetch(`index.php?action=search_users&q=${encodeURIComponent(query)}`, {
                signal: activeController.signal
            });
            const data = await response.json();
            const results = data.results || [];

            resultsCache.set(query.toLowerCase(), results);
            renderResults(results);
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Erro ao buscar usuários:', error);
            showMessage('Erro na busca. Tente novamente.');
        }
    }

    function renderResults(users) {
        if (users.length === 0) {
            showMessage('Nenhum usuário encontrado.');
            return;
        }

        dropdown.innerHTML = users.map(user => {
            const displayName = user.display_name || user.username;
            const safeDisplayName = displayName.replace(/</g, '&lt;');
            const safeUsername = user.username.replace(/</g, '&lt;');
            const followersLabel = `${user.followers_count} seguidor${user.followers_count == 1 ? '' : 'es'}`;

            const avatar = user.avatar
                ? `<img src="${user.avatar.startsWith('http') ? user.avatar : './uploads/profile/' + user.avatar.split('/').pop()}" alt="${safeUsername}" class="w-10 h-10 rounded-sm object-cover shrink-0 bg-zinc-950">`
                : `<div class="w-10 h-10 rounded-sm shrink-0 bg-zinc-800 flex items-center justify-center text-zinc-500 font-black uppercase">${safeUsername.charAt(0)}</div>`;

            return `
                <a href="index.php?action=profile&u=${encodeURIComponent(user.username)}" class="flex items-center gap-3 p-3 hover:bg-zinc-800/60 transition-colors">
                    ${avatar}
                    <div class="min-w-0">
                        <p class="text-white text-sm font-bold truncate">${safeDisplayName}</p>
                        <p class="text-zinc-500 text-xs truncate">@${safeUsername} · ${followersLabel}</p>
                    </div>
                </a>`;
        }).join('');

        dropdown.classList.remove('hidden');
    }
});
