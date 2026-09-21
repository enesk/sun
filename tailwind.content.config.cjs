/**
 * Token-Export Content-Pipeline (Ticket #5)
 * ---------------------------------------------------------------------------
 * Gestaltungsquelle: Dokument "Designsystem Content-Dashboard (Ticket #5)".
 *
 * WICHTIG zur Einordnung: Das Projekt laeuft auf Tailwind 4. Dort sind Tokens
 * CSS-first, die verbindliche Quelle ist deshalb `resources/css/content/theme.css`
 * (Panel) beziehungsweise `resources/css/portal.css` (Leserseite). Diese Datei
 * ist der vom Ticket verlangte Konfigurationsvorschlag: ein maschinenlesbarer
 * Export derselben Werte. Sie dient
 *   - als Nachschlagewerk fuer Umsetzende (Statusfarben, Abstaende, Breakpoints),
 *   - als Quelle fuer Node-Werkzeuge (E-Mail-Templates, Diagrammfarben, Tests),
 *   - als Rueckfallebene, falls ein Teil des Panels ueber eine klassische
 *     Tailwind-Konfiguration gebaut wird.
 *
 * Dateiendung: `.cjs`, weil package.json `"type": "module"` setzt. Damit ist
 * `module.exports` gueltig und die Datei laesst sich sowohl per require() als
 * auch per `import cfg from './tailwind.content.config.cjs'` lesen.
 *
 * Regel: Werte werden hier NICHT eigenstaendig geaendert. Aenderung immer erst
 * in theme.css, dann hier nachziehen — sonst driften Panel und Export.
 */

/** Markenfarbe des Content-Panels (Teal, klar getrennt vom Admin-Violett). */
const content = {
    50: '#eefbf8',
    100: '#d3f5ee',
    200: '#a9ebe0',
    300: '#72dbcd',
    400: '#3cc2b3',
    500: '#17a698',
    600: '#0d857b',
    700: '#0d6a63',
    800: '#105450',
    900: '#114643',
    950: '#032927',
};

/**
 * Statusfarben — die sieben Werte von App\\Content\\Enums\\DisplayStatus.
 * Normativ und identisch in Board, Kalender, Tabellen, Leistung und E-Mail.
 *
 * Rollen je Status (Ticket #30):
 *   bg      Flaeche der Pille / des Filament-Badges
 *   fg      Text und Rand, >= 4,5:1 auf bg und auf Weiss
 *   dot     8-px-Punkt und 20-px-Zaehlmarke; identisch mit fg, damit der
 *           Punkt >= 4,5:1 traegt und weisse Zahlen darauf lesbar sind
 *   fill    grosse Flaechen (Fortschrittsbalken, Kalenderbalken,
 *           Diagrammreihen), >= 3:1 auf Weiss; nur auf surface-card
 *   filament Name der im Panel registrierten Filament-Farbe
 *           (config/content.php -> 'colors'); Skala 50 = bg, 600 = fg
 *
 * DraftStatus und TopicStatus haben KEINE eigenen Farben; sie werden ueber
 * DisplayStatus::fromDraft() / fromTopic() abgebildet.
 */
const status = {
    idea:       { label: 'Themenvorschlag', bg: '#f4f5f7', fg: '#475569', dot: '#475569', fill: '#64748b', filament: 'status-idea',       order: 1 },
    scheduled:  { label: 'Eingeplant',      bg: '#eff6ff', fg: '#1d4ed8', dot: '#1d4ed8', fill: '#3b82f6', filament: 'status-scheduled',  order: 2 },
    generating: { label: 'In Erstellung',   bg: '#f5f3ff', fg: '#6d28d9', dot: '#6d28d9', fill: '#8b5cf6', filament: 'status-generating', order: 3, animated: true },
    review:     { label: 'Zur Prüfung',     bg: '#fffbeb', fg: '#b45309', dot: '#b45309', fill: '#d97706', filament: 'status-review',     order: 4, badge: true },
    published:  { label: 'Veröffentlicht',  bg: '#ecfdf5', fg: '#047857', dot: '#047857', fill: '#059669', filament: 'status-published',  order: 5 },
    failed:     { label: 'Fehlgeschlagen',  bg: '#fef2f2', fg: '#b91c1c', dot: '#b91c1c', fill: '#dc2626', filament: 'status-failed',     order: 6 },
    archived:   { label: 'Zurückgezogen',   bg: '#f8fafc', fg: '#64716f', dot: '#64716f', fill: '#7c8a88', filament: 'status-archived',   order: 7 },
};

/**
 * Vollstaendige Filament-Skalen der Statusfarben. Quelle ist
 * config/content.php ('colors'); hier gespiegelt fuer Node-Werkzeuge.
 * Schattierung 50 = bg, 600 = fg. Schattierungen 100-500 erreichen auf 50
 * bewusst weniger als 4,5:1, damit Filament die Textfarbe eines Badges
 * deterministisch auf 600 legt.
 */
const statusRamps = {
    'status-idea':       { 50: '#f4f5f7', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#7b8794', 600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a', 950: '#020617' },
    'status-scheduled':  { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#1d4ed8', 700: '#1e40af', 800: '#1e3a8a', 900: '#172554', 950: '#0d1233' },
    'status-generating': { 50: '#f5f3ff', 100: '#ede9fe', 200: '#ddd6fe', 300: '#c4b5fd', 400: '#a78bfa', 500: '#8b5cf6', 600: '#6d28d9', 700: '#5b21b6', 800: '#4c1d95', 900: '#3b1673', 950: '#2e1065' },
    'status-review':     { 50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d', 400: '#fbbf24', 500: '#d97706', 600: '#b45309', 700: '#92400e', 800: '#78350f', 900: '#5c2a0c', 950: '#451a03' },
    'status-published':  { 50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7', 400: '#34d399', 500: '#059669', 600: '#047857', 700: '#065f46', 800: '#064e3b', 900: '#04412f', 950: '#022c22' },
    'status-failed':     { 50: '#fef2f2', 100: '#fee2e2', 200: '#fecaca', 300: '#fca5a5', 400: '#f87171', 500: '#ef4444', 600: '#b91c1c', 700: '#991b1b', 800: '#7f1d1d', 900: '#641818', 950: '#450a0a' },
    'status-archived':   { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e7e7e2', 300: '#cbd5e1', 400: '#94a3b8', 500: '#7c8a88', 600: '#64716f', 700: '#55605e', 800: '#3d4a48', 900: '#2a3533', 950: '#16211f' },
    'status-unchanged':  { 50: '#f4f5f7', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#7b8794', 600: '#3d4a48', 700: '#2a3533', 800: '#1e293b', 900: '#0f172a', 950: '#020617' },
    'status-paused':     { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#1d4ed8', 700: '#1e40af', 800: '#1e3a8a', 900: '#172554', 950: '#0d1233' },
};

/**
 * Abbildung der internen Zustaende auf die Anzeigefarbe. Spiegelt
 * DisplayStatus::fromDraft() / fromTopic() und ist nur fuer Werkzeuge
 * ausserhalb von PHP gedacht (E-Mail-Vorschau, Diagramme).
 */
const statusFromDraft = {
    generating: 'generating',
    generated: 'generating',
    checking: 'generating',
    approved: 'scheduled',
    scheduled: 'scheduled',
    review: 'review',
    published: 'published',
    failed: 'failed',
};

const statusFromTopic = {
    discovered: 'idea',
    scored: 'idea',
    selected: 'scheduled',
    rejected: 'archived',
};

/**
 * Ratgeber-Dashboard (design/guide-dashboard.md §2, Ticket #22). Keine neuen
 * Statusrollen — nur zwei Bauformen und die Fuellungen des Tagesbalkens.
 *
 * guideStatus   Sonderbauformen .content-status--unchanged / --paused;
 *               filament = registrierte Farbe gleichen Tons (config/content.php)
 * runFill       Segmente des gestapelten Tagesbalkens (--color-run-*-fill);
 *               "new" traegt zusaetzlich die Schraffur runNewPattern
 * runDisplay    Anzeigewert eines Laufs, spiegelt App\Guide\Enums\RunDisplay
 *               (pill() = Modifikator, color() = 'status-' + pill)
 * topicStatus   spiegelt App\Guide\Enums\TopicStatus::pill()
 */
const guideStatus = {
    unchanged: { label: 'Geprüft, unverändert', bg: status.idea.bg, fg: '#3d4a48', dot: status.published.dot, filament: 'status-unchanged' },
    paused:    { label: 'Pausiert', bg: status.scheduled.bg, fg: status.scheduled.fg, dot: status.scheduled.fg, filament: 'status-paused', mark: 'pause' },
};

const runFill = {
    new: status.published.fill,
    updated: status.published.fill,
    unchanged: '#6fbf9f',
    review: status.review.fill,
    failed: status.failed.fill,
    active: status.generating.fill,
    open: '#d3d3cc',
};

const runNewPattern = `repeating-linear-gradient(135deg, ${status.published.fill} 0 4px, ${status.published.dot} 4px 6px)`;

const runDisplay = {
    wartet:         { label: 'Wartet',               pill: 'idea',       rank: 3 },
    'in-arbeit':    { label: 'In Arbeit',            pill: 'generating', rank: 2 },
    pruefung:       { label: 'Zur Prüfung',          pill: 'review',     rank: 1 },
    neu:            { label: 'Neu erschienen',       pill: 'published',  rank: 4, fill: 'new' },
    aktualisiert:   { label: 'Aktualisiert',         pill: 'published',  rank: 5, fill: 'updated' },
    unveraendert:   { label: 'Geprüft, unverändert', pill: 'unchanged',  rank: 6, fill: 'unchanged' },
    fehlgeschlagen: { label: 'Fehlgeschlagen',       pill: 'failed',     rank: 0, fill: 'failed' },
};

const topicStatus = {
    draft: 'idea',
    outline_pending: 'review',
    active: 'published',
    paused: 'paused',
    archived: 'archived',
};

/** Qualitaetsscore des Gates (#15) — eigene Skala, damit ein guter Score
 *  nicht wie der Status "Veroeffentlicht" aussieht. */
const score = {
    good: '#0d857b',
    mid: '#b45309',
    poor: '#b91c1c',
};

module.exports = {
    /* Nur Panel-Quellen. Die Leserseite bleibt beim Portal-Theme. */
    content: [
        './app/Filament/Content/**/*.php',
        './app/Content/**/*.php',
        './resources/views/filament/content/**/*.blade.php',
    ],

    theme: {
        /* Breakpoints wie im Bestand — keine eigenen Stufen im Panel. */
        screens: {
            sm: '640px',
            md: '768px',
            lg: '1024px',
            xl: '1280px',
            '2xl': '1536px',
        },

        extend: {
            colors: {
                content,
                primary: content,

                surface: {
                    page: '#f7f7f5',
                    card: '#ffffff',
                    sunken: '#f0f0ed',
                    nav: '#103f3c',
                    'nav-active': '#17a698',
                },
                line: {
                    soft: '#e7e7e2',
                    strong: '#d3d3cc',
                },
                text: {
                    strong: '#16211f',
                    base: '#3d4a48',
                    muted: '#64716f',
                },

                status: Object.fromEntries(
                    Object.entries(status).map(([key, value]) => [
                        key,
                        { DEFAULT: value.fg, bg: value.bg, fg: value.fg, dot: value.dot, fill: value.fill },
                    ]),
                ),

                score,

                run: Object.fromEntries(
                    Object.entries(runFill).map(([key, value]) => [key, { fill: value }]),
                ),
            },

            /* 8px-Skala, deckungsgleich mit --portal-space-* */
            spacing: {
                'content-1': '0.25rem',
                'content-2': '0.5rem',
                'content-3': '0.75rem',
                'content-4': '1rem',
                'content-6': '1.5rem',
                'content-8': '2rem',
                'content-12': '3rem',
                'content-16': '4rem',
            },

            fontFamily: {
                content: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                'content-nums': ['Inter Tight', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },

            fontSize: {
                /* Panel */
                'content-metric': ['2.75rem', { lineHeight: '1.05', fontWeight: '600' }],
                'content-h1': ['1.75rem', { lineHeight: '1.25', fontWeight: '600' }],
                'content-h2': ['1.25rem', { lineHeight: '1.3', fontWeight: '600' }],
                'content-h3': ['1rem', { lineHeight: '1.4', fontWeight: '600' }],
                'content-body': ['0.9375rem', { lineHeight: '1.55' }],
                'content-table': ['0.875rem', { lineHeight: '1.4' }],
                'content-label': ['0.75rem', { lineHeight: '1.3' }],

                /* Leserseite — Lesbarkeit vor Dichte */
                'read-h1': ['2.25rem', { lineHeight: '1.2', fontWeight: '700' }],
                'read-h2': ['1.625rem', { lineHeight: '1.3', fontWeight: '700' }],
                'read-h3': ['1.25rem', { lineHeight: '1.35', fontWeight: '600' }],
                'read-body': ['1.125rem', { lineHeight: '1.7' }],
                'read-lead': ['1.25rem', { lineHeight: '1.6' }],
                'read-small': ['0.9375rem', { lineHeight: '1.6' }],
            },

            borderRadius: {
                'content-sm': '0.375rem',
                'content-md': '0.5rem',
                'content-lg': '0.75rem',
                'content-xl': '1rem',
            },

            boxShadow: {
                'content-card': '0 1px 2px rgb(22 33 31 / 0.06), 0 1px 3px rgb(22 33 31 / 0.04)',
                'content-raise': '0 4px 12px rgb(22 33 31 / 0.10)',
                'content-over': '0 16px 40px rgb(22 33 31 / 0.16)',
            },

            /* Textspalte der Leserseite: 680–720px bei 18px Fliesstext.
               Nicht ueberschreiben — laengere Zeilen kosten Lesegeschwindigkeit. */
            maxWidth: {
                'read-column': '45rem',   /* 720px */
                'read-narrow': '42.5rem', /* 680px */
            },

            /* Feste Hoehen gegen Layoutverschiebungen (CLS < 0,1).
               Bilder und Anzeigen bekommen ihren Platz vor dem Laden. */
            aspectRatio: {
                'read-hero': '16 / 9',
                'read-inline': '3 / 2',
                'read-infographic': '4 / 5',
            },
        },
    },

    /* Nur zur Dokumentation — von Werkzeugen ausserhalb von Tailwind lesbar. */
    saasykitContent: {
        status,
        statusRamps,
        statusFromDraft,
        statusFromTopic,
        guideStatus,
        runFill,
        runNewPattern,
        runDisplay,
        topicStatus,
        score,
        /* Klickflaeche mindestens 44x44px, Tabellenzeile 44px, Statuspille 24px.
           Das Filament-Badge hat dieselbe Bauform: 24px hoch, Radius 6px,
           Punkt davor (Regeln in resources/css/content/theme.css). */
        sizing: { hitArea: 44, tableRow: 44, statusPill: 24, statusPillRadius: 6, statusDot: 8 },
    },
};
