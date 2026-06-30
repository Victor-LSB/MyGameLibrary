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
        }).then(function() {
            Toast.fire({
                icon: 'success',
                title: 'Status atualizado com sucesso!'
            });

            updateStatusCard(gameId, newStatus);
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
        }).then(function() {
            Toast.fire({
                icon: 'success',
                title: 'Status atualizado com sucesso!'
            });

            updateStatusCard(gameId, newStatus);
            closeCompletionModal();
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
        }).then(function() {
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