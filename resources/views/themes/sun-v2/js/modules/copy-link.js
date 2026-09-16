/**
 * Link kopieren im Betriebsbereich (Bewertungslink): Schalter [data-copy]
 * kopiert den Wert des Feldes aus aria-controls. Die Rueckmeldung steht in
 * data-copied und wird im Live-Bereich [data-copy-status] angesagt.
 */
export function initCopyLink() {
    document.querySelectorAll('[data-copy]').forEach((button) => {
        const field = document.getElementById(button.getAttribute('aria-controls'));
        const status = document.querySelector('[data-copy-status]');

        if (!field) {
            return;
        }

        button.addEventListener('click', async () => {
            field.select();

            try {
                await navigator.clipboard.writeText(field.value);
            } catch {
                document.execCommand('copy');
            }

            if (status) {
                status.textContent = button.dataset.copied;
                window.setTimeout(() => {
                    status.textContent = '';
                }, 3000);
            }
        });
    });
}
