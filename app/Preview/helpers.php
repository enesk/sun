<?php

use App\Preview\PreviewContext;

if (! function_exists('preview_active')) {
    /**
     * Laeuft dieser Request im Vorschau-Modus (SUN-PV-001, Ticket #4)?
     *
     * Die einzige Abfrage fuer Views und Werbe-Komponenten: im Vorschau-Modus
     * entfallen Werbung und Tracking. In Blade als `@preview` / `@unlesspreview`
     * (AppServiceProvider). Das Cookie selbst liest nur
     * {@see \App\Http\Middleware\ResolvePreview}.
     */
    function preview_active(): bool
    {
        return PreviewContext::current()->active;
    }
}
