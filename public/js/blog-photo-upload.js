function initBlogPhotoUpload() {
    if (document.querySelector('.blog-photo-section')) return; // už inicializované

    const path = window.location.pathname;
    const editMatch = path.match(/\/admin\/blog-post\/(\d+)/);
    const isNew = path.endsWith('/new');

    if (!editMatch && !isNew) return;

    const uploadUrl = editMatch
        ? `/admin/blog-post/${editMatch[1]}/photos`
        : '/admin/blog-post/temp-photos';
    const isTempMode = isNew;

    const form = document.querySelector('form[name="BlogPost"]')
        || document.querySelector('form.ea-new-form')
        || document.querySelector('form.ea-edit-form')
        || document.querySelector('section.ea-main form')
        || document.querySelector('form');

    if (!form) {
        console.warn('[blog-photo-upload] Formulár nenájdený');
        return;
    }

    const section = document.createElement('div');
    section.className = 'blog-photo-section';
    section.innerHTML = `
        <h4 class="blog-photo-title">Obrázky článku</h4>
        <label class="blog-photo-dropzone">
            <input type="file" class="blog-photo-input" multiple accept="image/*" style="display:none">
            <span class="blog-photo-dropzone-text">
                <strong>Klikni alebo pretiahni obrázky sem</strong><br>
                <small>JPG, PNG, WebP, GIF</small>
            </span>
        </label>
        <div class="blog-photo-progress" style="display:none">
            <div class="blog-photo-progress-bar"></div>
        </div>
        <div class="blog-photo-grid"></div>
    `;

    form.insertAdjacentElement('afterend', section);

    const dropzone    = section.querySelector('.blog-photo-dropzone');
    const fileInput   = section.querySelector('.blog-photo-input');
    const progress    = section.querySelector('.blog-photo-progress');
    const progressBar = section.querySelector('.blog-photo-progress-bar');
    const grid        = section.querySelector('.blog-photo-grid');

    // Na edit stránke načítaj existujúce fotky
    if (!isTempMode) {
        fetch(uploadUrl)
            .then(r => r.json())
            .then(photos => photos.forEach(p => addPhotoCard(p, false)))
            .catch(e => console.error('[blog-photo-upload] Chyba načítania:', e));
    }

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('drag-over'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        uploadFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => {
        uploadFiles(fileInput.files);
        fileInput.value = '';
    });

    function uploadFiles(files) {
        if (!files.length) return;

        const formData = new FormData();
        Array.from(files).forEach(f => formData.append('files[]', f));

        progress.style.display = 'block';
        progressBar.style.width = '0%';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                progressBar.style.width = Math.round(e.loaded / e.total * 100) + '%';
            }
        });

        xhr.addEventListener('load', () => {
            progress.style.display = 'none';
            if (xhr.status === 200) {
                const newPhotos = JSON.parse(xhr.responseText);
                if (isTempMode) {
                    newPhotos.forEach(p => addPhotoCard(p, true));
                } else {
                    const existing = grid.querySelectorAll('.blog-photo-card').length;
                    newPhotos.slice(existing).forEach(p => addPhotoCard(p, true));
                }
            } else {
                alert('Chyba pri nahrávaní obrázkov.');
            }
        });

        xhr.addEventListener('error', () => {
            progress.style.display = 'none';
            alert('Chyba pri nahrávaní obrázkov.');
        });

        xhr.send(formData);
    }

    function addPhotoCard(photo, isNew) {
        const card = document.createElement('div');
        card.className = 'blog-photo-card' + (isNew ? ' is-new' : '');
        card.innerHTML = `
            <img src="${photo.url}" alt="">
            <div class="blog-photo-card-url">
                <input type="text" value="${photo.url}" readonly onclick="this.select()">
                <button type="button" class="blog-photo-copy-btn" title="Kopírovať URL">📋</button>
            </div>
        `;
        card.querySelector('.blog-photo-copy-btn').addEventListener('click', () => {
            navigator.clipboard.writeText(photo.url);
            const btn = card.querySelector('.blog-photo-copy-btn');
            btn.textContent = '✅';
            setTimeout(() => btn.textContent = '📋', 1500);
        });
        grid.appendChild(card);

        // V temp móde pridaj hidden input do formulára
        if (isTempMode && photo.id) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'BlogPost[pendingPhotoIds][]';
            hidden.value = photo.id;
            hidden.dataset.photoId = photo.id;
            form.appendChild(hidden);
        }
    }
}

document.addEventListener('DOMContentLoaded', initBlogPhotoUpload);
document.addEventListener('turbo:load', initBlogPhotoUpload);