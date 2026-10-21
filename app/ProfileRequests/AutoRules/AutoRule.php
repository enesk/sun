<?php

declare(strict_types=1);

namespace App\ProfileRequests\AutoRules;

use App\ProfileRequests\Models\ProfileRequest;

/**
 * Eine Auto-Regel (SUN-RQ #9).
 *
 * Der {@see RuleRunner} laeuft nach jeder erfolgreichen Verifikation durch
 * die Liste aus `profile-requests.auto.rules` und nimmt die erste Regel,
 * deren {@see self::appliesTo()} zutrifft.
 *
 * Die Trennung ist Absicht: `appliesTo()` entscheidet **nur**, ob die Regel
 * zustaendig ist, und aendert nichts. Damit laesst sich jede Regel im Tinker
 * gegen einen echten Vorgang pruefen, ohne ihn anzufassen — und eine neue
 * Regel ist eine neue Klasse in der Liste, nie ein weiteres `if` in einer
 * bestehenden.
 */
interface AutoRule
{
    /** Kurzname fuer das Protokoll in `request_events`. */
    public function key(): string;

    /** Ist diese Regel fuer den Vorgang zustaendig? Aendert nichts. */
    public function appliesTo(ProfileRequest $request): bool;

    /**
     * Die Regel ausfuehren. `true`, wenn sie etwas getan hat (uebernommen,
     * Karenzfrist gestartet); `false`, wenn der Vorgang zur Freigabe
     * liegenbleibt. Jeder Ausgang steht in `request_events`.
     */
    public function run(ProfileRequest $request): bool;
}
