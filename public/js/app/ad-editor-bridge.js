/*
 * Brücke zwischen dem Vorschau-iframe (Portal-Domain) und der Filament-Seite
 * „Werbeplätze" (Panel-Domain) — Ticket #7, Protokoll in docs/ad-slots.md 6.2.
 *
 * Cross-origin: beide Seiten nennen `targetOrigin` ausdrücklich und prüfen
 * `event.origin`. Nachrichten sind `{ type, payload, catalogVersion }`; eine
 * abweichende Katalogversion wird verworfen — dann steht im iframe ein
 * anderer Anker-Stand als im Panel.
 *
 * Portal → Panel: slots:ready, slot:clicked
 * Panel → Portal: slot:highlight, placement:updated, device:changed
 *
 * Registriert als Filament-Asset (AppServiceProvider), eingebunden als
 * Alpine-Komponente `adEditorPreview` — kein Inline-Skript.
 */

function alpineKomponente() {
    return {
        /** Adresse im iframe; null heisst: keine Vorschau möglich. */
        url: null,
        /** Erwartete Gegenseite, z. B. https://fliesenleger.io */
        origin: null,
        device: 'mobile',
        catalogVersion: 0,
        /** Breite/Höhe des iframes in px — die echte Geräteabmessung. */
        frameWidth: 390,
        frameHeight: 844,
        scale: 1,
        bereit: false,

        init() {
            this.url = this.$el.dataset.url || null;
            this.origin = this.$el.dataset.origin || null;
            this.device = this.$el.dataset.device || 'mobile';
            this.catalogVersion = Number.parseInt(this.$el.dataset.catalogVersion || '0', 10);
            this.frameWidth = Number.parseInt(this.$el.dataset.frameWidth || '390', 10);
            this.frameHeight = Number.parseInt(this.$el.dataset.frameHeight || '844', 10);

            this.skaliere();

            this._aufNachricht = (event) => this.empfange(event);
            window.addEventListener('message', this._aufNachricht);

            this._aufGroesse = () => this.skaliere();
            window.addEventListener('resize', this._aufGroesse);

            // Auswahl im Panel hervorheben — egal ob sie aus der Liste oder
            // aus dem iframe kam.
            this.$watch('$wire.selectedSlotKey', (key) => this.sende('slot:highlight', { key: key ?? null }));
        },

        destroy() {
            window.removeEventListener('message', this._aufNachricht);
            window.removeEventListener('resize', this._aufGroesse);
        },

        /** Ein Wechsel von Portal, Seitentyp, Gerät oder Pfad lädt neu. */
        neuLaden(detail) {
            this.bereit = false;
            this.url = detail?.url ?? null;
            this.device = detail?.device ?? this.device;
            this.frameWidth = this.device === 'desktop' ? 1280 : 390;
            this.frameHeight = this.device === 'desktop' ? 900 : 844;
            this.skaliere();
        },

        /**
         * Desktop (1280 px) wird in den verfügbaren Platz skaliert, damit die
         * Seite ihre echte Breakpoint-Logik behält. Mobil bleibt 1:1, solange
         * es passt.
         */
        skaliere() {
            const platz = this.$refs.buehne?.clientWidth ?? this.frameWidth;

            this.scale = Math.min(1, platz / this.frameWidth);
        },

        buehnenHoehe() {
            return Math.round(this.frameHeight * this.scale);
        },

        /**
         * Eine gespeicherte Belegung (#8) zieht den Rahmen im iframe sofort
         * nach — ohne Reload, damit die Scrollposition bleibt. Gemeldet wird
         * der **aufgelöste** Stand des Ankers: `filled` und `unitName` kommen
         * aus dem Resolver, nicht aus dem Formular.
         */
        belegungAktualisiert(detail) {
            if (!detail?.slotKey) {
                return;
            }

            this.sende('placement:updated', {
                key: detail.slotKey,
                filled: Boolean(detail.filled),
                label: detail.unitName ?? '',
                device: detail.device ?? this.device,
                status: detail.status ?? null,
            });
        },

        geladen() {
            this.bereit = true;
            this.sende('device:changed', { device: this.device });
        },

        empfange(event) {
            if (this.origin === null || event.origin !== this.origin) {
                return;
            }

            const nachricht = event.data;

            if (!nachricht || typeof nachricht !== 'object' || typeof nachricht.type !== 'string') {
                return;
            }

            // Anderer Katalogstand im iframe: verwerfen und neu laden lassen.
            if (Number.isInteger(nachricht.catalogVersion) && nachricht.catalogVersion !== this.catalogVersion) {
                this.$wire.$refresh();

                return;
            }

            const nutzlast = nachricht.payload ?? {};

            if (nachricht.type === 'slot:clicked') {
                window.Livewire.dispatch('ad-editor-slot-clicked', { key: nutzlast.key ?? null });

                return;
            }

            if (nachricht.type === 'slots:ready') {
                const keys = Array.isArray(nutzlast.slots)
                    ? nutzlast.slots.map((slot) => slot?.key).filter((key) => typeof key === 'string')
                    : [];

                window.Livewire.dispatch('ad-editor-slots-ready', { keys });
            }
        },

        sende(type, payload = {}) {
            const rahmen = this.$refs.rahmen;

            if (!rahmen?.contentWindow || this.origin === null) {
                return;
            }

            rahmen.contentWindow.postMessage(
                { type, payload, catalogVersion: this.catalogVersion },
                this.origin,
            );
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('adEditorPreview', alpineKomponente);
});
