// profile-comments.js - Postar, responder e apagar comentários de perfil

(function () {
    const section = document.getElementById('profileComments');
    if (!section) return;

    const profileUserId = section.dataset.profileUserId;
    const commentsList = document.getElementById('commentsList');
    const noCommentsMsg = document.getElementById('noCommentsMsg');
    const commentsCount = document.getElementById('commentsCount');
    const newCommentForm = document.getElementById('newCommentForm');
    const replyFormTemplate = document.getElementById('replyFormTemplate');

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    // Mesmo estilo de toast usado no follow.js, pra não depender do
    // SweetAlert2 (que nem sempre está carregado nesta página).
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 20px;
            background-color: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            border-radius: 8px;
            z-index: 9999;
        `;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 3000);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Faz a textarea crescer junto com o conteúdo, em vez de ficar com
    // scroll interno quando a resposta é grande.
    function enableAutoGrow(textarea) {
        if (!textarea || textarea.dataset.autoGrow === '1') return;
        textarea.dataset.autoGrow = '1';
        textarea.style.overflow = 'hidden';
        textarea.style.resize = 'none';

        const resize = () => {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        };

        textarea.addEventListener('input', resize);
        resize();
    }

    function bumpCount(delta) {
        if (!commentsCount) return;
        commentsCount.textContent = String(Math.max(0, parseInt(commentsCount.textContent, 10) + delta));
    }

    function buildCommentArticle(comment, isReply) {
        const article = document.createElement('article');
        article.id = `comment-${comment.id}`;
        article.className = isReply ? 'flex gap-3 py-3 pl-10' : 'flex gap-3 py-4';
        article.dataset.commentId = comment.id;
        article.dataset.authorId = comment.author_id;

        const avatarSize = isReply ? 'w-8 h-8' : 'w-10 h-10';
        const avatarHtml = comment.avatar
            ? `<img src="${escapeHtml(comment.avatar.startsWith('http') ? comment.avatar : './uploads/profile/' + comment.avatar.split('/').pop())}" alt="${escapeHtml(comment.username)}" class="${avatarSize} rounded-sm object-cover shrink-0 bg-zinc-950">`
            : `<div class="${avatarSize} rounded-sm shrink-0 bg-zinc-800 flex items-center justify-center text-zinc-500 font-black uppercase">${escapeHtml((comment.username || '?').charAt(0))}</div>`;

        const canReply = !isReply;
        const actionsHtml = `
            <div class="flex items-center gap-4 mt-2">
                ${canReply ? '<button type="button" class="comment-reply-btn text-zinc-500 hover:text-violet-400 text-xs font-bold uppercase tracking-wide transition-colors">Responder</button>' : ''}
                <button type="button" class="comment-delete-btn text-zinc-500 hover:text-red-400 text-xs font-bold uppercase tracking-wide transition-colors">Apagar</button>
            </div>
        `;

        article.innerHTML = `
            ${avatarHtml}
            <div class="comment-body flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="index.php?action=profile&u=${encodeURIComponent(comment.username)}" class="text-white text-sm font-bold hover:text-violet-400 transition-colors">
                        ${escapeHtml(comment.display_name || comment.username)}
                    </a>
                    <time class="text-zinc-500 text-xs">agora mesmo</time>
                </div>
                <p class="comment-content text-zinc-300 text-sm mt-0.5 whitespace-pre-wrap break-words">${escapeHtml(comment.content)}</p>
                ${actionsHtml}
                ${!isReply ? `
                    <button type="button" class="comment-toggle-replies-btn flex items-center gap-1.5 text-violet-400 hover:text-violet-300 text-xs font-bold uppercase tracking-wide transition-colors mt-4 hidden" data-expanded="false">
                        <svg class="comment-toggle-chevron w-3.5 h-3.5 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                        Respostas (<span class="comment-replies-count">0</span>)
                    </button>
                    <div class="comment-replies mt-3 space-y-1 border-l-2 border-zinc-800 hidden"></div>
                ` : ''}
            </div>
        `;

        return article;
    }

    function attachReplyForm(article) {
        if (article.querySelector('.comment-reply-form')) return;

        const fragment = replyFormTemplate.content.cloneNode(true);
        const form = fragment.querySelector('.comment-reply-form');
        const content = article.querySelector(':scope > .comment-body');
        content.appendChild(form);
        const textarea = form.querySelector('textarea');
        enableAutoGrow(textarea);
        textarea.focus();
    }

    async function postComment({ content, parentId }) {
        const dados = new FormData();
        dados.set('csrf_token', csrfToken());
        dados.set('profile_user_id', profileUserId);
        dados.set('content', content);
        if (parentId) dados.set('parent_id', parentId);

        const response = await fetch('index.php?action=profile_comment_add', {
            method: 'POST',
            body: dados,
        });

        if (!response.ok) throw new Error('Erro ao publicar comentário');
        return response.json();
    }

    if (newCommentForm) {
        enableAutoGrow(newCommentForm.querySelector('textarea[name="content"]'));

        newCommentForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const textarea = newCommentForm.querySelector('textarea[name="content"]');
            const content = textarea.value.trim();
            if (!content) return;

            const submitBtn = newCommentForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            try {
                const data = await postComment({ content });
                if (!data.success) throw new Error(data.message || 'Erro ao publicar comentário');

                const article = buildCommentArticle(data.comment, false);
                commentsList.prepend(article);
                if (noCommentsMsg) noCommentsMsg.classList.add('hidden');
                bumpCount(1);
                textarea.value = '';
                textarea.style.height = 'auto';
            } catch (error) {
                console.error('Erro:', error);
                showNotification(error.message, 'error');
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

    // Delegação de eventos: cobre tanto os comentários já renderizados no
    // servidor quanto os que forem adicionados dinamicamente pelo JS.
    commentsList.addEventListener('click', async function (event) {
        const toggleBtn = event.target.closest('.comment-toggle-replies-btn');
        if (toggleBtn) {
            const article = toggleBtn.closest('article[data-comment-id]');
            const repliesDiv = article.querySelector('.comment-replies');
            const chevron = toggleBtn.querySelector('.comment-toggle-chevron');
            const expanded = toggleBtn.dataset.expanded === 'true';

            repliesDiv.classList.toggle('hidden', expanded);
            toggleBtn.dataset.expanded = expanded ? 'false' : 'true';
            if (chevron) chevron.classList.toggle('rotate-180', !expanded);
            return;
        }

        const replyBtn = event.target.closest('.comment-reply-btn');
        if (replyBtn) {
            const article = replyBtn.closest('article[data-comment-id]');
            attachReplyForm(article);
            return;
        }

        const cancelBtn = event.target.closest('.comment-reply-cancel');
        if (cancelBtn) {
            cancelBtn.closest('.comment-reply-form').remove();
            return;
        }

        const deleteBtn = event.target.closest('.comment-delete-btn');
        if (deleteBtn) {
            if (!confirm('Apagar este comentário?')) return;

            const article = deleteBtn.closest('article[data-comment-id]');
            const commentId = article.dataset.commentId;
            const hasReplies = article.querySelector('.comment-replies') && article.querySelector('.comment-replies').children.length > 0;

            const dados = new FormData();
            dados.set('csrf_token', csrfToken());
            dados.set('comment_id', commentId);

            try {
                const response = await fetch('index.php?action=profile_comment_delete', {
                    method: 'POST',
                    body: dados,
                });
                if (!response.ok) throw new Error('Erro ao apagar comentário');
                const data = await response.json();
                if (!data.success) throw new Error(data.message || 'Erro ao apagar comentário');

                if (hasReplies) {
                    // Mantém a thread: vira um placeholder "removido" em vez de sumir.
                    article.querySelector('.comment-content').innerHTML = '<span class="italic text-zinc-600">Comentário removido.</span>';
                    const actions = article.querySelector('.flex.items-center.gap-4.mt-2');
                    if (actions) actions.remove();
                    bumpCount(-1);
                } else {
                    const parentReplies = article.closest('.comment-replies');
                    article.remove();
                    bumpCount(-1);

                    if (parentReplies) {
                        // Era uma resposta: atualiza o contador "Respostas (N)" do comentário pai.
                        const parentToggleBtn = parentReplies.previousElementSibling;
                        if (parentToggleBtn && parentToggleBtn.classList.contains('comment-toggle-replies-btn')) {
                            const countSpan = parentToggleBtn.querySelector('.comment-replies-count');
                            const newCount = Math.max(0, parseInt(countSpan.textContent, 10) - 1);
                            countSpan.textContent = String(newCount);
                            if (newCount === 0) parentToggleBtn.classList.add('hidden');
                        }
                    } else if (commentsList.children.length === 0 && noCommentsMsg) {
                        noCommentsMsg.classList.remove('hidden');
                    }
                }
            } catch (error) {
                console.error('Erro:', error);
                showNotification(error.message, 'error');
            }
        }
    });

    commentsList.addEventListener('submit', async function (event) {
        const form = event.target.closest('.comment-reply-form');
        if (!form) return;
        event.preventDefault();

        const article = form.closest('article[data-comment-id]');
        const parentId = article.dataset.commentId;
        const textarea = form.querySelector('textarea');
        const content = textarea.value.trim();
        if (!content) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        try {
            const data = await postComment({ content, parentId });
            if (!data.success) throw new Error(data.message || 'Erro ao publicar resposta');

            const reply = buildCommentArticle(data.comment, true);
            const repliesDiv = article.querySelector('.comment-replies');
            repliesDiv.appendChild(reply);

            // Mostra a resposta recém-enviada na hora, em vez de deixar
            // escondida atrás do botão "expandir".
            const toggleBtn = article.querySelector('.comment-toggle-replies-btn');
            const countSpan = toggleBtn.querySelector('.comment-replies-count');
            countSpan.textContent = String(parseInt(countSpan.textContent, 10) + 1);
            toggleBtn.classList.remove('hidden');
            repliesDiv.classList.remove('hidden');
            toggleBtn.dataset.expanded = 'true';
            const chevron = toggleBtn.querySelector('.comment-toggle-chevron');
            if (chevron) chevron.classList.add('rotate-180');

            bumpCount(1);
            form.remove();
        } catch (error) {
            console.error('Erro:', error);
            showNotification(error.message, 'error');
        } finally {
            submitBtn.disabled = false;
        }
    });

    // Ao chegar via link com #comment-ID (ex: clicando numa notificação),
    // garante que o comentário-alvo fique visível: se for uma resposta,
    // o container de respostas começa fechado, então precisa ser aberto
    // antes de rolar até ele.
    function goToTargetComment() {
        const hash = window.location.hash;
        if (!hash.startsWith('#comment-')) return;

        const target = document.querySelector(hash);
        if (!target) return;

        const repliesDiv = target.closest('.comment-replies');
        if (repliesDiv && repliesDiv.classList.contains('hidden')) {
            const toggleBtn = repliesDiv.previousElementSibling;
            if (toggleBtn && toggleBtn.classList.contains('comment-toggle-replies-btn')) {
                repliesDiv.classList.remove('hidden');
                toggleBtn.dataset.expanded = 'true';
                const chevron = toggleBtn.querySelector('.comment-toggle-chevron');
                if (chevron) chevron.classList.add('rotate-180');
            }
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('ring-2', 'ring-violet-500');
        setTimeout(() => target.classList.remove('ring-2', 'ring-violet-500'), 2500);
    }

    goToTargetComment();
})();
