<?php

declare(strict_types=1);

namespace App\Content\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Die Zuruecknahme eines Entwurfs oder Artikels wurde aufgehoben.
 * Der Publisher (#21) nimmt den Artikel wieder in Sitemap und Feed auf und
 * entfernt noindex bzw. die Weiterleitung.
 */
class ContentRepublished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $content,
    ) {}
}
