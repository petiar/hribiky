document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.querySelector('textarea[name="BlogPost[text]"]');
    if (!textarea) return;

    // Obal textarea do kontajnera
    const wrapper = document.createElement('div');
    textarea.parentNode.insertBefore(wrapper, textarea);
    textarea.style.display = 'none';

    const editorDiv = document.createElement('div');
    editorDiv.style.minHeight = '500px';
    editorDiv.style.background = '#fff';
    editorDiv.style.color = '#000';
    wrapper.appendChild(editorDiv);

    const quill = new Quill(editorDiv, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic'],
                ['link'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['clean']
            ]
        }
    });

    // Načítaj existujúci obsah
    quill.clipboard.dangerouslyPasteHTML(textarea.value);

    // Pred odoslaním formulára skopíruj obsah späť do textarea
    const form = textarea.closest('form');
    if (form) {
        function getCleanHtml() {
            return quill.getSemanticHTML().replace(/\u00A0/g, ' ');
        }
        form.addEventListener('formdata', function () {
            textarea.value = getCleanHtml();
        });
        form.addEventListener('submit', function () {
            textarea.value = getCleanHtml();
        });
    }
});