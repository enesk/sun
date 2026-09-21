<?php

declare(strict_types=1);

namespace App\Guide\Models\Central;

use App\Guide\Mail\GuideAlertRaised;
use App\Guide\Support\GuideOwners;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Throwable;

/**
 * Alarm des Ratgebersystems (#13). Derselbe `dedupe_key` zaehlt
 * `occurrences` hoch, statt eine neue Zeile anzulegen.
 *
 * `guide_topic_id` und `guide_topic_run_id` verweisen in die Tenant-DB des
 * Alarms und haben deshalb keine Relation.
 *
 * @property \Illuminate\Support\Carbon|null $for_date
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property array<string, mixed>|null $context_json
 */
class GuideAlert extends Model
{
    use CentralConnection;

    public const LEVEL_CRITICAL = 'critical';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_INFO = 'info';

    // Tages-, Tenant- oder Laufbudget erreicht (#5).
    public const KEY_BUDGET_EXCEEDED = 'budget_exceeded';

    // Budget zu warn_threshold verbraucht (#5).
    public const KEY_BUDGET_WARNING = 'budget_warning';

    // Importiertes Thema deckt einen Altartikel ab, Entscheidung im Dashboard (#19).
    public const KEY_LEGACY_OVERLAP = 'legacy_overlap';

    // Thema nach guide.schedule.max_consecutive_failures Fehlschlaegen pausiert (#9).
    public const KEY_TOPIC_PAUSED = 'topic_paused';

    // Faellige Themen wegen Tagesbudget oder Neuanlage-Grenze auf morgen verschoben (#9);
    // context_json.topics listet sie fuer den Tagesbericht (#13).
    public const KEY_TOPICS_DEFERRED = 'topics_deferred';

    // Gliederungsvorschlag fuer ein Thema gescheitert (#10, ProposeOutlineJob).
    public const KEY_OUTLINE_FAILED = 'outline_failed';

    // Modell-Provider ausgefallen (toter Zugang, 5xx, Verbindungsfehler); Laeufe werden zurueckgestellt (#13).
    public const KEY_PROVIDER_DOWN = 'provider_down';

    // Lauf haengt laenger als guide.schedule.stuck_after_minutes in einem Zwischenstatus;
    // occurrences = Zahl der Neuansaetze durch den Watchdog (#13).
    public const KEY_RUN_STUCK = 'run_stuck';

    // Mehr als guide.orchestrator.failure_alert_ratio der Laeufe eines Tenants an einem Tag fehlgeschlagen (#13).
    public const KEY_FAILURE_RATE = 'failure_rate';

    // Einzelner Lauf fehlgeschlagen (#8 ff.).
    public const KEY_RUN_FAILED = 'run_failed';

    /**
     * Kritische Alarme mit Sofort-Mail an die Inhaber (#38 G1). run_stuck ist
     * erst kritisch, wenn der Watchdog die Neustarts ausgeschoepft hat.
     *
     * @var list<string>
     */
    public const MAIL_KEYS = [
        self::KEY_PROVIDER_DOWN,
        self::KEY_BUDGET_EXCEEDED,
        self::KEY_FAILURE_RATE,
        self::KEY_RUN_STUCK,
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected $table = 'guide_alerts';

    protected $fillable = [
        'dedupe_key',
        'tenant_id',
        'guide_topic_id',
        'guide_topic_run_id',
        'key',
        'level',
        'message',
        'context_json',
        'for_date',
        'status',
        'occurrences',
        'last_seen_at',
        'notified_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context_json' => 'array',
            'for_date' => 'date',
            'occurrences' => 'integer',
            'last_seen_at' => 'datetime',
            'notified_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Legt den Alarm an oder zaehlt einen offenen mit demselben dedupe_key
     * hoch; ein erledigter wird wieder geoeffnet.
     *
     * @param  array<string, mixed>  $attributes  tenant_id, guide_topic_id, guide_topic_run_id, context_json, for_date
     */
    public static function raise(string $dedupeKey, string $key, string $level, string $message, array $attributes = []): self
    {
        $alert = static::query()->firstOrNew(['dedupe_key' => $dedupeKey]);

        if (! $alert->exists) {
            $alert->fill([...$attributes, 'key' => $key, 'level' => $level, 'message' => $message, 'occurrences' => 1]);
        } else {
            $alert->occurrences = $alert->status === self::STATUS_OPEN ? $alert->occurrences + 1 : 1;
            $alert->message = $message;
        }

        $alert->status = self::STATUS_OPEN;
        $alert->resolved_at = null;
        $alert->last_seen_at = now();
        $alert->save();
        $alert->notifyOwners();

        return $alert;
    }

    /**
     * Sofort-Mail fuer kritische Alarme aus MAIL_KEYS (#38 G1), hoechstens
     * eine je Alarmcode, Portal (bzw. global) und Tag (for_date): auch ein
     * am selben Tag wieder geoeffneter Alarm oder ein zweiter Alarm mit
     * anderem dedupe_key (z. B. anderer Lauf) loest keine zweite Mail aus.
     *
     * Ein Fehler beim Versand haelt den Lauf nicht an; der Alarm bleibt
     * auf Heute und im Tagesbericht sichtbar.
     */
    public function notifyOwners(): bool
    {
        if ($this->level !== self::LEVEL_CRITICAL || ! in_array($this->key, self::MAIL_KEYS, true)) {
            return false;
        }

        $day = $this->for_date?->toDateString()
            ?? Carbon::now((string) config('guide.timezone', 'Europe/Berlin'))->toDateString();

        $alreadySent = static::query()
            ->where('key', $this->key)
            ->when(
                $this->tenant_id !== null,
                fn (Builder $query) => $query->where('tenant_id', $this->tenant_id),
                fn (Builder $query) => $query->whereNull('tenant_id'),
            )
            ->whereNotNull('notified_at')
            ->where(fn (Builder $query) => $query
                ->whereDate('for_date', $day)
                ->orWhere(fn (Builder $query) => $query->whereNull('for_date')->whereDate('notified_at', $day)))
            ->exists();

        if ($alreadySent) {
            return false;
        }

        // Atomar belegen, damit zwei Worker denselben Alarm nicht doppelt melden.
        $claimed = static::query()->whereKey($this->getKey())->whereNull('notified_at')->update(['notified_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        $recipients = GuideOwners::emails();

        if ($recipients === []) {
            Log::warning('Guide-Alarm ohne Empfaenger: kein aktiver Inhaber mit Mailadresse.', ['alert_id' => $this->getKey()]);

            return false;
        }

        /** @var Tenant|null $tenant */
        $tenant = $this->tenant_id !== null ? $this->tenant : null;

        try {
            Mail::to($recipients)->send(new GuideAlertRaised($this, $tenant !== null ? (string) ($tenant->domain ?: $tenant->name) : null));
        } catch (Throwable $exception) {
            static::query()->whereKey($this->getKey())->update(['notified_at' => null]);

            Log::warning('Alarm-Mail des Ratgebersystems nicht zugestellt.', [
                'alert_id' => $this->getKey(),
                'dedupe_key' => $this->dedupe_key,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }

        $this->notified_at = now();

        return true;
    }
}
