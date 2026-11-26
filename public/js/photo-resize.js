/**
 * Zmenšenie fotiek v prehliadači pred odoslaním formulára.
 *
 * Fotka z telefónu (4000×3000, 3–7 MB) sa zmenší na najviac 2000 px (JPEG) — rovnako ako na serveri
 * (ImageOptimizer). Odchádza tak ~1 MB namiesto 5–7 MB: zmestí sa pod limity servera
 * a nahrávanie so slabým signálom (na horách) je niekoľkonásobne rýchlejšie.
 *
 * Ak sa fotka nedá spracovať (nepodporovaný formát, starý prehliadač), odošle sa originál —
 * server ju aj tak upraví. Zmenšenie teda nikdy nezablokuje odoslanie.
 *
 * Použitie: const formData = await HribikyPhotos.buildFormData(formElement);
 */
window.HribikyPhotos = (function () {
    'use strict';

    const MAX_DIMENSION = 2000;
    const JPEG_QUALITY = 0.85;
    /** Menšie fotky (rozmerom aj veľkosťou) sa posielajú bez zmeny. */
    const SKIP_BELOW_BYTES = 1.5 * 1024 * 1024;
    /** GIF (animácia) a PNG (priehľadnosť) sa nekonvertujú na JPEG. */
    const RESIZABLE_TYPES = ['image/jpeg', 'image/jpg', 'image/webp', 'image/heic', 'image/heif'];

    function loadBitmap(file) {
        // imageOrientation: 'from-image' otočí fotku podľa EXIF (výška/šírka ako na telefóne).
        if (window.createImageBitmap) {
            return createImageBitmap(file, { imageOrientation: 'from-image' });
        }
        // Záloha pre staršie prehliadače.
        return new Promise(function (resolve, reject) {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('Nepodarilo sa načítať obrázok')); };
            img.src = url;
        });
    }

    function toJpegName(name) {
        const base = name.replace(/\.[^.]+$/, '');
        return (base || 'foto') + '.jpg';
    }

    /**
     * Vráti zmenšenú fotku (File, JPEG) alebo pôvodný súbor, ak zmenšenie nemá zmysel alebo zlyhá.
     */
    async function resize(file) {
        if (!(file instanceof File) || !RESIZABLE_TYPES.includes((file.type || '').toLowerCase())) {
            return file;
        }

        try {
            const bitmap = await loadBitmap(file);
            const width = bitmap.width;
            const height = bitmap.height;
            const longest = Math.max(width, height);

            if (longest <= MAX_DIMENSION && file.size <= SKIP_BELOW_BYTES) {
                if (bitmap.close) { bitmap.close(); }
                return file;
            }

            const ratio = Math.min(1, MAX_DIMENSION / longest);
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            const ctx = canvas.getContext('2d');
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            if (bitmap.close) { bitmap.close(); }

            const blob = await new Promise(function (resolve) {
                canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY);
            });

            // Ak by výsledok nebol menší, pošli originál.
            if (!blob || blob.size >= file.size) {
                return file;
            }

            return new File([blob], toJpegName(file.name), { type: 'image/jpeg', lastModified: file.lastModified });
        } catch (e) {
            return file;
        }
    }

    /**
     * FormData z formulára, v ktorom sú fotky zo všetkých <input type="file"> zmenšené.
     */
    async function buildFormData(form) {
        const formData = new FormData(form);
        const inputs = form.querySelectorAll('input[type="file"]');

        for (const input of inputs) {
            if (!input.name || !input.files || input.files.length === 0) {
                continue;
            }
            const resized = await Promise.all(Array.from(input.files).map(resize));
            formData.delete(input.name);
            resized.forEach(function (file) {
                formData.append(input.name, file, file.name);
            });
        }

        return formData;
    }

    /**
     * Upozorní hneď po výbere, ak je fotiek viac, ako server prijme (input[data-max-files]).
     * Výber zruší, aby sa formulár s priveľa fotkami neodoslal.
     */
    document.addEventListener('change', function (event) {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.dataset.maxFiles) {
            return;
        }
        const max = parseInt(input.dataset.maxFiles, 10);
        if (input.files && input.files.length > max) {
            alert('Môžeš pridať najviac ' + max + ' fotiek. Vybral si ' + input.files.length + ', vyber ich prosím menej.');
            input.value = '';
        }
    });

    return { resize: resize, buildFormData: buildFormData, MAX_DIMENSION: MAX_DIMENSION };
})();
