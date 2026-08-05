// Controla a troca de status e de nota diretamente na página de detalhes do jogo.
// Ao contrário do status.js (usado na biblioteca, com vários cards ao mesmo
// tempo), aqui só existe um jogo na tela — então, após qualquer alteração bem
// sucedida, simplesmente recarregamos a página para refletir com segurança o
// novo status, nota, data de conclusão e horas jogadas vindos do servidor.

(function () {
    const statusForms = document.querySelectorAll('.detailStatusForm');
    const ratingForm = document.getElementById('detailRatingForm');

    const completionModal = document.getElementById('detailCompletionModal');
    const completionForm = document.getElementById('detailCompletionForm');
    const modalCompletionDate = document.getElementById('detailModalCompletionDate');
    const modalTimeSpentHours = document.getElementById('detailModalTimeSpentHours');
    const cancelCompletionModal = document.getElementById('detailCancelCompletionModal');

    if (!statusForms.length && !ratingForm) return;

    const Toast = window.Swal ? Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    }) : null;

    function notify(icon, title, text) {
        if (Toast) {
            Toast.fire({ icon, title, text });
        }
    }

    let pendingForm = null;

    function openCompletionModal(form) {
        if (!completionModal || !completionForm) return;

        pendingForm = form;

        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        const nowLocal = now.toISOString().slice(0, 16);
        modalCompletionDate.value = nowLocal;
        // Impede a escolha de uma data/hora futura para a conclusão.
        modalCompletionDate.max = nowLocal;

        completionModal.classList.remove('hidden');
        completionModal.classList.add('flex');
    }

    function closeCompletionModal() {
        if (!completionModal) return;
        completionModal.classList.add('hidden');
        completionModal.classList.remove('flex');
        pendingForm = null;
    }

    function submitStatusChange(dados) {
        return fetch('index.php?action=change_status', {
            method: 'POST',
            body: dados
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Erro ao atualizar status: ' + response.statusText);
            }
            return response.json();
        });
    }

    statusForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const newStatus = form.dataset.status;
            if (newStatus === 'Zerado') {
                openCompletionModal(form);
                return;
            }

            const dados = new FormData(form);
            submitStatusChange(dados).then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'Erro desconhecido ao atualizar status');
                }
                notify('success', 'Status atualizado com sucesso!');
                window.location.reload();
            }).catch(function (error) {
                console.error('Erro:', error);
                notify('error', 'Erro ao atualizar status', error.message);
            });
        });
    });

    if (completionForm) {
        completionForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!pendingForm) return;

            const dados = new FormData(pendingForm);
            dados.set('completion_date', modalCompletionDate.value);
            dados.set('time_spent_hours', modalTimeSpentHours.value);

            submitStatusChange(dados).then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'Erro desconhecido ao atualizar status');
                }
                notify('success', 'Status atualizado com sucesso!');
                closeCompletionModal();
                window.location.reload();
            }).catch(function (error) {
                console.error('Erro:', error);
                notify('error', 'Erro ao atualizar status', error.message);
            });
        });
    }

    if (cancelCompletionModal) {
        cancelCompletionModal.addEventListener('click', closeCompletionModal);
    }

    if (completionModal) {
        completionModal.addEventListener('click', function (event) {
            if (event.target === completionModal) {
                closeCompletionModal();
            }
        });
    }

    if (ratingForm) {
        ratingForm.addEventListener('change', function (event) {
            event.preventDefault();

            const dados = new FormData(ratingForm);

            fetch('index.php?action=change_rating', {
                method: 'POST',
                body: dados
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Erro ao atualizar avaliação: ' + response.statusText);
                }
                return response.json();
            }).then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'Erro desconhecido ao atualizar avaliação');
                }
                notify('success', 'Avaliação atualizada com sucesso!');

                // Mantém os formulários de status com a nota nova, caso o
                // usuário troque o status logo em seguida sem recarregar.
                const newRating = dados.get('rating');
                statusForms.forEach(function (sForm) {
                    const hiddenRating = sForm.querySelector('input[name="rating"]');
                    if (hiddenRating) hiddenRating.value = newRating;
                });
            }).catch(function (error) {
                console.error('Erro:', error);
                notify('error', 'Erro ao atualizar avaliação', error.message);
            });
        });
    }
})();
