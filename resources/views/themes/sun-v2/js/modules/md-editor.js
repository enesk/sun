/**
 * Markdown-Editor fuer Textfelder mit [data-md-editor] (Beschreibung in
 * "Profil bearbeiten"). EasyMDE wird nur auf Seiten mit so einem Feld
 * nachgeladen. Texte (Schaltflaechen) kommen fertig uebersetzt aus
 * data-md-editor als JSON, Icons aus dem Sprite (data-md-icons).
 *
 * Markdown-Zeichen (**, ###, _) sind ausgeblendet und erscheinen nur in der
 * Zeile mit dem Cursor (Klasse md-active, Styles in md-editor.css). So sieht
 * man beim Lesen das Ergebnis und beim Bearbeiten weiterhin die Zeichen.
 *
 * Das Textfeld bleibt die Quelle fuer Livewire (wire:model): jede Aenderung
 * schreibt den Wert zurueck und loest ein input-Ereignis aus. Der Editor
 * sitzt in einem wire:ignore-Bereich, damit Livewire ihn nicht ersetzt.
 */
export function initMdEditor() {
    const fields = [...document.querySelectorAll('textarea[data-md-editor]')];

    if (fields.length === 0) {
        return;
    }

    Promise.all([import('easymde'), import('easymde/dist/easymde.min.css'), import('./md-editor.css')]).then(([{ default: EasyMDE }]) => {
        fields.forEach((textarea) => setup(EasyMDE, textarea));
    });
}

function setup(EasyMDE, textarea) {
    if (textarea.dataset.mdReady) {
        return;
    }

    textarea.dataset.mdReady = '1';

    const texts = JSON.parse(textarea.dataset.mdEditor || '{}');
    const sprite = textarea.dataset.mdIcons || '';
    const icon = (name) => `<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="${sprite}#${name}"/></svg>`;
    const label = (text) => `<span class="md-editor-label">${text}</span>`;

    const button = (name, action, title, content) => ({ name, action, title, icon: content });

    const editor = new EasyMDE({
        element: textarea,
        autoDownloadFontAwesome: false,
        spellChecker: false,
        nativeSpellcheck: true,
        status: false,
        forceSync: true,
        minHeight: '12rem',
        placeholder: textarea.getAttribute('placeholder') || '',
        blockStyles: { bold: '**', italic: '*' },
        inputStyle: 'contenteditable',
        toolbar: [
            button('heading', EasyMDE.toggleHeading2, texts.heading, label('H')),
            button('bold', EasyMDE.toggleBold, texts.bold, label('<strong>F</strong>')),
            button('italic', EasyMDE.toggleItalic, texts.italic, label('<em>K</em>')),
            '|',
            button('unordered-list', EasyMDE.toggleUnorderedList, texts.list, icon('list')),
            button('ordered-list', EasyMDE.toggleOrderedList, texts.numbered, icon('list-ordered')),
            '|',
            { ...button('preview', EasyMDE.togglePreview, texts.preview, icon('eye')), noDisable: true },
        ],
        previewClass: ['editor-preview', 'md-editor-preview'],
    });

    const wrapper = editor.codemirror.getWrapperElement();
    wrapper.setAttribute('aria-label', texts.label || '');

    markActiveLines(editor.codemirror);
    editor.codemirror.on('cursorActivity', markActiveLines);
    editor.codemirror.on('focus', markActiveLines);
    editor.codemirror.on('blur', markActiveLines);

    editor.codemirror.on('change', () => {
        textarea.value = editor.value();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

function markActiveLines(cm) {
    const active = new Set();

    // Ohne Fokus (etwa direkt nach dem Laden) bleibt der ganze Text als Ergebnis sichtbar
    if (cm.hasFocus()) {
        cm.listSelections().forEach((range) => {
            const from = Math.min(range.anchor.line, range.head.line);
            const to = Math.max(range.anchor.line, range.head.line);

            for (let line = from; line <= to; line++) {
                active.add(cm.getLineHandle(line));
            }
        });
    }

    cm.operation(() => {
        (cm.state.mdActiveLines || []).forEach((handle) => {
            if (!active.has(handle)) {
                cm.removeLineClass(handle, 'text', 'md-active');
            }
        });

        active.forEach((handle) => cm.addLineClass(handle, 'text', 'md-active'));
    });

    cm.state.mdActiveLines = [...active];
}
