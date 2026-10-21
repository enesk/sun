<?php

declare(strict_types=1);

namespace App\ProfileRequests\AutoRules;

use App\Models\Portal\Company;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Mail\DeletionScheduledMail;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Support\TenantCompanyResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Verifizierte Loeschung in die Karenzfrist stellen (SUN-RQ #9;
 * docs/profile-requests.md 5).
 *
 * Die Regel loescht **nichts**. Sie setzt `grace_until` auf jetzt +
 * `deletion_grace_hours` (48 h), merkt den Vorgang mit
 * `auto_applicable = true` vor und schickt die Ankuendigung mit Stornolink
 * ({@see DeletionScheduledMail}). Ausgefuehrt wird nach Ablauf der Frist von
 * `requests:run-grace-deletions`; ein Klick auf den Stornolink setzt den
 * Vorgang vorher auf `cancelled` und nimmt ihn damit aus der Auswahl.
 *
 * Betriebe mit laufendem Vertrag oder offenen Leads ({@see ContractGuard})
 * bekommen **keine** Frist: dort bleibt der Vorgang auf `verified` zur
 * Freigabe im Panel, mit dem Grund im Protokoll. Das ist der Unterschied
 * zwischen „laeuft automatisch" und „laeuft automatisch, wenn es nichts
 * kostet".
 */
class ScheduleVerifiedDeletion implements AutoRule
{
    public function __construct(
        private readonly ContractGuard $guard,
        private readonly TenantCompanyResolver $resolver,
    ) {}

    public function key(): string
    {
        return 'schedule_verified_deletion';
    }

    public function appliesTo(ProfileRequest $request): bool
    {
        if ($request->type !== RequestType::DELETE) {
            return false;
        }

        if ($request->status !== RequestStatus::VERIFIED || ! $request->isVerified()) {
            return false;
        }

        if ($request->verification_method !== null && ! $request->verification_method->allowsAutoApply()) {
            return false;
        }

        if ($request->tenant_id === null || $request->company_id === null) {
            return false;
        }

        // Frist laeuft schon — die Regel ist fuer diesen Vorgang erledigt.
        return $request->grace_until === null;
    }

    public function run(ProfileRequest $request): bool
    {
        $grund = $this->guard->blockingReason($request);

        if ($grund !== null) {
            $request->auto_applicable = false;
            $request->save();

            $request->recordEvent(RequestEventType::NOTE, [
                'note' => 'Keine automatische Löschung — bleibt zur Freigabe',
                'rule' => $this->key(),
                'grund' => $grund,
            ]);

            return false;
        }

        $stunden = max(1, (int) config('profile-requests.deletion_grace_hours', 48));

        $request->startGracePeriod($stunden);
        $request->auto_applicable = true;
        $request->save();

        $request->recordEvent(RequestEventType::NOTE, [
            'note' => 'Karenzfrist gestartet',
            'rule' => $this->key(),
            'grace_hours' => $stunden,
            'grace_until' => $request->grace_until?->toIso8601String(),
        ]);

        $this->announce($request);

        return true;
    }

    /**
     * Ankuendigung mit Termin und Stornolink. Scheitert der Versand, bleibt
     * die Frist **nicht** stehen: ohne Mail hat der Betrieb keine Chance zu
     * widersprechen, und dann darf die Loeschung nicht von selbst laufen.
     */
    private function announce(ProfileRequest $request): void
    {
        $empfaenger = trim((string) ($request->verified_email ?? $request->requester_email));

        try {
            if ($empfaenger === '' || filter_var($empfaenger, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('keine gültige Empfängeradresse');
            }

            Mail::to($empfaenger)->send(new DeletionScheduledMail(
                $request,
                $this->resolver->tenant((int) $request->tenant_id),
                $this->companyName($request),
                $request->grace_until ?? now()->addHours((int) config('profile-requests.deletion_grace_hours', 48)),
            ));

            $request->recordEvent(RequestEventType::MAIL_SENT, [
                'mail' => 'deletion_scheduled',
                'to' => $empfaenger,
                'rule' => $this->key(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('profile-requests: Löschankündigung nicht versendet', [
                'request_id' => $request->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            $request->auto_applicable = false;
            $request->grace_until = null;
            $request->save();

            $request->recordEvent(RequestEventType::NOTE, [
                'note' => 'Ankündigung nicht versendet — Löschung bleibt zur Freigabe',
                'rule' => $this->key(),
                'fehler' => Str::limit($exception->getMessage(), 300),
            ]);
        }
    }

    private function companyName(ProfileRequest $request): string
    {
        $name = $request->runInTenantContext(
            fn (): string => (string) (Company::withTrashed()->find($request->company_id)?->name ?? ''),
        );

        return is_string($name) ? $name : '';
    }
}
