<?php

declare(strict_types=1);

namespace App\ProfileRequests\Models;

use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Models\User;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Enums\VerificationMethod;
use App\ProfileRequests\Exceptions\RequestTransitionException;
use App\ProfileRequests\Observers\ProfileRequestObserver;
use App\ProfileRequests\Support\TenantCompanyResolver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Eine Profil-Anfrage aus einem der Portale (SUN-RQ, Ticket #3;
 * docs/profile-requests.md 8 und 9).
 *
 * Liegt in der Central-DB, damit die Inbox (#7) alle Portale in einer
 * Abfrage zeigt. `company_id` ist deshalb nur eine Referenz in die Tenant-DB
 * ohne Fremdschluessel; aufgeloest wird sie ueber {@see self::company()},
 * das den Kontextwechsel kapselt — der Aufrufer bleibt, wo er ist.
 *
 * **Der Zustand wird nur ueber die Methoden des Automaten geaendert:**
 * markClassified(), requestVerification(), verify(), apply(), reject(),
 * markExpired(), cancel(). Jede schreibt in derselben Transaktion genau eine
 * Zeile in `request_events`. Ein direktes `$request->status = ...; save()`
 * lehnt der {@see ProfileRequestObserver} mit einer Exception ab, denn dabei
 * entstuende ein Vorgang ohne Protokoll.
 *
 * Der Verifikationstoken liegt nur als SHA-256 in der Tabelle. Den Klartext
 * gibt {@see self::requestVerification()} einmalig zurueck; er existiert
 * danach ausschliesslich im Magic-Link der versendeten Mail (#10).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $company_id
 * @property RequestType $type
 * @property RequestSource $source
 * @property RequestStatus $status
 * @property string|null $requester_name
 * @property string $requester_email
 * @property string|null $requester_phone
 * @property string|null $message
 * @property string|null $raw_email_path
 * @property string|null $external_ref
 * @property VerificationMethod|null $verification_method
 * @property string|null $verification_token_hash
 * @property \Illuminate\Support\Carbon|null $verification_expires_at
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $verified_email
 * @property float|null $ai_confidence
 * @property string|null $ai_summary
 * @property string|null $ai_reply_draft
 * @property bool $auto_applicable
 * @property \Illuminate\Support\Carbon|null $grace_until
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property int|null $applied_by
 * @property string|null $rejected_reason
 */
#[ObservedBy(ProfileRequestObserver::class)]
class ProfileRequest extends Model
{
    use CentralConnection;

    protected $table = 'profile_requests';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'type',
        'source',
        'status',
        'requester_name',
        'requester_email',
        'requester_phone',
        'message',
        'raw_email_path',
        'external_ref',
        'ai_confidence',
        'ai_summary',
        'ai_reply_draft',
        'auto_applicable',
        'grace_until',
    ];

    /**
     * Vorbelegung auch auf dem Model, nicht nur in der Tabelle: nach einem
     * create() ohne `status` haette die Instanz sonst keinen Zustand, und der
     * Observer koennte die erste Protokollzeile nicht schreiben.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
        'auto_applicable' => false,
    ];

    /**
     * Klartext des gerade erzeugten Magic-Link-Tokens — nur im Speicher, nie
     * in der Datenbank. Nach dem Versand der Mail ist er verloren.
     */
    public ?string $plainVerificationToken = null;

    /**
     * Fenster, in dem der Observer eine Aenderung an `status` durchlaesst.
     * Nur {@see self::transitionTo()} oeffnet es.
     */
    protected bool $inTransition = false;

    protected ?Company $resolvedCompany = null;

    protected bool $companyResolved = false;

    protected function casts(): array
    {
        return [
            'type' => RequestType::class,
            'source' => RequestSource::class,
            'status' => RequestStatus::class,
            'verification_method' => VerificationMethod::class,
            'verification_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'ai_confidence' => 'float',
            'auto_applicable' => 'boolean',
            'grace_until' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Relationen
    // ------------------------------------------------------------------

    /** Das Portal in der Central-DB. Der Betrieb dagegen: {@see self::company()}. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * Der Diff (#11).
     *
     * Achtung: `changes` ist auch eine geschuetzte Eigenschaft von
     * Illuminate\Database\Eloquent\Model (die geaenderten Attribute). Von
     * aussen liefert `$request->changes` trotzdem die Relation, **innerhalb**
     * dieser Klasse aber das Array des Models. Deshalb wird hier drinnen nie
     * `$this->changes` gelesen, sondern {@see self::diffLines()}.
     *
     * @return HasMany<RequestChange, $this>
     */
    public function changes(): HasMany
    {
        return $this->hasMany(RequestChange::class, 'request_id');
    }

    /**
     * Die Diff-Zeilen — nutzt die geladene Relation, wenn es eine gibt.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, RequestChange>
     */
    public function diffLines(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->relationLoaded('changes')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, RequestChange> $loaded */
            $loaded = $this->getRelation('changes');

            return $loaded;
        }

        return $this->changes()->get();
    }

    /** @return list<string> */
    public function diffFields(): array
    {
        return $this->diffLines()->pluck('field')->values()->all();
    }

    /** @return HasMany<RequestEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class, 'request_id')->orderBy('id');
    }

    /** @return HasMany<RequestAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'request_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /** Alles, was noch nicht in einem Endzustand ist (#7). @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', RequestStatus::openValues());
    }

    /** @param  Builder<self>  $query */
    public function scopeForTenant(Builder $query, Tenant|int|string $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    /** @param  Builder<self>  $query */
    public function scopeVerified(Builder $query): void
    {
        $query->whereNotNull('verified_at');
    }

    /** @param  Builder<self>  $query */
    public function scopeOfType(Builder $query, RequestType|string $type): void
    {
        $query->where('type', $type instanceof RequestType ? $type->value : $type);
    }

    /**
     * Verifizierte Vorgaenge, deren Karenzfrist abgelaufen ist — die Auswahl
     * der Auto-Regel fuer Loeschungen (#9).
     *
     * @param  Builder<self>  $query
     */
    public function scopeDueForAutoApply(Builder $query): void
    {
        $query->where('status', RequestStatus::VERIFIED->value)
            ->where('auto_applicable', true)
            ->where(function (Builder $inner) {
                $inner->whereNull('grace_until')->orWhere('grace_until', '<=', now());
            });
    }

    /** @param  Builder<self>  $query */
    public function scopeAwaitingVerification(Builder $query): void
    {
        $query->where('status', RequestStatus::AWAITING_VERIFICATION->value);
    }

    // ------------------------------------------------------------------
    // Tenant-Kontext
    // ------------------------------------------------------------------

    /**
     * Der Betrieb zu dieser Anfrage, aufgeloest in der Tenant-DB seines
     * Portals. null, solange die Zuordnung fehlt oder der Eintrag dort nicht
     * (mehr) existiert.
     *
     * Der Aufrufer braucht keinen Tenant-Kontext und behaelt den eigenen —
     * das kapselt der {@see TenantCompanyResolver} mit `Tenant::run()`.
     * Das Ergebnis wird je Instanz gemerkt; `refresh()` oder
     * {@see self::forgetCompany()} holt es neu.
     */
    public function company(bool $withTrashed = false): ?Company
    {
        if ($this->companyResolved && ! $withTrashed) {
            return $this->resolvedCompany;
        }

        $company = app(TenantCompanyResolver::class)->resolve(
            $this->tenant_id === null ? null : (int) $this->tenant_id,
            $this->company_id === null ? null : (int) $this->company_id,
            $withTrashed,
        );

        if (! $withTrashed) {
            $this->resolvedCompany = $company;
            $this->companyResolved = true;
        }

        return $company;
    }

    public function forgetCompany(): void
    {
        $this->resolvedCompany = null;
        $this->companyResolved = false;
    }

    /**
     * Arbeit im Kontext des Portals dieser Anfrage ausfuehren — der Weg fuer
     * die Ein-Klick-Aktionen (#7) und die Loeschpipeline (#8).
     *
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn|null
     */
    public function runInTenantContext(callable $callback): mixed
    {
        if ($this->tenant_id === null) {
            return null;
        }

        return app(TenantCompanyResolver::class)->runFor((int) $this->tenant_id, $callback);
    }

    // ------------------------------------------------------------------
    // Zustandsautomat — jede Methode schreibt genau ein request_event
    // ------------------------------------------------------------------

    /**
     * Klassifikation uebernehmen (#6): Typ, Portal, Betrieb und der
     * KI-Vorschlag stehen fest.
     *
     * @param  array<string, mixed>  $attributes  z. B. type, tenant_id, company_id, ai_*
     */
    public function markClassified(array $attributes = [], string $actorType = RequestEvent::ACTOR_SYSTEM, ?int $actorId = null): bool
    {
        return $this->transitionTo(
            RequestStatus::CLASSIFIED,
            RequestEventType::CLASSIFIED,
            $attributes,
            ['fields' => array_keys($attributes)],
            $actorType,
            $actorId,
        );
    }

    /**
     * Magic-Link anstossen: erzeugt den Token, legt nur dessen SHA-256 ab und
     * gibt den Klartext **einmalig** zurueck. Er gehoert in die Mail (#10)
     * und sonst nirgendwohin — nicht ins Protokoll, nicht in ein Log.
     *
     * Rueckgabe null, wenn der Uebergang nicht moeglich war.
     */
    public function requestVerification(?string $sendTo = null, string $actorType = RequestEvent::ACTOR_SYSTEM, ?int $actorId = null): ?string
    {
        $token = Str::random(64);
        $ttlHours = (int) config('profile-requests.verification_ttl_hours', 72);

        $ok = $this->transitionTo(
            RequestStatus::AWAITING_VERIFICATION,
            RequestEventType::VERIFICATION_REQUESTED,
            [],
            // Nur die Zieladresse, nie der Token.
            ['sent_to' => $sendTo, 'ttl_hours' => $ttlHours],
            $actorType,
            $actorId,
            function (self $request) use ($token, $ttlHours): void {
                $request->verification_method = VerificationMethod::MAGIC_LINK;
                $request->verification_token_hash = self::hashToken($token);
                $request->verification_expires_at = now()->addHours($ttlHours);
            },
        );

        if (! $ok) {
            return null;
        }

        $this->plainVerificationToken = $token;

        return $token;
    }

    /**
     * Handpruefung anfordern (#6): die Anfrage wartet auf eine Bestaetigung,
     * aber es gibt keinen maschinellen Weg dorthin — kein Domain-Match, keine
     * hinterlegte Firmen-E-Mail, oder das Tageslimit der Magic-Links ist
     * erreicht. Enes entscheidet im Panel.
     *
     * Bewusst **ohne** Token und ohne Ablauf: ein Token, zu dem keine Mail
     * existiert, wuerde nur ablaufen, und ein Ablauf ohne Empfaenger wuerde
     * den Vorgang nach `verification_ttl_hours` still schliessen.
     */
    public function requireManualVerification(?string $reason = null, string $actorType = RequestEvent::ACTOR_SYSTEM, ?int $actorId = null): bool
    {
        return $this->transitionTo(
            RequestStatus::AWAITING_VERIFICATION,
            RequestEventType::VERIFICATION_REQUESTED,
            [],
            ['method' => VerificationMethod::MANUAL->value, 'reason' => $reason],
            $actorType,
            $actorId,
            function (self $request): void {
                $request->verification_method = VerificationMethod::MANUAL;
                $request->verification_token_hash = null;
                $request->verification_expires_at = null;
            },
        );
    }

    /**
     * Neuen Magic-Link-Token fuer einen Vorgang, der schon auf die
     * Bestaetigung wartet (#6: „Link abgelaufen, neu anfordern").
     *
     * Kein Statuswechsel — der Zustand ist bereits
     * `awaiting_verification` —, deshalb kein Uebergang des Automaten,
     * sondern nur eine Protokollzeile. Der alte Token ist mit dem
     * Ueberschreiben entwertet.
     *
     * Rueckgabe null, wenn der Vorgang gar nicht (mehr) auf eine Bestaetigung
     * wartet.
     */
    public function refreshVerificationToken(?string $sendTo = null): ?string
    {
        if ($this->status !== RequestStatus::AWAITING_VERIFICATION) {
            return null;
        }

        $token = Str::random(64);
        $ttlHours = (int) config('profile-requests.verification_ttl_hours', 72);

        $this->verification_method = VerificationMethod::MAGIC_LINK;
        $this->verification_token_hash = self::hashToken($token);
        $this->verification_expires_at = now()->addHours($ttlHours);
        $this->save();

        $this->recordEvent(RequestEventType::VERIFICATION_REQUESTED, [
            'sent_to' => $sendTo,
            'ttl_hours' => $ttlHours,
            'erneut' => true,
        ]);

        $this->plainVerificationToken = $token;

        return $token;
    }

    /**
     * Anfrage gilt als vom Betrieb autorisiert. Der Token wird dabei
     * entwertet — ein Magic-Link ist einmal gueltig.
     */
    public function verify(
        VerificationMethod $method = VerificationMethod::MAGIC_LINK,
        ?string $verifiedEmail = null,
        string $actorType = RequestEvent::ACTOR_REQUESTER,
        ?int $actorId = null,
    ): bool {
        return $this->transitionTo(
            RequestStatus::VERIFIED,
            RequestEventType::VERIFIED,
            [],
            ['method' => $method->value, 'verified_email' => $verifiedEmail],
            $actorType,
            $actorId,
            function (self $request) use ($method, $verifiedEmail): void {
                $request->verification_method = $method;
                $request->verified_email = $verifiedEmail ?? $request->verified_email ?? $request->requester_email;
                $request->verified_at = now();
                $request->verification_token_hash = null;
                $request->verification_expires_at = null;
            },
        );
    }

    /**
     * Anfrage ist erledigt — die Aenderung steht im Profil, die Loeschung ist
     * gelaufen, die Uebernahme ist freigegeben.
     *
     * `$appliedBy = null` heisst: eine Auto-Regel (#9) hat es getan.
     *
     * @param  array<string, mixed>  $payload
     */
    public function apply(?User $appliedBy = null, array $payload = []): bool
    {
        return $this->transitionTo(
            RequestStatus::APPLIED,
            RequestEventType::APPLIED,
            [],
            $payload + ['automatic' => $appliedBy === null],
            $appliedBy === null ? RequestEvent::ACTOR_SYSTEM : RequestEvent::ACTOR_USER,
            $appliedBy?->getKey() === null ? null : (int) $appliedBy->getKey(),
            function (self $request) use ($appliedBy): void {
                $request->applied_at = now();
                $request->applied_by = $appliedBy?->getKey();
            },
        );
    }

    public function reject(string $reason, ?User $rejectedBy = null): bool
    {
        return $this->transitionTo(
            RequestStatus::REJECTED,
            RequestEventType::REJECTED,
            [],
            ['reason' => $reason],
            $rejectedBy === null ? RequestEvent::ACTOR_SYSTEM : RequestEvent::ACTOR_USER,
            $rejectedBy?->getKey() === null ? null : (int) $rejectedBy->getKey(),
            function (self $request) use ($reason): void {
                $request->rejected_reason = $reason;
            },
        );
    }

    /** Magic-Link ist abgelaufen (Scheduler, #9). */
    public function markExpired(): bool
    {
        return $this->transitionTo(
            RequestStatus::EXPIRED,
            RequestEventType::EXPIRED,
            [],
            ['expired_at' => now()->toIso8601String()],
            RequestEvent::ACTOR_SYSTEM,
            null,
            function (self $request): void {
                $request->verification_token_hash = null;
            },
        );
    }

    /** Storniert — der Stornolink in der Karenzfrist (#10). */
    public function cancel(?string $reason = null, string $actorType = RequestEvent::ACTOR_REQUESTER, ?int $actorId = null): bool
    {
        return $this->transitionTo(
            RequestStatus::CANCELLED,
            RequestEventType::CANCELLED,
            [],
            ['reason' => $reason],
            $actorType,
            $actorId,
            function (self $request) use ($reason): void {
                $request->rejected_reason = $reason ?? $request->rejected_reason;
                $request->verification_token_hash = null;
            },
        );
    }

    /**
     * Der eine Weg, auf dem `status` sich aendert.
     *
     * Status und Protokollzeile entstehen in **einer** Transaktion: ohne sie
     * koennte ein Abbruch nach dem Speichern einen Vorgang ohne Protokoll
     * hinterlassen, und genau das soll der Observer unmoeglich machen.
     *
     * @param  array<string, mixed>  $attributes  mit fill() uebernommene Felder
     * @param  array<string, mixed>  $payload  Nutzlast der Protokollzeile
     * @param  (callable(self): void)|null  $mutate  Felder, die nicht fillable sind
     */
    protected function transitionTo(
        RequestStatus $target,
        RequestEventType $event,
        array $attributes,
        array $payload,
        string $actorType,
        ?int $actorId,
        ?callable $mutate = null,
    ): bool {
        $from = $this->status;

        if (! $from instanceof RequestStatus) {
            $from = RequestStatus::from((string) $from);
        }

        if ($from === $target) {
            // Schon dort — kein zweites Ereignis fuer denselben Zustand.
            return false;
        }

        if (! $from->canTransitionTo($target)) {
            throw RequestTransitionException::notAllowed($from, $target);
        }

        if ($attributes !== []) {
            $this->fill($attributes);
        }

        if ($mutate !== null) {
            $mutate($this);
        }

        $this->status = $target;

        return (bool) DB::connection($this->getConnectionName())->transaction(function () use ($event, $payload, $actorType, $actorId, $from, $target) {
            $this->inTransition = true;

            try {
                $this->save();
            } finally {
                $this->inTransition = false;
            }

            $this->recordEvent($event, $payload, $actorType, $actorId, $from, $target);

            return true;
        });
    }

    /**
     * Protokollzeile schreiben. Auch fuer Ereignisse ohne Statuswechsel
     * (KI-Lauf, verworfener Anhang, versendete Mail) — dann ohne
     * from/to_status.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(
        RequestEventType $event,
        array $payload = [],
        string $actorType = RequestEvent::ACTOR_SYSTEM,
        ?int $actorId = null,
        ?RequestStatus $from = null,
        ?RequestStatus $to = null,
    ): RequestEvent {
        return $this->events()->create([
            'event' => $event,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'from_status' => $from,
            'to_status' => $to,
            'payload' => $payload === [] ? null : $payload,
            'created_at' => now(),
        ]);
    }

    /** Nur fuer den Observer: laeuft gerade ein Uebergang des Automaten? */
    public function isInTransition(): bool
    {
        return $this->inTransition;
    }

    // ------------------------------------------------------------------
    // Verifikationstoken
    // ------------------------------------------------------------------

    /**
     * SHA-256, kein bcrypt: der Token ist 64 Zeichen Zufall, hat also keine
     * Entropie zu verstaerken, und der Magic-Link wird je Aufruf nachgesehen
     * — ein langsamer Hash waere hier nur langsam. Die Tabelle traegt
     * deshalb einen Index auf die Spalte.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Anfrage zu einem Magic-Link. Nur solange sie auf die Bestaetigung
     * wartet und der Link nicht abgelaufen ist (#6).
     */
    public static function findByVerificationToken(string $token): ?self
    {
        return self::query()
            ->awaitingVerification()
            ->where('verification_token_hash', self::hashToken($token))
            ->where(function (Builder $query) {
                $query->whereNull('verification_expires_at')
                    ->orWhere('verification_expires_at', '>', now());
            })
            ->first();
    }

    public function hasExpiredVerification(): bool
    {
        return $this->status === RequestStatus::AWAITING_VERIFICATION
            && $this->verification_expires_at !== null
            && $this->verification_expires_at->isPast();
    }

    // ------------------------------------------------------------------
    // Abgeleitetes
    // ------------------------------------------------------------------

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Ende der Karenzfrist fuer eine verifizierte Loeschung setzen (#8/#9).
     * Kein Statuswechsel, deshalb kein Ereignis des Automaten.
     */
    public function startGracePeriod(?int $hours = null): bool
    {
        $this->grace_until = now()->addHours($hours ?? (int) config('profile-requests.deletion_grace_hours', 48));

        return $this->save();
    }

    public function isGracePeriodOver(): bool
    {
        return $this->grace_until === null || $this->grace_until->isPast();
    }

    /**
     * Enthaelt der Diff nur Felder der Whitelist? Erst damit darf eine
     * verifizierte Aenderung ohne Enes laufen (#9).
     */
    public function hasOnlyAutoApplicableChanges(): bool
    {
        $whitelist = (array) config('profile-requests.auto_apply_fields', []);
        $fields = $this->diffFields();

        return $fields !== [] && array_diff($fields, $whitelist) === [];
    }
}
