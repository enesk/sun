<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestMailLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Personenbezug eines geschlossenen Vorgangs entfernen (SUN-RQ #15).
 *
 * Gegenstueck zu {@see \App\Console\Commands\RequestsPurgeRawMail}: dort
 * fallen nach 90 Tagen Rohmail und Anhaenge, hier nach 24 Monaten
 * (`profile-requests.anonymize_closed.after_months`) die Kontaktfelder des
 * Vorgangs selbst.
 *
 * **Was bleibt:** Typ, Quelle, Zustand, Portal, Betriebs-ID, alle
 * Zeitpunkte, `verification_method`, `verified_at`, `ai_confidence`, der
 * Ablehnungsgrund und das vollstaendige Protokoll mit allen Feldnamen des
 * Diffs. Damit ist weiterhin belegbar, dass eine Loeschung oder Aenderung
 * bestaetigt und von wem sie ausgefuehrt wurde — der Nachweis nach Art. 17,
 * um dessentwillen der Vorgang die Rohmail ueberlebt.
 *
 * **Was fallen muss:** alles, was auf eine Person zeigt — Name, Adresse,
 * Telefon und Freitext des Anfragenden, die bestaetigte Adresse, die
 * KI-Zusammenfassung und der Antwortentwurf (beide enthalten den Mailtext),
 * die **Werte** des Diffs sowie Absender und Betreff im Postfach-Protokoll.
 *
 * Der Lauf ist idempotent: Merkmal ist `requester_email` ==
 * `anonymize_closed.placeholder_email`. Eine eigene Spalte haette dafuer
 * eine Migration gebraucht; die Spalte ist `NOT NULL`, also traegt sie den
 * Marker selbst.
 *
 * Protokollzeilen sind sonst append-only. Hier werden bestehende Nutzlasten
 * geaendert, weil in ihnen Adressen und Mailtexte stehen (`sent_to`,
 * `subject`, `message`) — bliebe das stehen, waere die Anonymisierung
 * wirkungslos. Geaendert wird nur der Wert der Schluessel aus
 * `anonymize_closed.event_payload_keys`, nie die Zeile selbst.
 */
class AnonymizeClosedRequest
{
    /** Ersatz fuer einen Wert in einer Protokollzeile. */
    public const REDACTED = '[anonymisiert]';

    /**
     * Faellige Vorgaenge: geschlossen, Stichtag erreicht, noch nicht
     * anonymisiert.
     *
     * Stichtag ist der Abschluss, nicht der Eingang. `applied_at` steht nur
     * bei erledigten Vorgaengen; bei abgelehnten, abgelaufenen und
     * stornierten ist `updated_at` der Zeitpunkt, an dem der Zustand zuletzt
     * gesetzt wurde — danach aendert sich an einem Endzustand nichts mehr.
     *
     * @return Builder<ProfileRequest>
     */
    public static function due(?Carbon $cutoff = null): Builder
    {
        $cutoff ??= self::cutoff();

        return ProfileRequest::query()
            ->whereIn('status', self::finalValues())
            ->where('requester_email', '!=', self::placeholderEmail())
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where('applied_at', '<=', $cutoff)
                    ->orWhere(function (Builder $inner) use ($cutoff): void {
                        $inner->whereNull('applied_at')->where('updated_at', '<=', $cutoff);
                    });
            })
            ->orderBy('id');
    }

    public static function cutoff(): Carbon
    {
        return now()->subMonths(self::afterMonths());
    }

    public static function afterMonths(): int
    {
        return max(1, (int) config('profile-requests.anonymize_closed.after_months', 24));
    }

    public static function placeholderEmail(): string
    {
        return (string) config('profile-requests.anonymize_closed.placeholder_email', 'anonymisiert@invalid');
    }

    /** @return list<string> */
    private static function finalValues(): array
    {
        return collect(RequestStatus::cases())
            ->filter(fn (RequestStatus $case) => $case->isFinal())
            ->map(fn (RequestStatus $case) => $case->value)
            ->values()
            ->all();
    }

    /**
     * @return array{ok: bool, message: string, changes: int, events: int, mails: int}
     */
    public function __invoke(ProfileRequest $request): array
    {
        if (! $request->status->isFinal()) {
            return ['ok' => false, 'message' => 'Dieser Vorgang ist noch offen.', 'changes' => 0, 'events' => 0, 'mails' => 0];
        }

        if ($request->requester_email === self::placeholderEmail()) {
            return ['ok' => false, 'message' => 'Bereits anonymisiert.', 'changes' => 0, 'events' => 0, 'mails' => 0];
        }

        $diffFields = $request->diffFields();

        return DB::connection($request->getConnectionName())->transaction(function () use ($request, $diffFields) {
            // forceFill: `verified_email`, `raw_email_path` und die KI-Felder
            // sind nicht alle fillable, und der Zustand bleibt unberuehrt —
            // der Waechter des Automaten greift hier also nicht.
            $request->forceFill([
                'requester_email' => self::placeholderEmail(),
                'requester_name' => null,
                'requester_phone' => null,
                'message' => null,
                'verified_email' => null,
                'ai_summary' => null,
                'ai_reply_draft' => null,
                // Die Datei ist nach `retention_days` ohnehin weg; der Pfad
                // traegt die Anfrage-ID und sonst nichts, bleibt aber ein
                // Zeiger auf eine Ablage, die es nicht mehr gibt.
                'raw_email_path' => null,
            ])->save();

            // Feldnamen bleiben, Werte fallen: der Diff belegt weiterhin,
            // *was* geaendert wurde, ohne Telefonnummer und Beschreibung.
            $changes = $request->changes()
                ->where(function (Builder $query): void {
                    $query->whereNotNull('old_value')->orWhereNotNull('new_value');
                })
                ->update(['old_value' => null, 'new_value' => null]);

            $events = $this->redactEvents($request);
            $mails = $this->redactMailLog($request);

            $request->recordEvent(RequestEventType::NOTE, [
                'note' => 'Vorgang anonymisiert (Aufbewahrungsfrist)',
                'after_months' => self::afterMonths(),
                'diff_fields' => $diffFields,
                'changes' => $changes,
                'events' => $events,
                'mails' => $mails,
            ]);

            return [
                'ok' => true,
                'message' => 'Kontaktfelder entfernt, Nachweis erhalten.',
                'changes' => $changes,
                'events' => $events,
                'mails' => $mails,
            ];
        });
    }

    /**
     * Adressen und Mailtexte aus den Nutzlasten des Protokolls nehmen.
     * Betroffen sind nur die Schluessel der Konfiguration, und nur dort, wo
     * ein Wert steht — eine Zeile ohne solchen Schluessel wird nicht
     * angefasst.
     */
    private function redactEvents(ProfileRequest $request): int
    {
        $keys = (array) config('profile-requests.anonymize_closed.event_payload_keys', []);

        if ($keys === []) {
            return 0;
        }

        $betroffen = 0;

        $request->events()->whereNotNull('payload')->cursor()->each(function ($event) use ($keys, &$betroffen): void {
            $payload = (array) ($event->payload ?? []);
            $neu = $payload;

            foreach ($keys as $key) {
                if (array_key_exists($key, $neu) && $neu[$key] !== null && $neu[$key] !== self::REDACTED) {
                    $neu[$key] = self::REDACTED;
                }
            }

            if ($neu === $payload) {
                return;
            }

            $event->forceFill(['payload' => $neu])->save();
            $betroffen++;
        });

        return $betroffen;
    }

    /**
     * Postfach-Protokoll (#5): Absender und Betreff raus. `message_id_hash`
     * bleibt — ohne ihn wuerde eine erneute Zustellung derselben Mail als
     * neue Anfrage durchgehen.
     */
    private function redactMailLog(ProfileRequest $request): int
    {
        return RequestMailLog::query()
            ->where('request_id', $request->getKey())
            ->where(function (Builder $query): void {
                $query->whereNotNull('from_email')->orWhereNotNull('subject');
            })
            ->update(['from_email' => null, 'subject' => null]);
    }
}
