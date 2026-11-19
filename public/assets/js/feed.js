// feed.js - curtidas e filtro por tipo no feed de atividades

(function () {
    'use strict';

    const feedList = document.getElementById('feedList');
    if (!feedList) return;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    // ---------------------------------------------------------------
    // Curtir / descurtir
    // ---------------------------------------------------------------

    function paintLike(btn, liked, count) {
        btn.classList.toggle('is-liked', liked);
        btn.setAttribute('aria-pressed', String(liked));
        const countEl = btn.querySelector('.feed-like__count');
        if (countEl) countEl.textContent = count > 0 ? count : '';
    }

    feedList.addEventListener('click', async (e) => {
        const btn = e.target.closest('.feed-like');
        if (!btn) return;

        const countEl = btn.querySelector('.feed-like__count');
        const wasLiked = btn.classList.contains('is-liked');
        const wasCount = parseInt(countEl?.textContent, 10) || 0;

        // Atualização otimista: reflete na hora e corrige se o servidor discordar
        paintLike(btn, !wasLiked, Math.max(0, wasCount + (wasLiked ? -1 : 1)));

        try {
            const response = await fetch('index.php?action=activity_toggle_like', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken(),
                },
                body: 'activity_id=' + encodeURIComponent(btn.dataset.activityId),
            });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Erro ao curtir');

            paintLike(btn, data.liked, data.count);
        } catch (error) {
            console.error('Erro ao curtir atividade:', error);
            paintLike(btn, wasLiked, wasCount);
        }
    });

    // ---------------------------------------------------------------
    // Filtro por tipo (chips). Esconde também os dias que ficarem vazios.
    // ---------------------------------------------------------------

    const chips = Array.from(document.querySelectorAll('[data-feed-filter]'));
    const emptyMsg = document.getElementById('feedFilterEmpty');

    function applyFilter(type) {
        chips.forEach((chip) => chip.setAttribute('aria-pressed', String(chip.dataset.feedFilter === type)));

        let anyVisible = false;
        feedList.querySelectorAll('.feed-day').forEach((day) => {
            let dayVisible = false;
            day.querySelectorAll('[data-activity-type]').forEach((item) => {
                const show = !type || item.dataset.activityType === type;
                item.hidden = !show;
                if (show) dayVisible = true;
            });
            day.hidden = !dayVisible;
            if (dayVisible) anyVisible = true;
        });

        if (emptyMsg) emptyMsg.hidden = anyVisible;
    }

    chips.forEach((chip) => chip.addEventListener('click', () => applyFilter(chip.dataset.feedFilter)));
})();
