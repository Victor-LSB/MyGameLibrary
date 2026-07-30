// feed.js - Curtidas e filtro por tipo no feed de atividades

(function () {
    const feedList = document.getElementById('feedList');
    const feedTypeFilter = document.getElementById('feedTypeFilter');

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    // Curtir/descurtir atividade
    if (feedList) {
        feedList.addEventListener('click', async (e) => {
            const btn = e.target.closest('.feed-like-btn');
            if (!btn) return;

            const activityId = btn.dataset.activityId;
            const countEl = btn.querySelector('.feed-like-count');
            const wasLiked = btn.classList.contains('is-liked');

            // Atualização otimista: reflete a mudança na hora, sem esperar o servidor
            btn.classList.toggle('is-liked', !wasLiked);
            btn.classList.toggle('text-zinc-500', wasLiked);
            btn.classList.toggle('hover:text-pink-400', wasLiked);
            btn.classList.toggle('hover:bg-zinc-800', wasLiked);
            if (countEl) {
                const current = parseInt(countEl.textContent, 10) || 0;
                countEl.textContent = wasLiked ? Math.max(0, current - 1) : current + 1;
            }

            try {
                const response = await fetch('index.php?action=activity_toggle_like', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken(),
                    },
                    body: `activity_id=${encodeURIComponent(activityId)}`,
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Erro ao curtir');
                }

                // Sincroniza com o valor real do servidor
                btn.classList.toggle('is-liked', data.liked);
                btn.classList.toggle('text-zinc-500', !data.liked);
                btn.classList.toggle('hover:text-pink-400', !data.liked);
                btn.classList.toggle('hover:bg-zinc-800', !data.liked);
                if (countEl) countEl.textContent = data.count;
            } catch (error) {
                console.error('Erro ao curtir atividade:', error);
                // Desfaz a atualização otimista em caso de erro
                btn.classList.toggle('is-liked', wasLiked);
                btn.classList.toggle('text-zinc-500', !wasLiked);
                btn.classList.toggle('hover:text-pink-400', !wasLiked);
                btn.classList.toggle('hover:bg-zinc-800', !wasLiked);
                if (countEl) {
                    const current = parseInt(countEl.textContent, 10) || 0;
                    countEl.textContent = wasLiked ? current + 1 : Math.max(0, current - 1);
                }
            }
        });
    }

    // Filtro por tipo de atividade
    if (feedTypeFilter && feedList) {
        feedTypeFilter.addEventListener('change', () => {
            const selected = feedTypeFilter.value;
            const cards = feedList.querySelectorAll('[data-activity-type]');

            cards.forEach((card) => {
                const matches = !selected || card.dataset.activityType === selected;
                card.style.display = matches ? 'flex' : 'none';
            });
        });
    }
})();
