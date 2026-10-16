<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Ads\Dto\AdHeadDependency;

/**
 * Entdoppelt die Head-Abhaengigkeiten einer Seite (SUN-AD-004, Ticket #5).
 *
 * Jeder Provider meldet die Skripte an, die sein Fragment braucht — sechs
 * AdSense-Anker auf einer Seite melden sechsmal denselben Loader. Die
 * Komponente fragt hier, welche davon noch nicht im Stack `head` stehen, und
 * schreibt nur die. `handle` ist der Schluessel, nicht die URL: der Loader
 * traegt die Publisher-ID in der Query, und die ist je Portal eine andere —
 * zweimal derselbe Loader mit verschiedenen Kennungen waere trotzdem falsch.
 *
 * `scoped` gebunden (AdsServiceProvider): die Merkliste gilt fuer eine
 * Anfrage. In einem Octane- oder Queue-Worker laeuft sonst die zweite Seite
 * ohne ihren Loader.
 */
final class AdHeadRegistry
{
    /** @var array<string, true> */
    private array $written = [];

    /**
     * Die noch nicht ausgegebenen Abhaengigkeiten — und merkt sie als
     * ausgegeben vor. Zweiter Aufruf mit demselben `handle` liefert nichts.
     *
     * @param  list<AdHeadDependency>  $dependencies
     * @return list<AdHeadDependency>
     */
    public function take(array $dependencies): array
    {
        $neu = [];

        foreach ($dependencies as $dependency) {
            if (! $dependency instanceof AdHeadDependency || isset($this->written[$dependency->handle])) {
                continue;
            }

            $this->written[$dependency->handle] = true;
            $neu[] = $dependency;
        }

        return $neu;
    }

    public function has(string $handle): bool
    {
        return isset($this->written[$handle]);
    }

    public function flush(): void
    {
        $this->written = [];
    }
}
