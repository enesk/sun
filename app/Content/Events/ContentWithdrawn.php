<?php

declare(strict_types=1);

namespace App\Content\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ein Entwurf oder Artikel wurde zurueckgezogen (depubliziert/verworfen).
 *
 * Der Publisher (#21) haengt sich hier ein: Artikel aus Sitemap und Feed
 * entfernen, Cache invalidieren, noindex bzw. Weiterleitung setzen. Der
 * Fingerprint bleibt bewusst registriert.
 */
class ContentWithdrawn
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $content,
        public string $reason,
    ) {}
}
