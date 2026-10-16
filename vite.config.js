import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/blog.js',
                'resources/js/components.js',
                // Nur auf Seiten mit <x-turnstile /> (#5), via Stack 'scripts'.
                'resources/js/turnstile.js',
                'resources/css/filament/admin/theme.css',
                'resources/css/filament/dashboard/theme.css',
                'resources/css/content/theme.css',
                'resources/views/themes/sun-v2/css/app.css',
                'resources/views/themes/sun-v2/js/app.js',
                // Nur im Editor-Modus der Werbeplaetze (#6), siehe
                // layouts/sun.blade.php — kein Gewicht fuer Besucher.
                'resources/views/themes/sun-v2/js/ad-editor.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
