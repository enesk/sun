<?php

declare(strict_types=1);

namespace App\ProfileRequests\Support;

use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;
use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Support\Facades\URL;

/**
 * Die oeffentliche Statusadresse eines Vorgangs (SUN-RQ #10).
 *
 * Eine Stelle, weil die Adresse an vier Orten gebraucht wird: in den Mails
 * (#10), auf der Bestaetigungsseite des Magic-Links (#6) und im Dialog am
 * Profil (#4, dort direkt ueber `URL::signedRoute`, weil der Aufruf schon
 * auf der Portal-Domain laeuft).
 *
 * **Signiert, aber ohne Ablauf.** Ein Vorgang laeuft Wochen; eine Adresse,
 * die nach 72 Stunden ins Leere zeigt, erzeugt genau die Rueckfrage, die die
 * Seite ersparen soll. Dafuer steht auf der Seite nichts, was nicht der
 * Betrieb selbst eingereicht hat, und die Route ist durchgesetzt
 * (`throttle`).
 *
 * Der Host muss **vor** dem Signieren stimmen: Laravel prueft die Signatur
 * gegen die tatsaechlich aufgerufene Adresse. Eine zentral erzeugte und
 * danach umgeschriebene Adresse waere ungueltig — und eine fremde Domain in
 * der Mail eines Portals sieht aus wie Phishing.
 */
final class RequestLinks
{
    public static function status(ProfileRequest $request, ?Tenant $tenant = null): string
    {
        return self::onPortalDomain(
            $tenant,
            fn (): string => URL::signedRoute('portal.requests.status', ['request' => $request->getKey()]),
        );
    }

    /**
     * Der Vorgang im Central-Panel (#7) — fuer den Tagesdigest (#9).
     *
     * Bewusst zusammengesetzt und nicht ueber die Filament-Resource: der
     * Digest laeuft im Scheduler und in der Queue, wo kein Panel aktiv ist
     * und `getUrl()` deshalb keine Adresse bilden kann. Pfad (`/admin`) und
     * Slug (`anfragen`) stehen im AdminPanelProvider bzw. in der Resource
     * fest; aendert sich einer, aendert sich auch diese Zeile.
     */
    public static function panel(int|ProfileRequest $request): string
    {
        $id = $request instanceof ProfileRequest ? (int) $request->getKey() : $request;

        return rtrim((string) config('app.url'), '/')."/admin/anfragen/{$id}";
    }

    /**
     * Adresse auf der Domain des Portals erzeugen, danach zurueckschalten.
     *
     * @param  callable(): string  $build
     */
    public static function onPortalDomain(?Tenant $tenant, callable $build): string
    {
        $root = rtrim(TenantMailBranding::for($tenant)->url('/'), '/');
        $previous = rtrim(url('/'), '/');

        URL::forceRootUrl($root);

        try {
            return $build();
        } finally {
            URL::forceRootUrl($previous);
        }
    }
}
