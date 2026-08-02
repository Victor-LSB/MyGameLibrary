const formStatus = document.querySelectorAll('.formStatus');
const ratingForm = document.querySelectorAll('.ratingForm');
const searchInput = document.getElementById('searchInput');
const gameList = document.querySelectorAll('.gameItem');
const filterStatus = document.querySelector('.filterStatus');
const completionModal = document.getElementById('completionModal');
const completionForm = document.getElementById('completionForm');
const modalGameId = document.getElementById('modalGameId');
const modalStatus = document.getElementById('modalStatus');
const modalCompletionDate = document.getElementById('modalCompletionDate');
const modalTimeSpentHours = document.getElementById('modalTimeSpentHours');
const cancelCompletionModal = document.getElementById('cancelCompletionModal');

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000
});

let pendingCompletionForm = null;

function updateStatusCard(gameId, newStatus) {
    const cardGame = document.getElementById(`game-${gameId}`);
    if (!cardGame) return;

    const pStatus = cardGame.querySelector('.gameStatus');
    if (pStatus) {
        pStatus.textContent = 'Status: ' + newStatus;
    }

    const ratingFormOfGame = cardGame.querySelector('.ratingForm');
    if (ratingFormOfGame) {
        const hiddenStatus = ratingFormOfGame.querySelector('input[name="status"]');
        if (hiddenStatus) hiddenStatus.value = newStatus;
    }

    const statusButtons = cardGame.querySelectorAll('.status-btn');
    statusButtons.forEach(function(btn) {
        const isActive = btn.dataset.statusBtn === newStatus;
        btn.classList.toggle('is-active', isActive);
        btn.classList.toggle('is-inactive', !isActive);
    });

    // O servidor limpa a platina automaticamente quando o status deixa de ser
    // "Zerado", então o botão de troféu do card precisa refletir isso na hora.
    const platinumBtn = cardGame.querySelector('.platinum-btn');
    if (platinumBtn) {
        const isZerado = newStatus === 'Zerado';
        platinumBtn.disabled = !isZerado;
        platinumBtn.dataset.platinum = '0';
        platinumBtn.title = isZerado ? 'Marcar como platinado' : 'Disponível depois de marcar como Zerado';
        platinumBtn.classList.remove('bg-amber-400', 'border-amber-400', 'text-zinc-900');
        platinumBtn.classList.add(...(isZerado
            ? ['bg-zinc-900/80', 'border-zinc-700', 'text-zinc-400']
            : ['bg-zinc-900/60', 'border-zinc-800', 'text-zinc-700', 'cursor-not-allowed']));
    }
}

function openCompletionModal(form) {
    if (!completionModal || !completionForm) return;

    pendingCompletionForm = form;
    const dados = new FormData(form);

    modalGameId.value = dados.get('game_id') || '';
    modalStatus.value = dados.get('status') || 'Zerado';

    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    modalCompletionDate.value = now.toISOString().slice(0, 16);
    modalTimeSpentHours.value = '';

    completionModal.classList.remove('hidden');
    completionModal.classList.add('flex');
}

function closeCompletionModal() {
    if (!completionModal) return;
    completionModal.classList.add('hidden');
    completionModal.classList.remove('flex');
    pendingCompletionForm = null;
}

formStatus.forEach(function(form) {
    form.addEventListener('submit', function(event) {
        event.preventDefault();

        const dados = new FormData(form);
        const gameId = dados.get('game_id');
        const newStatus = dados.get('status');

        if (newStatus === 'Zerado') {
            openCompletionModal(form);
            return;
        }

        fetch('index.php?action=change_status', {
            method: 'POST',
            body: dados
        }).then(response => {
            // Verificar se a resposta HTTP foi bem-sucedida
            if (!response.ok) {
                throw new Error('Erro ao atualizar status: ' + response.statusText);
            }
            return response.json();
        }).then(data => {
            // Verificar se a operação foi bem-sucedida
            if (data.success) {
                Toast.fire({
                    icon: 'success',
                    title: 'Status atualizado com sucesso!'
                });

                updateStatusCard(gameId, newStatus);
            } else {
                throw new Error(data.message || 'Erro desconhecido ao atualizar status');
            }
        }).catch(error => {
            // Mostrar erro ao usuário
            console.error('Erro:', error);
            Toast.fire({
                icon: 'error',
                title: 'Erro ao atualizar status',
                text: error.message
            });
        });
    });
});

if (completionForm) {
    completionForm.addEventListener('submit', function(event) {
        event.preventDefault();

        if (!pendingCompletionForm) return;

        const dados = new FormData(pendingCompletionForm);
        dados.set('completion_date', modalCompletionDate.value);
        dados.set('time_spent_hours', modalTimeSpentHours.value);

        const gameId = dados.get('game_id');
        const newStatus = dados.get('status');

        fetch('index.php?action=change_status', {
            method: 'POST',
            body: dados
        }).then(response => {
            // Verificar se a resposta HTTP foi bem-sucedida
            if (!response.ok) {
                throw new Error('Erro ao atualizar status: ' + response.statusText);
            }
            return response.json();
        }).then(data => {
            // Verificar se a operação foi bem-sucedida
            if (data.success) {
                Toast.fire({
                    icon: 'success',
                    title: 'Status atualizado com sucesso!'
                });

                updateStatusCard(gameId, newStatus);
                closeCompletionModal();
            } else {
                throw new Error(data.message || 'Erro desconhecido ao atualizar status');
            }
        }).catch(error => {
            // Mostrar erro ao usuário
            console.error('Erro:', error);
            Toast.fire({
                icon: 'error',
                title: 'Erro ao atualizar status',
                text: error.message
            });
        });
    });
}

if (cancelCompletionModal) {
    cancelCompletionModal.addEventListener('click', closeCompletionModal);
}

if (completionModal) {
    completionModal.addEventListener('click', function(event) {
        if (event.target === completionModal) {
            closeCompletionModal();
        }
    });
}

ratingForm.forEach(function(form) {
    form.addEventListener('change', function(event) {
        event.preventDefault();

        const dados = new FormData(form);
        const gameId = dados.get('game_id');
        const newRating = dados.get('rating');

        fetch('index.php?action=change_rating', {
            method: 'POST',
            body: dados
        }).then(response => {
            // Verificar se a resposta HTTP foi bem-sucedida
            if (!response.ok) {
                throw new Error('Erro ao atualizar avaliação: ' + response.statusText);
            }
            return response.json();
        }).then(data => {
            // Verificar se a operação foi bem-sucedida
            if (data.success) {
                Toast.fire({
                    icon: 'success',
                    title: 'Avaliação atualizada com sucesso!'
                });

                const cardGame = document.getElementById(`game-${gameId}`);
                if (!cardGame) return;

                const pRating = cardGame.querySelector('.pRating');
                if (pRating) {
                    pRating.textContent = 'Avaliação: ' + (newRating ? newRating : 'Não avaliado');
                }

                const statusForms = cardGame.querySelectorAll('.formStatus');
                statusForms.forEach((sForm) => {
                    const hiddenRating = sForm.querySelector('input[name="rating"]');
                    if (hiddenRating) hiddenRating.value = newRating;
                });
            } else {
                throw new Error(data.message || 'Erro desconhecido ao atualizar avaliação');
            }
        }).catch(error => {
            // Mostrar erro ao usuário
            console.error('Erro:', error);
            Toast.fire({
                icon: 'error',
                title: 'Erro ao atualizar avaliação',
                text: error.message
            });
        });
    });
});

if (filterStatus) {
    filterStatus.addEventListener('change', function(event) {
        event.preventDefault();
        const selectedStatus = filterStatus.value;

        gameList.forEach(function(game) {
            const pStatus = game.querySelector('.gameStatus');
            if (pStatus) {
                const status = pStatus.textContent.replace('Status: ', '').trim();
                game.style.display = (selectedStatus === '' || status === selectedStatus) ? 'block' : 'none';
            }
        });
    });
}

if (searchInput) {
    searchInput.addEventListener('input', function() {
        const termoPesquisa = searchInput.value.toLowerCase();

        gameList.forEach(function(game) {
            const titulo = game.querySelector('h3').textContent.toLowerCase();
            game.style.display = titulo.includes(termoPesquisa) ? 'block' : 'none';
        });
    });
}

const platinumButtons = document.querySelectorAll('.platinum-btn');
const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
const csrfToken = csrfTokenMeta ? csrfTokenMeta.content : '';

platinumButtons.forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (btn.disabled) return;

        const gameId = btn.dataset.gameId;
        const dados = new FormData();
        dados.set('csrf_token', csrfToken);
        dados.set('game_id', gameId);

        fetch('index.php?action=toggle_platinum', {
            method: 'POST',
            body: dados
        }).then(response => {
            if (!response.ok) {
                throw new Error('Erro ao atualizar platina: ' + response.statusText);
            }
            return response.json();
        }).then(data => {
            if (data.success) {
                const isPlatinum = !!data.platinum;
                btn.dataset.platinum = isPlatinum ? '1' : '0';
                btn.title = isPlatinum ? 'Platinado — clique para desmarcar' : 'Marcar como platinado';
                btn.classList.toggle('bg-amber-400', isPlatinum);
                btn.classList.toggle('border-amber-400', isPlatinum);
                btn.classList.toggle('text-zinc-900', isPlatinum);
                btn.classList.toggle('bg-zinc-900/80', !isPlatinum);
                btn.classList.toggle('border-zinc-700', !isPlatinum);
                btn.classList.toggle('text-zinc-400', !isPlatinum);

                Toast.fire({
                    icon: 'success',
                    title: isPlatinum ? 'Jogo platinado!' : 'Platina removida.'
                });
            } else {
                throw new Error(data.message || 'Erro desconhecido ao atualizar platina');
            }
        }).catch(error => {
            console.error('Erro:', error);
            Toast.fire({
                icon: 'error',
                title: 'Erro ao atualizar platina',
                text: error.message
            });
        });
    });
});