import { Editor } from 'https://esm.sh/@tiptap/core@2';
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2';
import Link from 'https://esm.sh/@tiptap/extension-link@2';
import Image from 'https://esm.sh/@tiptap/extension-image@2';

document.addEventListener('DOMContentLoaded', () => {
    const textarea = document.querySelector('textarea[name="BlogPost[text]"]');
    if (!textarea) return;

    textarea.style.display = 'none';

    const wrapper = document.createElement('div');
    wrapper.className = 'tiptap-wrapper';
    textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);

    const toolbar = document.createElement('div');
    toolbar.className = 'tiptap-toolbar';
    wrapper.appendChild(toolbar);

    const editorEl = document.createElement('div');
    editorEl.className = 'tiptap-content';
    wrapper.appendChild(editorEl);

    const editor = new Editor({
        element: editorEl,
        extensions: [
            StarterKit,
            Link.configure({ openOnClick: false, defaultProtocol: 'https' }),
            Image.configure({ inline: false }),
        ],
        content: textarea.value,
    });

    const toolbarButtons = [
        { label: 'H2',    action: () => editor.chain().focus().toggleHeading({ level: 2 }).run(), isActive: () => editor.isActive('heading', { level: 2 }) },
        { label: 'H3',    action: () => editor.chain().focus().toggleHeading({ level: 3 }).run(), isActive: () => editor.isActive('heading', { level: 3 }) },
        { label: 'B',     action: () => editor.chain().focus().toggleBold().run(),          isActive: () => editor.isActive('bold') },
        { label: 'I',     action: () => editor.chain().focus().toggleItalic().run(),        isActive: () => editor.isActive('italic') },
        { label: 'Link',  action: () => {
            const prev = editor.isActive('link') ? editor.getAttributes('link').href : '';
            const url = prompt('URL odkazu:', prev);
            if (url === null) return;
            url === ''
                ? editor.chain().focus().unsetLink().run()
                : editor.chain().focus().setLink({ href: url }).run();
        }, isActive: () => editor.isActive('link') },
        { label: '—',     action: () => editor.chain().focus().setHorizontalRule().run(),  isActive: () => false },
        { label: 'UL',    action: () => editor.chain().focus().toggleBulletList().run(),   isActive: () => editor.isActive('bulletList') },
        { label: 'OL',    action: () => editor.chain().focus().toggleOrderedList().run(),  isActive: () => editor.isActive('orderedList') },
        { label: '❝',     action: () => editor.chain().focus().toggleBlockquote().run(),   isActive: () => editor.isActive('blockquote') },
        { label: '🖼',     action: () => {
            const url = prompt('URL obrázka:');
            if (url) editor.chain().focus().setImage({ src: url }).run();
        }, isActive: () => false },
        { label: '↩',     action: () => editor.chain().focus().undo().run(),               isActive: () => false },
        { label: '↪',     action: () => editor.chain().focus().redo().run(),               isActive: () => false },
    ];

    toolbarButtons.forEach(({ label, action, isActive }) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = label;
        btn.title = label;
        btn.className = 'tiptap-btn';
        btn.addEventListener('click', () => {
            action();
            updateActive();
        });
        toolbar.appendChild(btn);
    });

    function updateActive() {
        toolbar.querySelectorAll('.tiptap-btn').forEach((btn, i) => {
            btn.classList.toggle('is-active', toolbarButtons[i].isActive());
        });
    }

    editor.on('selectionUpdate', updateActive);
    editor.on('transaction', updateActive);

    // Textarea je skrytá a povinná (required). Prehliadač kontroluje povinné polia ešte PRED
    // udalosťou submit, takže pri novom článku (prázdna textarea) odoslanie potichu zablokoval –
    // chybu nemal kde ukázať. Preto obsah zapisujeme do textarey priebežne pri každej zmene.
    const syncTextarea = () => {
        textarea.value = editor.isEmpty ? '' : editor.getHTML();
    };
    editor.on('update', syncTextarea);
    syncTextarea();

    // Ak je text naozaj prázdny, prehliadač chybu pri skrytom poli neukáže – ukážeme ju pri editore.
    const errorEl = document.createElement('div');
    errorEl.className = 'invalid-feedback d-block';
    errorEl.textContent = 'Text článku je povinný.';
    errorEl.hidden = true;
    wrapper.after(errorEl);

    textarea.addEventListener('invalid', () => {
        wrapper.style.borderColor = '#dc3545';
        errorEl.hidden = false;
        wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
        editor.commands.focus();
    });
    editor.on('update', () => {
        if (!editor.isEmpty) {
            wrapper.style.borderColor = '';
            errorEl.hidden = true;
        }
    });

    const form = textarea.closest('form');
    if (form) {
        form.addEventListener('submit', () => {
            textarea.value = editor.getHTML();
        });
        form.addEventListener('formdata', (e) => {
            e.formData.set('BlogPost[text]', editor.getHTML());
        });
    }
});