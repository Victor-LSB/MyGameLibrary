/**
 * Upload de avatar e banner com recorte (Cropper.js).
 *
 * Fluxo: o usuário escolhe a foto -> abre um modal para ajustar o recorte -> "Aplicar"
 * gera uma imagem já recortada/redimensionada e a coloca de volta no <input type="file">.
 * O formulário é enviado normalmente, então o backend não muda.
 *
 * - Avatar: recorte quadrado (1:1), saída de até 512x512.
 * - Banner: recorte 3:1, saída de até 1500x500.
 * - GIF: NÃO é recortado (o recorte no navegador acabaria com a animação); segue direto.
 * - Se o navegador não suportar (sem Cropper/DataTransfer), cai no comportamento antigo:
 *   só pré-visualização, sem recorte.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var VALID_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    var MAX_GIF_BYTES = 5 * 1024 * 1024;       // GIF vai direto pro servidor
    var MAX_IMAGE_BYTES = 25 * 1024 * 1024;    // demais: o recorte reduz bastante o arquivo
    var MAX_UPLOAD_BYTES = 5 * 1024 * 1024;    // limite do arquivo FINAL enviado

    var CONFIGS = {
        avatar: { inputId: 'avatar', previewId: 'avatar-preview', prefix: 'avatar', title: 'Ajustar foto de perfil', aspect: 1, outWidth: 512 },
        banner: { inputId: 'banner', previewId: 'banner-preview', prefix: 'banner', title: 'Ajustar banner', aspect: 3, outWidth: 1500 }
    };

    var cropSupported = (function () {
        if (typeof window.Cropper !== 'function' || typeof window.File !== 'function' || typeof window.DataTransfer !== 'function') {
            return false;
        }
        try { new DataTransfer(); return true; } catch (e) { return false; }
    })();

    var modal = null;      // elementos do modal (criados na 1ª vez que abre)
    var cropper = null;    // instância ativa do Cropper
    var session = null;    // { input, cfg, state, file, mode: 'new' | 'readjust' }
    var objectUrl = null;  // URL temporária da imagem no modal

    Object.keys(CONFIGS).forEach(function (key) {
        var cfg = CONFIGS[key];
        cfg.key = key;
        var input = document.getElementById(cfg.inputId);
        if (input) setup(input, cfg);
    });

    // ---------------------------------------------------------------- por campo

    function setup(input, cfg) {
        var state = { original: null, previewUrl: null };

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];

            // Escolher "cancelar" no seletor limpa o campo (Chrome): a prévia não vale mais
            if (!file) {
                state.original = null;
                hidePreview(cfg, state);
                return;
            }

            if (VALID_TYPES.indexOf(file.type) === -1) {
                alert('Por favor, selecione um arquivo de imagem válido (PNG, JPEG, GIF ou WebP)');
                input.value = '';
                return;
            }

            var isGif = file.type === 'image/gif';
            if (file.size > (isGif ? MAX_GIF_BYTES : MAX_IMAGE_BYTES)) {
                alert('A imagem é muito grande. O tamanho máximo é de ' + (isGif ? '5MB para GIF.' : '25MB.'));
                input.value = '';
                return;
            }

            if (isGif || !cropSupported) {
                state.original = null;
                showPreview(input, cfg, state, file, isGif ? 'GIFs animados são enviados sem recorte, para manter a animação.' : '');
                return;
            }

            state.original = file;
            openCropper({ input: input, cfg: cfg, state: state, file: file, mode: 'new' });
        });
    }

    // ------------------------------------------------------------- pré-visualização

    function getPreview(input, cfg, state) {
        var box = document.getElementById(cfg.previewId);
        if (box) return box;

        box = document.createElement('div');
        box.id = cfg.previewId;
        box.className = 'mgl-preview';

        var label = document.createElement('span');
        label.className = 'mgl-preview-label';
        label.textContent = 'Pré-visualização:';

        var frame = document.createElement('div');
        frame.className = 'mgl-preview-frame mgl-preview-' + cfg.key;
        var img = document.createElement('img');
        img.alt = 'Pré-visualização da imagem';
        frame.appendChild(img);

        var note = document.createElement('p');
        note.className = 'mgl-preview-note';
        note.hidden = true;

        var again = document.createElement('button');
        again.type = 'button';
        again.className = 'mgl-btn mgl-btn-ghost mgl-preview-readjust';
        again.textContent = 'Ajustar recorte';
        again.hidden = true;
        again.addEventListener('click', function () {
            if (state.original) {
                openCropper({ input: input, cfg: cfg, state: state, file: state.original, mode: 'readjust' });
            }
        });

        box.appendChild(label);
        box.appendChild(frame);
        box.appendChild(note);
        box.appendChild(again);
        input.parentNode.insertBefore(box, input.nextSibling);
        return box;
    }

    function showPreview(input, cfg, state, blob, noteText) {
        var box = getPreview(input, cfg, state);
        if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
        state.previewUrl = URL.createObjectURL(blob);

        box.querySelector('img').src = state.previewUrl;
        var note = box.querySelector('.mgl-preview-note');
        note.textContent = noteText || '';
        note.hidden = !noteText;
        box.querySelector('.mgl-preview-readjust').hidden = !state.original;
        box.style.display = 'block';
    }

    function hidePreview(cfg, state) {
        var box = document.getElementById(cfg.previewId);
        if (box) box.style.display = 'none';
        if (state.previewUrl) {
            URL.revokeObjectURL(state.previewUrl);
            state.previewUrl = null;
        }
    }

    // ------------------------------------------------------------------- modal

    function getModal() {
        if (modal) return modal;

        var overlay = document.createElement('div');
        overlay.className = 'mgl-crop-overlay';
        overlay.hidden = true;
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'mgl-crop-title');

        var box = document.createElement('div');
        box.className = 'mgl-crop-modal';

        var title = document.createElement('h2');
        title.id = 'mgl-crop-title';
        title.className = 'mgl-crop-title';

        var help = document.createElement('p');
        help.className = 'mgl-crop-help';
        help.textContent = 'Arraste a imagem para posicionar e use os cantos da caixa para ajustar o recorte.';

        var stage = document.createElement('div');
        stage.className = 'mgl-crop-stage';
        var img = document.createElement('img');
        img.alt = 'Imagem a ser recortada';
        stage.appendChild(img);

        var tools = document.createElement('div');
        tools.className = 'mgl-crop-tools';
        [
            ['−', 'Diminuir zoom', function () { if (cropper) cropper.zoom(-0.1); }],
            ['+', 'Aumentar zoom', function () { if (cropper) cropper.zoom(0.1); }],
            ['↻', 'Girar 90°', function () { if (cropper) cropper.rotate(90); }],
            ['Redefinir', 'Voltar ao recorte inicial', function () { if (cropper) cropper.reset(); }]
        ].forEach(function (t) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'mgl-btn mgl-btn-ghost mgl-btn-tool';
            b.textContent = t[0];
            b.title = t[1];
            b.setAttribute('aria-label', t[1]);
            b.addEventListener('click', t[2]);
            tools.appendChild(b);
        });

        var actions = document.createElement('div');
        actions.className = 'mgl-crop-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'mgl-btn mgl-btn-ghost';
        cancel.textContent = 'Cancelar';
        var apply = document.createElement('button');
        apply.type = 'button';
        apply.className = 'mgl-btn';
        apply.textContent = 'Aplicar';
        actions.appendChild(cancel);
        actions.appendChild(apply);

        box.appendChild(title);
        box.appendChild(help);
        box.appendChild(stage);
        box.appendChild(tools);
        box.appendChild(actions);
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        cancel.addEventListener('click', function () { closeModal(true); });
        apply.addEventListener('click', applyCrop);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !overlay.hidden) closeModal(true);
        });

        modal = { overlay: overlay, title: title, img: img, apply: apply };
        return modal;
    }

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    }

    function openCropper(s) {
        session = s;
        var m = getModal();
        destroyCropper();

        m.title.textContent = s.cfg.title;
        m.apply.disabled = true;
        m.overlay.hidden = false;
        document.documentElement.classList.add('mgl-crop-open');

        objectUrl = URL.createObjectURL(s.file);
        m.img.onload = function () {
            m.img.onload = null;
            cropper = new Cropper(m.img, {
                aspectRatio: s.cfg.aspect,
                viewMode: 1,             // a caixa nunca sai da imagem
                dragMode: 'move',        // arrastar move a imagem; os cantos redimensionam a caixa
                autoCropArea: 1,
                background: false,
                guides: true,            // grade de terços
                highlight: false,
                toggleDragModeOnDblclick: false,
                ready: function () { m.apply.disabled = false; }
            });
        };
        m.img.onerror = function () {
            m.img.onerror = null;
            alert('Não foi possível abrir esta imagem. Tente outro arquivo.');
            closeModal(true);
        };
        m.img.src = objectUrl;
        m.apply.focus();
    }

    function closeModal(cancelled) {
        var s = session;
        session = null;
        if (modal) {
            modal.overlay.hidden = true;
            modal.img.onload = null;
            modal.img.onerror = null;
        }
        document.documentElement.classList.remove('mgl-crop-open');
        destroyCropper();

        if (!s) return;
        // Cancelou logo após escolher o arquivo: desfaz a escolha. Se estava só
        // reajustando um recorte já aplicado, mantém o que já estava.
        if (cancelled && s.mode === 'new') {
            s.input.value = '';
            s.state.original = null;
            hidePreview(s.cfg, s.state);
        }
        s.input.focus();
    }

    // --------------------------------------------------------- aplicar o recorte

    function applyCrop() {
        if (!cropper || !session) return;
        var s = session;
        var m = modal;

        var data = cropper.getData(true); // tamanho do recorte em pixels da imagem original
        if (!data.width || !data.height) return;

        // Nunca amplia: se o recorte é menor que o tamanho de saída, mantém o tamanho dele
        var width = Math.max(1, Math.round(data.width * Math.min(1, s.cfg.outWidth / data.width)));
        var height = Math.max(1, Math.round(width / s.cfg.aspect));

        var isPng = s.file.type === 'image/png';
        var canvasOptions = { width: width, height: height, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' };
        if (!isPng) canvasOptions.fillColor = '#18181b'; // JPEG não tem transparência

        var canvas = cropper.getCroppedCanvas(canvasOptions);
        if (!canvas) {
            alert('Não foi possível recortar esta imagem. Tente outra.');
            return;
        }

        m.apply.disabled = true;

        function finish(blob, mime) {
            if (session !== s) return; // o usuário cancelou enquanto a imagem era gerada
            if (!blob || blob.size > MAX_UPLOAD_BYTES) {
                alert('Não foi possível gerar uma imagem pequena o bastante. Tente um recorte menor ou outra foto.');
                m.apply.disabled = false;
                return;
            }

            var file = new File([blob], s.cfg.prefix + (mime === 'image/png' ? '.png' : '.jpg'), { type: mime, lastModified: Date.now() });
            var transfer = new DataTransfer();
            transfer.items.add(file);
            s.input.files = transfer.files; // atribuir assim NÃO dispara "change" (sem loop)

            showPreview(s.input, s.cfg, s.state, blob, '');
            closeModal(false);
        }

        if (isPng) {
            canvas.toBlob(function (blob) {
                if (session !== s || !cropper) return;
                if (blob && blob.size > MAX_UPLOAD_BYTES) {
                    // PNG de foto pode ficar grande: reencoda como JPEG
                    var flat = cropper.getCroppedCanvas({ width: width, height: height, imageSmoothingQuality: 'high', fillColor: '#18181b' });
                    flat.toBlob(function (jpg) { finish(jpg, 'image/jpeg'); }, 'image/jpeg', 0.9);
                } else {
                    finish(blob, 'image/png');
                }
            }, 'image/png');
        } else {
            canvas.toBlob(function (blob) { finish(blob, 'image/jpeg'); }, 'image/jpeg', 0.92);
        }
    }
});
