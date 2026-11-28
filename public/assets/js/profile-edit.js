/**
 * Edição do perfil na própria página (só para o dono).
 *
 * "Editar perfil" liga o modo edição no card do topo: aparecem "Trocar banner",
 * "Trocar foto", o campo do nome, a bio editável e os botões Cancelar/Salvar.
 * O envio é o mesmo formulário de antes (index.php?action=update_profile); o recorte
 * das imagens continua sendo feito pelo imagePreview.js.
 * Sem JavaScript, o link "Editar perfil" continua levando para a página antiga.
 */
(function () {
    'use strict';

    var form = document.getElementById('profileEditForm');
    var card = form && form.querySelector('[data-profile-card]');
    var editBtn = form && form.querySelector('[data-edit-profile]');
    if (!form || !card || !editBtn) return;

    var cancelBtn = form.querySelector('[data-edit-cancel]');
    var nameInput = form.querySelector('input[name="display_name"]');
    var submitBtn = form.querySelector('button[type="submit"]');

    // Guarda como estava, para o Cancelar desfazer as imagens trocadas
    var originals = Array.prototype.map.call(form.querySelectorAll('[data-crop-target]'), function (img) {
        return { el: img, src: img.getAttribute('src'), hidden: img.hidden };
    });
    var placeholders = Array.prototype.map.call(form.querySelectorAll('[data-crop-placeholder]'), function (el) {
        return { el: el, hidden: el.hidden };
    });

    function open() {
        card.classList.add('pe-on');
        if (nameInput) {
            nameInput.focus();
            nameInput.select();
        }
    }

    function close() {
        form.reset(); // volta nome, bio e limpa os arquivos escolhidos
        originals.forEach(function (o) {
            if (o.src) o.el.setAttribute('src', o.src); else o.el.removeAttribute('src');
            o.el.hidden = o.hidden;
        });
        placeholders.forEach(function (p) { p.el.hidden = p.hidden; });
        form.querySelectorAll('.mgl-preview').forEach(function (box) { box.style.display = 'none'; });
        card.classList.remove('pe-on');
        editBtn.focus();
    }

    editBtn.addEventListener('click', function (e) {
        e.preventDefault();
        open();
    });

    if (cancelBtn) cancelBtn.addEventListener('click', close);

    document.addEventListener('keydown', function (e) {
        // Esc com o recorte aberto fecha só o recorte (o imagePreview cuida disso)
        if (e.key !== 'Escape' || !card.classList.contains('pe-on')) return;
        if (document.documentElement.classList.contains('mgl-crop-open')) return;
        close();
    });

    form.addEventListener('submit', function () {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Salvando...';
        }
    });

    // Erro no envio (ex.: imagem inválida): volta com a edição aberta
    if (document.documentElement.dataset.openProfileEdit === '1') open();
})();
