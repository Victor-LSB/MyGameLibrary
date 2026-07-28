// notifications.js - Gerenciar bell de notificações

(function () {
    const notificationBell = document.getElementById('notificationBell');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationsList = document.getElementById('notificationsList');
    const unreadCount = document.getElementById('unreadCount');
    const markAllReadBtn = document.getElementById('markAllRead');

    if (!notificationBell) return;

    // Toggle notification panel
    notificationBell.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationPanel.classList.toggle('hidden');
        if (!notificationPanel.classList.contains('hidden')) {
            loadNotifications();
        }
    });

    // Fechar ao clicar fora
    document.addEventListener('click', (e) => {
        if (!notificationBell.contains(e.target) && !notificationPanel.contains(e.target)) {
            notificationPanel.classList.add('hidden');
        }
    });

    // Marcar tudo como lido
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async () => {
            await fetch('index.php?action=notifications_mark_all_read', {
                method: 'POST',
            });
            loadNotifications();
        });
    }

    // Carregar notificações
    async function loadNotifications() {
        try {
            const response = await fetch('index.php?action=notifications_get');
            const data = await response.json();

            if (data.success) {
                displayNotifications(data.notifications);
                updateUnreadCount(data.unread_count);
            }
        } catch (error) {
            console.error('Erro ao carregar notificações:', error);
            notificationsList.innerHTML = '<p class="empty">Erro ao carregar notificações</p>';
        }
    }

    // Exibir notificações
    function displayNotifications(notifications) {
        if (notifications.length === 0) {
            notificationsList.innerHTML = '<p class="text-center py-8 text-zinc-500">Nenhuma notificação</p>';
            return;
        }

        notificationsList.innerHTML = notifications.map(notif => `
            <div class="notification-item ${notif.is_read ? 'read' : 'unread'}" data-id="${notif.id}">
                <img src="${notif.actor_avatar || 'assets/images/avatar-default.png'}" alt="${notif.actor_name}" class="notification-avatar">
                <div class="notification-content">
                    <p class="notification-message">${escapeHtml(notif.message)}</p>
                    <time class="notification-time">${formatDate(notif.created_at)}</time>
                </div>
                ${!notif.is_read ? `
                    <button class="notification-mark-read-btn" data-id="${notif.id}" title="Marcar como lido">
                        ✓
                    </button>
                ` : ''}
            </div>
        `).join('');

        // Event listeners para marcar individual como lido
        document.querySelectorAll('.notification-mark-read-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = btn.dataset.id;
                await markNotificationAsRead(id);
            });
        });

        // Permite clicar na notificação para ir para o jogo/review (opcional)
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                // Aqui você pode adicionar lógica para redirecionar
                // por exemplo: window.location.href = `/game/${notif.related_id}`;
                console.log('Clicou em notificação:', item.dataset.id);
            });
        });
    }

    // Marcar notificação como lida
    async function markNotificationAsRead(notificationId) {
        try {
            await fetch('index.php?action=notification_mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId }),
            });
            loadNotifications();
        } catch (error) {
            console.error('Erro ao marcar como lido:', error);
        }
    }

    // Atualizar contador
    function updateUnreadCount(count) {
        if (unreadCount) {
            unreadCount.textContent = count;
            unreadCount.style.display = count > 0 ? 'block' : 'none';
        }
    }

    // Formatar data de forma amigável
    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;

        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (seconds < 60) return 'agora';
        if (minutes < 60) return `${minutes}m atrás`;
        if (hours < 24) return `${hours}h atrás`;
        if (days < 7) return `${days}d atrás`;

        // Mostrar data completa se for muito antigo
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    // Escapar HTML para evitar XSS
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Carregar notificações ao abrir página
    updateUnreadCount(0);
    
    // Atualizar contador a cada 30 segundos (polling)
    setInterval(async () => {
        try {
            const response = await fetch('index.php?action=notifications_count');
            const data = await response.json();
            updateUnreadCount(data.unread_count);
        } catch (error) {
            console.error('Erro ao atualizar contagem:', error);
        }
    }, 30000);

    // Se o panel estiver aberto, atualizar notificações a cada 15 segundos
    setInterval(() => {
        if (!notificationPanel.classList.contains('hidden')) {
            loadNotifications();
        }
    }, 15000);

})();
