// follow.js - Gerenciar UI de follow/unfollow

(function () {
    const followBtn = document.getElementById('followBtn');
    
    if (!followBtn) return;

    followBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        
        const userId = followBtn.dataset.userId;
        const isFollowing = followBtn.dataset.isFollowing === 'true';
        
        // Desabilitar botão durante requisição
        const originalText = followBtn.textContent;
        followBtn.disabled = true;
        followBtn.textContent = 'Processando...';

        try {
            const response = await fetch('index.php?action=follow_toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    is_following: isFollowing,
                }),
            });

            const data = await response.json();

            if (data.success) {
                // Atualizar estado do botão
                if (isFollowing) {
                    followBtn.textContent = 'Seguir';
                    followBtn.dataset.isFollowing = 'false';
                    followBtn.classList.remove('bg-zinc-700', 'hover:bg-zinc-600');
                    followBtn.classList.add('bg-violet-600', 'hover:bg-violet-500');
                } else {
                    followBtn.textContent = 'Deixar de Seguir';
                    followBtn.dataset.isFollowing = 'true';
                    followBtn.classList.remove('bg-violet-600', 'hover:bg-violet-500');
                    followBtn.classList.add('bg-zinc-700', 'hover:bg-zinc-600');
                }

                // Mostrar mensagem de sucesso
                showNotification(data.message, 'success');

                // Atualizar contador de seguidores (opcional)
                // location.reload();
            } else {
                showNotification(data.message || 'Erro ao seguir usuário', 'error');
                followBtn.textContent = originalText;
            }
        } catch (error) {
            console.error('Erro:', error);
            showNotification('Erro ao processar solicitação', 'error');
            followBtn.textContent = originalText;
        } finally {
            followBtn.disabled = false;
        }
    });

    // Função auxiliar para mostrar notificações
    function showNotification(message, type = 'info') {
        // Criar elemento de notificação
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
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
            animation: slideIn 0.3s ease-in-out;
        `;

        document.body.appendChild(notification);

        // Remover após 3 segundos
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-in-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
})();
