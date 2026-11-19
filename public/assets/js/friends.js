// friends.js - botões de seguir (em qualquer página) e modal de amigos do perfil
//
// 1) Qualquer botão com [data-follow-user="<id>"] vira um seguir/deixar de seguir inline.
// 2) Se a página tiver #friendsModal, abre a lista completa (abas, busca, paginação) via
//    index.php?action=friends_list.

(function () {
    'use strict';

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function toast(message) {
        const el = document.createElement('div');
        el.className = 'mgl-toast';
        el.setAttribute('role', 'alert');
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }

    // ---------------------------------------------------------------
    // Seguir / deixar de seguir inline
    // ---------------------------------------------------------------

    function setFollowState(btn, following) {
        btn.dataset.following = String(following);
        btn.textContent = following ? 'Seguindo' : 'Seguir';
        btn.classList.toggle('fbtn--following', following);
        btn.classList.toggle('fbtn--follow', !following);
        btn.setAttribute('aria-pressed', String(following));
    }

    async function toggleFollow(btn) {
        const userId = btn.dataset.followUser;
        const isFollowing = btn.dataset.following === 'true';

        btn.disabled = true;
        try {
            const response = await fetch('index.php?action=follow_toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken(),
                },
                body: JSON.stringify({ user_id: userId, is_following: isFollowing }),
            });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Não foi possível concluir a ação.');

            setFollowState(btn, !isFollowing);
            document.dispatchEvent(new CustomEvent('mgl:follow-changed', {
                detail: {
                    userId: userId,
                    following: !isFollowing,
                    followsMe: btn.dataset.followsMe === '1',
                },
            }));
        } catch (error) {
            toast(error.message || 'Não foi possível concluir a ação.');
        } finally {
            btn.disabled = false;
        }
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-follow-user]');
        if (!btn) return;
        e.preventDefault();
        toggleFollow(btn);
    });

    // ---------------------------------------------------------------
    // Modal de amigos
    // ---------------------------------------------------------------

    const modal = document.getElementById('friendsModal');
    if (!modal) return;

    const username = modal.dataset.username;
    const isOwner = modal.dataset.owner === '1';
    const panel = modal.querySelector('.fmodal__panel');
    const list = modal.querySelector('.fmodal__list');
    const statusEl = modal.querySelector('.fmodal__status');
    const moreBtn = modal.querySelector('.fmodal__more');
    const searchInput = modal.querySelector('.fmodal__search input');
    const tabs = Array.from(modal.querySelectorAll('[data-tab]'));

    const state = { tab: 'followers', q: '', offset: 0, seq: 0 };
    let opener = null;
    let searchTimer = null;

    function bumpCount(name, delta) {
        document.querySelectorAll('[data-count="' + name + '"]').forEach((el) => {
            const current = parseInt(el.textContent, 10) || 0;
            el.textContent = Math.max(0, current + delta);
        });
    }

    function buildRow(user) {
        const li = document.createElement('li');
        li.className = 'person';

        const link = document.createElement('a');
        link.className = 'person__who';
        link.href = 'index.php?action=profile&u=' + encodeURIComponent(user.username);

        if (user.avatar_url) {
            const img = document.createElement('img');
            img.className = 'person__avatar';
            img.src = user.avatar_url;
            img.alt = '';
            img.loading = 'lazy';
            link.appendChild(img);
        } else {
            const initial = document.createElement('div');
            initial.className = 'person__avatar person__avatar--initial';
            initial.textContent = user.username.charAt(0);
            link.appendChild(initial);
        }

        const text = document.createElement('div');
        text.className = 'person__text';

        const name = document.createElement('span');
        name.className = 'person__name';
        name.textContent = user.display_name;
        text.appendChild(name);

        const sub = document.createElement('span');
        sub.className = 'person__sub';
        sub.textContent = '@' + user.username;
        if (user.follows_me && !user.is_me) {
            const tag = document.createElement('span');
            tag.className = 'person__tag';
            tag.textContent = 'Segue você';
            sub.appendChild(tag);
        }
        text.appendChild(sub);

        link.appendChild(text);
        li.appendChild(link);

        if (user.can_follow) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'fbtn';
            btn.dataset.followUser = String(user.id);
            btn.dataset.followsMe = user.follows_me ? '1' : '0';
            setFollowState(btn, user.i_follow);
            li.appendChild(btn);
        }

        return li;
    }

    function emptyMessage() {
        if (state.q) return 'Ninguém encontrado com esse nome.';
        switch (state.tab) {
            case 'following': return 'Ainda não segue ninguém.';
            case 'mutual': return 'Nenhum amigo mútuo por enquanto.';
            case 'common': return 'Vocês não seguem ninguém em comum ainda.';
            default: return 'Ainda não tem seguidores.';
        }
    }

    function showStatus(message) {
        statusEl.textContent = message;
        statusEl.hidden = !message;
    }

    // Cache por aba (só sem busca): trocar de aba mostra a lista na hora, sem recarregar.
    // Ao abrir o modal, as outras abas são pré-carregadas em segundo plano.
    const cache = {};
    const pending = {};

    async function fetchPage(tab, q, offset) {
        const url = 'index.php?action=friends_list'
            + '&u=' + encodeURIComponent(username)
            + '&tab=' + encodeURIComponent(tab)
            + '&q=' + encodeURIComponent(q)
            + '&offset=' + offset;
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Erro');
        return data;
    }

    function ensureTab(tab) {
        if (cache[tab]) return Promise.resolve(cache[tab]);
        if (!pending[tab]) {
            pending[tab] = fetchPage(tab, '', 0)
                .then((data) => {
                    cache[tab] = { users: data.users, offset: data.next_offset, hasMore: data.has_more };
                    return cache[tab];
                })
                .finally(() => { delete pending[tab]; });
        }
        return pending[tab];
    }

    function render(users, hasMore) {
        list.replaceChildren();
        users.forEach((user) => list.appendChild(buildRow(user)));
        moreBtn.hidden = !hasMore;
        showStatus(users.length ? '' : emptyMessage());
    }

    function showError() {
        list.replaceChildren();
        moreBtn.hidden = true;
        showStatus('Não foi possível carregar a lista. Tente novamente.');
    }

    // Aba sem busca: vem do cache se já tiver, senão busca (e guarda)
    function showTab() {
        const tab = state.tab;
        const seq = ++state.seq;

        if (cache[tab]) {
            render(cache[tab].users, cache[tab].hasMore);
            return;
        }

        list.replaceChildren();
        moreBtn.hidden = true;
        showStatus('Carregando...');
        ensureTab(tab)
            .then((entry) => { if (seq === state.seq) render(entry.users, entry.hasMore); })
            .catch(() => { if (seq === state.seq) showError(); });
    }

    // Busca por nome: sempre vai ao servidor, não entra no cache
    async function runSearch() {
        const seq = ++state.seq;
        state.offset = 0;
        list.replaceChildren();
        moreBtn.hidden = true;
        showStatus('Buscando...');

        try {
            const data = await fetchPage(state.tab, state.q, 0);
            if (seq !== state.seq) return; // chegou uma resposta mais nova enquanto esta voltava
            state.offset = data.next_offset;
            render(data.users, data.has_more);
        } catch (error) {
            if (seq === state.seq) showError();
        }
    }

    async function loadMore() {
        const tab = state.tab;
        const q = state.q;
        const seq = state.seq;
        const offset = q ? state.offset : cache[tab].offset;

        moreBtn.disabled = true;
        try {
            const data = await fetchPage(tab, q, offset);
            if (seq !== state.seq) return;

            data.users.forEach((user) => list.appendChild(buildRow(user)));
            moreBtn.hidden = !data.has_more;

            if (q) {
                state.offset = data.next_offset;
            } else {
                cache[tab].users.push(...data.users);
                cache[tab].offset = data.next_offset;
                cache[tab].hasMore = data.has_more;
            }
        } catch (error) {
            toast('Não foi possível carregar mais pessoas.');
        } finally {
            moreBtn.disabled = false;
        }
    }

    function setTab(tab) {
        state.tab = tab;
        state.q = '';
        searchInput.value = '';
        tabs.forEach((btn) => btn.setAttribute('aria-selected', String(btn.dataset.tab === tab)));
        showTab();
    }

    function focusables() {
        return Array.from(modal.querySelectorAll('button, a[href], input')).filter((el) => !el.disabled && el.offsetParent !== null);
    }

    function open(tab, trigger) {
        opener = trigger || document.activeElement;
        modal.hidden = false;
        document.documentElement.classList.add('fmodal-open');
        setTab(tabs.some((t) => t.dataset.tab === tab) ? tab : 'followers');
        tabs.forEach((t) => { ensureTab(t.dataset.tab).catch(() => {}); });
        panel.focus();
    }

    function close() {
        modal.hidden = true;
        document.documentElement.classList.remove('fmodal-open');
        if (opener && typeof opener.focus === 'function') opener.focus();
    }

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-friends-open]');
        if (trigger) {
            e.preventDefault();
            open(trigger.dataset.friendsOpen, trigger);
            return;
        }
        if (e.target.closest('[data-fmodal-close]')) close();
    });

    modal.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
            return;
        }
        if (e.key !== 'Tab') return;

        const items = focusables();
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (e.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    tabs.forEach((btn) => btn.addEventListener('click', () => setTab(btn.dataset.tab)));
    moreBtn.addEventListener('click', loadMore);

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.q = searchInput.value.trim();
            if (state.q) runSearch();
            else showTab();
        }, 250);
    });

    // Mantém o cache e os contadores coerentes depois de seguir/deixar de seguir
    document.addEventListener('mgl:follow-changed', (e) => {
        Object.keys(cache).forEach((tab) => {
            cache[tab].users.forEach((user) => {
                if (String(user.id) === String(e.detail.userId)) user.i_follow = e.detail.following;
            });
        });

        if (!isOwner) return; // só as próprias listas mudam quando o dono do perfil age
        const delta = e.detail.following ? 1 : -1;
        bumpCount('following', delta);
        if (e.detail.followsMe) bumpCount('mutual', delta);
    });

    // Visitante seguiu/deixou de seguir o dono do perfil pelo botão do topo
    document.addEventListener('mgl:profile-follow-changed', (e) => {
        // as listas de quem segue esse perfil mudaram: descarta para recarregar na próxima vez
        delete cache.followers;
        delete cache.mutual;
        delete cache.common;

        const delta = e.detail.following ? 1 : -1;
        bumpCount('followers', delta);
        if (modal.dataset.followsMe === '1') bumpCount('mutual', delta);
    });
})();
