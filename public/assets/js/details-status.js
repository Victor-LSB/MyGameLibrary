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

    const platinumBtn = document.getElementById('detailPlatinumBtn');
    if (platinumBtn) {
        platinumBtn.addEventListener('click', function () {
            if (platinumBtn.disabled) return;

            const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfTokenMeta ? csrfTokenMeta.content : '';

            const dados = new FormData();
            dados.set('csrf_token', csrfToken);
            dados.set('game_id', platinumBtn.dataset.gameId);

            fetch('index.php?action=toggle_platinum', {
                method: 'POST',
                body: dados
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Erro ao atualizar platina: ' + response.statusText);
                }
                return response.json();
            }).then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'Erro desconhecido ao atualizar platina');
                }
                notify('success', data.platinum ? 'Jogo platinado!' : 'Platina removida.');
                window.location.reload();
            }).catch(function (error) {
                console.error('Erro:', error);
                notify('error', 'Erro ao atualizar platina', error.message);
            });
        });
    }

    // Tags do jogo: adicionar e remover sem recarregar a página.
    const tagsSection = document.getElementById('gameTagsSection');
    if (tagsSection) {
        const addTagForm = document.getElementById('addTagForm');
        const tagsList = document.getElementById('gameTagsList');
        const noTagsMsg = document.getElementById('noGameTagsMsg');

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function renderTags(tags) {
            if (!tagsList) return;

            tagsList.innerHTML = tags.map(function (tag) {
                return '<div class="inline-flex items-center gap-1 rounded-full border border-violet-500/30 bg-violet-600/15 px-3 py-1.5 text-sm font-semibold text-violet-300" data-tag-id="' + tag.id + '">' +
                    '<a href="index.php?action=home&tag=' + encodeURIComponent(tag.name) + '" class="inline-flex items-center gap-2 hover:text-white transition-colors">' +
                        '<span>#</span><span>' + escapeHtml(tag.name) + '</span>' +
                    '</a>' +
                    '<button type="button" class="tag-remove-btn ml-2 text-xs font-black uppercase tracking-widest text-amber-200/80 hover:text-red-300 transition-colors" data-tag-id="' + tag.id + '" title="Remover tag">x</button>' +
                '</div>';
            }).join('');

            if (noTagsMsg) {
                noTagsMsg.classList.toggle('hidden', tags.length > 0);
            }
        }

        if (addTagForm) {
            addTagForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const input = document.getElementById('addTagInput');
                if (!input || !input.value.trim()) return;

                const dados = new FormData(addTagForm);
                const submitBtn = addTagForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                fetch('index.php?action=add_tag', {
                    method: 'POST',
                    body: dados
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Erro ao adicionar tag: ' + response.statusText);
                    }
                    return response.json();
                }).then(function (data) {
                    if (!data.success) {
                        throw new Error(data.message || 'Erro desconhecido ao adicionar tag');
                    }
                    renderTags(data.tags);
                    input.value = '';
                    notify('success', 'Tag adicionada com sucesso!');
                }).catch(function (error) {
                    console.error('Erro:', error);
                    notify('error', 'Erro ao adicionar tag', error.message);
                }).finally(function () {
                    if (submitBtn) submitBtn.disabled = false;
                });
            });
        }

        if (tagsList) {
            tagsList.addEventListener('click', function (event) {
                const btn = event.target.closest('.tag-remove-btn');
                if (!btn) return;

                const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfTokenMeta ? csrfTokenMeta.content : '';

                const dados = new FormData();
                dados.set('csrf_token', csrfToken);
                dados.set('game_id', tagsSection.dataset.gameId);
                dados.set('tag_id', btn.dataset.tagId);
                btn.disabled = true;

                fetch('index.php?action=remove_custom_tag', {
                    method: 'POST',
                    body: dados
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Erro ao remover tag: ' + response.statusText);
                    }
                    return response.json();
                }).then(function (data) {
                    if (!data.success) {
                        throw new Error(data.message || 'Erro desconhecido ao remover tag');
                    }
                    renderTags(data.tags);
                    notify('success', 'Tag removida com sucesso!');
                }).catch(function (error) {
                    console.error('Erro:', error);
                    notify('error', 'Erro ao remover tag', error.message);
                    btn.disabled = false;
                });
            });
        }
    }
})();