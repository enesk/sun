<?php

declare(strict_types=1);

namespace App\Companies\Deletion;

/**
 * Ergebnis eines einzelnen Schritts der Loeschpipeline (#8).
 *
 * FAILED haelt die Pipeline nicht auf: ein fehlgeschlagener IndexNow-Ping
 * darf nicht dazu fuehren, dass die Bilder liegen bleiben. Der Fehler steht
 * im Protokoll, der Lauf gilt als unvollstaendig und kann wiederholt werden.
 */
enum StepStatus: string
{
    case OK = 'ok';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::OK => __('erledigt'),
            self::SKIPPED => __('übersprungen'),
            self::FAILED => __('fehlgeschlagen'),
        };
    }
}
