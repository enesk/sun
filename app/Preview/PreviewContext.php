<?php

declare(strict_types=1);

namespace App\Preview;

/**
 * Vorschau-Zustand des aktuellen Requests (SUN-PV-001, Ticket #2).
 *
 * {@see \App\Http\Middleware\ResolvePreview} bindet bei gueltigem Cookie
 * eine aktive Instanz in den Container. Ohne Bindung liefert
 * `app(PreviewContext::class)` eine inaktive Instanz — Abfrage also immer
 * ueber `app(PreviewContext::class)->active`.
 */
final class PreviewContext
{
    public function __construct(
        public readonly bool $active = false,
        public readonly ?int $linkId = null,
    ) {}

    public static function current(): self
    {
        return app(self::class);
    }
}
