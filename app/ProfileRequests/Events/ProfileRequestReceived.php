<?php

declare(strict_types=1);

namespace App\ProfileRequests\Events;

use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ein Vorgang ist neu hereingekommen und sein Typ steht fest (SUN-RQ #23).
 *
 * Bewusst nicht am Model-Ereignis `created` aufgehängt, sondern an den
 * beiden Erfassungswegen:
 *
 *  * Das Self-Service-Formular kennt den Typ sofort und feuert beim Anlegen
 *    ({@see \App\ProfileRequests\Actions\CreateRequest}).
 *  * Beim Mailweg steht der Typ erst nach der Klassifikation fest — eine
 *    Mail ist bis dahin `other`. Deshalb feuert dort
 *    {@see \App\ProfileRequests\Jobs\ClassifyRequest} nach dem ersten Lauf.
 *
 * Zwei Dinge feuern absichtlich **nicht**: der Import des Altbestands
 * (#16) — 179 Mails auf einen Schlag wären keine Benachrichtigung, sondern
 * eine Strafe — und ein erneutes Einlesen aus dem Panel (#24), denn der
 * Vorgang ist dann längst bekannt.
 */
class ProfileRequestReceived
{
    use Dispatchable;

    public function __construct(public readonly ProfileRequest $request) {}
}
