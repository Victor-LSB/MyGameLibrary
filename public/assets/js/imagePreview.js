document.addEventListener('DOMContentLoaded', function() {
    const avatarInput = document.getElementById('avatar');
    const bannerInput = document.getElementById('banner');

    if (avatarInput) {
        setupImagePreview(avatarInput, 'avatar-preview');
    }

    if (bannerInput) {
        setupImagePreview(bannerInput, 'banner-preview');
    }
});

function setupImagePreview(fileInput, previewContainerId) {
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];

        if (!file) {
            return;
        }

        // Validate file type
        const validTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Por favor, seleciona um arquivo de imagem válido (PNG, JPEG, GIF ou WebP)');
            e.target.value = '';
            return;
        }

        // Validate file size (max 5MB)
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            alert('A imagem é muito grande. O tamanho máximo é de 5MB.');
            e.target.value = '';
            return;
        }

        // Read and display preview
        const reader = new FileReader();

        reader.onload = function(event) {
            const imageData = event.target.result;

            // Find or create preview container
            let previewContainer = document.getElementById(previewContainerId);

            if (!previewContainer) {
                previewContainer = createPreviewContainer(fileInput, previewContainerId);
            }

            // Update preview image
            const previewImg = previewContainer.querySelector('img');
            previewImg.src = imageData;
            previewImg.alt = 'Pré-visualização da imagem';

            // Show the preview container
            previewContainer.style.display = 'block';
        };

        reader.onerror = function() {
            alert('Erro ao ler o arquivo. Por favor, tenta novamente.');
        };

        reader.readAsDataURL(file);
    });
}

function createPreviewContainer(fileInput, containerId) {
    let container = document.getElementById(containerId);

    if (!container) {
        container = document.createElement('div');
        container.id = containerId;
        container.className = 'mb-4 mt-4';

        const label = document.createElement('span');
        label.className = 'text-xs text-zinc-500 block mb-2';
        label.textContent = 'Pré-visualização:';

        const imageWrapper = document.createElement('div');

        if (containerId === 'avatar-preview') {
            imageWrapper.className = 'w-32 h-32 rounded-sm border-2 border-violet-500 overflow-hidden bg-zinc-950 shadow-md';
        } else if (containerId === 'banner-preview') {
            imageWrapper.className = 'w-full h-32 sm:h-48 rounded-sm border-2 border-violet-500 overflow-hidden bg-zinc-950 shadow-md';
        }

        const img = document.createElement('img');
        img.className = 'w-full h-full object-cover';

        imageWrapper.appendChild(img);

        container.appendChild(label);
        container.appendChild(imageWrapper);

        // Insert after the file input
        fileInput.parentNode.insertBefore(container, fileInput.nextSibling);
    }

    return container;
}
