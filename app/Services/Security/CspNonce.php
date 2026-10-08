<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Str;

/**
 * Ein Nonce je Request fuer die Content-Security-Policy (#21).
 *
 * Gebraucht wird es nur fuer HTML, das nicht aus dem Repository kommt: die
 * Werbe-Schnipsel aus der Tabelle ad_slots und config('app.tracking_scripts').
 * Beides sind fremde Vorlagen (AdSense, Tag Manager) mit eigenen
 * <script>-Bloecken — die lassen sich nicht in ein Vite-Modul umbauen, ohne die
 * Schnipsel zu zerschneiden. Mit dem Nonce laufen genau diese Bloecke, waehrend
 * eingeschleustes Markup weiter blockiert bleibt; 'unsafe-inline' wuerde
 * dagegen jedes Inline-Skript erlauben und die Policy entwerten.
 *
 * Eigene Skripte brauchen das nicht: die Themes liefern seit #21 keine
 * ausfuehrbaren Inline-Skripte mehr aus.
 *
 * Als Singleton in App\Providers\AppServiceProvider registriert, damit Views
 * und App\Http\Middleware\ContentSecurityPolicy denselben Wert sehen.
 */
class CspNonce
{
    private ?string $nonce = null;

    public function value(): string
    {
        return $this->nonce ??= Str::random(24);
    }

    /**
     * Kurzform fuer die Blade-Views: {!! CspNonce::inject($slot->code) !!}.
     */
    public static function inject(?string $html): string
    {
        return app(self::class)->applyTo($html);
    }

    /**
     * Setzt das Nonce in jedes <script>-Tag eines fremden Schnipsels.
     * Vorhandene nonce-Angaben werden nicht angetastet.
     */
    public function applyTo(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $nonce = $this->value();

        return (string) preg_replace_callback(
            '/<script\b(?![^>]*\bnonce=)([^>]*)>/i',
            static fn (array $treffer): string => '<script nonce="'.$nonce.'"'.$treffer[1].'>',
            $html,
        );
    }
}
