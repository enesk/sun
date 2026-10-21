<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Steps;

use App\Companies\Deletion\DeletionContext;
use App\Companies\Deletion\DeletionStep;
use App\Companies\Deletion\GoneUrlRegistry;
use App\Companies\Deletion\StepResult;

/**
 * Profil-URL dauerhaft auf 410 stellen (#8).
 *
 * Ohne diesen Schritt antwortet die Route nach dem Loeschen mit 404 — und
 * ein 404 heisst „nicht gefunden", nicht „weg". Google fragt die URL dann
 * noch monatelang ab und zeigt sie derweil im Index. 410 ist das eindeutige
 * Signal und beschleunigt die Entfernung erheblich.
 *
 * Eingetragen werden beide URL-Muster des Portals; welches aktiv ist, kann
 * sich ueber `company_url_pattern` aendern, die Regel soll das ueberleben.
 */
class RegisterGoneUrl implements DeletionStep
{
    public function __construct(private readonly GoneUrlRegistry $registry) {}

    public function key(): string
    {
        return 'register_gone_url';
    }

    public function label(): string
    {
        return '410 für die Profil-URL eintragen';
    }

    public function plan(DeletionContext $context): string
    {
        $paths = $context->paths();

        return $paths === []
            ? 'Keine Profil-URL ermittelbar — kein 410-Eintrag.'
            : 'In gone_urls eintragen (liefert dauerhaft 410): '.implode(', ', $paths);
    }

    public function run(DeletionContext $context): StepResult
    {
        $paths = $context->paths();

        if ($paths === []) {
            return StepResult::skipped('Keine Profil-URL ermittelbar.');
        }

        $stored = $this->registry->register(
            $paths,
            (int) $context->company->getKey(),
            $context->reason,
        );

        return StepResult::ok(
            sprintf('%d URL(s) liefern jetzt 410.', count($stored)),
            ['paths' => $stored],
        );
    }
}
