<?php

declare(strict_types=1);

namespace App\ProfileRequests\Support;

use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Pilotschalter des Rollouts (SUN-RQ #12).
 *
 * Der Self-Service am Profil (#4) steht im Theme auf jeder Profilseite. Der
 * Rollout laeuft aber in zwei Stufen: erst drei Portale fuer eine Woche,
 * dann alle. Damit dazwischen kein Deploy und kein Theme-Eingriff noetig ist,
 * entscheidet eine Liste in der Konfiguration, auf welchen Portalen der
 * Dialog erscheint.
 *
 * `REQUESTS_PILOT_SELF_SERVICE_TENANTS` ist eine Komma-Liste aus
 * `tenants.id` oder Portal-Domains. **Leer heisst: alle Portale** — das ist
 * der Endzustand, und eine leere Variable darf das Feature nie abschalten.
 *
 * Die E-Mail-Ingestion braucht keinen Schalter: sie haengt daran, welche
 * Portal-Postfaecher in das Sammelpostfach weiterleiten, und das steht beim
 * Mailhoster, nicht hier.
 */
final class PilotGate
{
    /** @var list<string>|null */
    private static ?array $liste = null;

    /**
     * Darf der Self-Service-Dialog auf diesem Portal erscheinen?
     *
     * Ohne Tenant-Kontext (Central-Panel, Konsole) gilt die Antwort fuer die
     * Frage „ueberhaupt irgendwo offen?" — deshalb true, sobald die Liste
     * leer ist.
     */
    public static function selfServiceEnabled(?Tenant $tenant = null): bool
    {
        $liste = self::list();

        if ($liste === []) {
            return true;
        }

        $tenant ??= tenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        $kennungen = [
            (string) $tenant->getKey(),
            (string) self::normalize((string) ($tenant->domain ?? '')),
        ];

        return array_intersect(array_filter($kennungen), $liste) !== [];
    }

    /**
     * Die Portale des Pilots, wie sie in der Konfiguration stehen — IDs als
     * Zeichenkette, Domains normalisiert. Leer = alle.
     *
     * @return list<string>
     */
    public static function list(): array
    {
        if (self::$liste !== null) {
            return self::$liste;
        }

        $roh = (string) config('profile-requests.pilot.self_service_tenants', '');

        $eintraege = [];

        foreach (explode(',', $roh) as $eintrag) {
            $eintrag = trim($eintrag);

            if ($eintrag === '') {
                continue;
            }

            $eintraege[] = ctype_digit($eintrag) ? $eintrag : (string) self::normalize($eintrag);
        }

        return self::$liste = array_values(array_unique(array_filter($eintraege)));
    }

    /** Nach einer Konfigurationsaenderung im selben Prozess (Konsole, Tests). */
    public static function forget(): void
    {
        self::$liste = null;
    }

    private static function normalize(string $domain): ?string
    {
        $domain = Str::lower(trim($domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = (string) preg_replace('/^www\./', '', $domain);
        $domain = trim(explode('/', $domain)[0], ". \t\n\r\0\x0B");

        return $domain === '' ? null : $domain;
    }
}
