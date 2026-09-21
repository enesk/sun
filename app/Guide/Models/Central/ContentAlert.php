<?php

declare(strict_types=1);

namespace App\Guide\Models\Central;

use App\Guide\Enums\GuideRole;
use App\Guide\Mail\ContentAlertRaised;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Throwable;

/**
 * Ein offener Missstand der Content-Pipeline (#22).
 *
 * Alarme entstehen dort, wo die Kette sich nicht mehr selbst helfen kann:
 * ein Slot ohne Artikel nach erschoepften Reserve-Versuchen, eine
 * abgebrochene Tageskette, ein erschoepftes Budget. Sie stehen im
 * Stoerungsband der Uebersicht (#19) und im Tagesbericht um 20:00.
 *
 * Ein Alarm wiederholt sich nicht: derselbe `dedupe_key` zaehlt
 * `occurrences` hoch. Aufgeloest wird er, sobald die Ursache wegfaellt —
 * der Watchdog raeumt seine eigenen Slot-Alarme auf, sobald der Slot doch
 * noch gedeckt ist.
 *
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $for_date
 * @property array<string, mixed>|null $context_json
 */
class ContentAlert extends Model
{
    use CentralConnection;

    public const LEVEL_CRITICAL = 'critical';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_INFO = 'info';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    /** Ein Tages-Slot blieb auch nach allen Reserve-Versuchen unbelegt. */
    public const KEY_SLOT_EXHAUSTED = 'slot_exhausted';

    /** Eine Stufe der Tageskette ist mit einer Ausnahme abgebrochen. */
    public const KEY_CHAIN_FAILED = 'chain_failed';

    /**
     * Der Zugang zu einem kostenpflichtigen Provider ist tot: Guthaben
     * aufgebraucht oder Schluessel abgelehnt (#104). Netzwerkweit, also
     * ohne tenant_id — ein Band fuer alle Portale.
     */
    public const KEY_PROVIDER_ACCOUNT = 'provider_account';

    /** Fuer den Tag gibt es keine ausgewaehlten Themen. */
    public const KEY_NO_TOPICS = 'no_topics';

    protected $table = 'content_alerts';

    protected $fillable = [
        'dedupe_key',
        'tenant_id',
        'key',
        'level',
        'message',
        'context_json',
        'for_date',
        'slot',
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
            'slot' => 'integer',
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

    /**
     * Alarm melden. Gibt es ihn schon, wird er hochgezaehlt und wieder
     * geoeffnet — es entsteht keine zweite Zeile.
     *
     * @param  array<string, mixed>  $context
     */
    public static function raise(
        string $key,
        string $message,
        ?int $tenantId = null,
        string $level = self::LEVEL_CRITICAL,
        array $context = [],
        ?CarbonImmutable $forDate = null,
        ?int $slot = null,
    ): self {
        $date = $forDate?->toDateString();
        $dedupe = implode(':', [$tenantId ?? 'all', $key, $date ?? '-', $slot ?? '-']);

        /** @var self $alert */
        $alert = self::query()->firstOrNew(['dedupe_key' => $dedupe]);

        // Ein Alarm, der sich zwischenzeitlich aufgeloest hatte, ist ein
        // neuer Vorfall und wird noch einmal gemeldet.
        $reopened = $alert->exists && $alert->status === self::STATUS_RESOLVED;

        $alert->fill([
            'tenant_id' => $tenantId,
            'key' => $key,
            'level' => $level,
            'message' => $message,
            'context_json' => $context,
            'for_date' => $date,
            'slot' => $slot,
            'status' => self::STATUS_OPEN,
            'last_seen_at' => now(),
        ]);

        if ($alert->exists) {
            $alert->occurrences = (int) $alert->occurrences + 1;
            $alert->resolved_at = null;
        }

        if ($reopened) {
            $alert->notified_at = null;
        }

        $alert->save();
        $alert->notifyOwners();

        return $alert;
    }

    /**
     * Schickt einen kritischen Alarm einmalig an die Owner (#22). Wiederholt
     * sich derselbe Alarm, zaehlt nur `occurrences` hoch — die Mail ging
     * bereits raus und steht abends noch einmal im Tagesbericht.
     *
     * Ein Fehler beim Versand darf die Pipeline nicht anhalten: er landet im
     * Log, der Alarm bleibt in der Uebersicht sichtbar.
     */
    public function notifyOwners(): bool
    {
        if ($this->level !== self::LEVEL_CRITICAL || $this->notified_at !== null) {
            return false;
        }

        if (! (bool) config('content.pipeline.alerts.mail', true)) {
            return false;
        }

        $recipients = self::ownerRecipients();

        if ($recipients === []) {
            return false;
        }

        /** @var Tenant|null $tenant */
        $tenant = $this->tenant;

        try {
            Mail::to($recipients)->send(new ContentAlertRaised($this, $tenant !== null ? (string) $tenant->name : null));
        } catch (Throwable $exception) {
            Log::warning('Alarm-Mail der Content-Pipeline konnte nicht zugestellt werden.', [
                'alert_id' => $this->getKey(),
                'dedupe_key' => $this->dedupe_key,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }

        $this->forceFill(['notified_at' => now()])->save();

        return true;
    }

    /**
     * Schliesst einen Alarm, wenn es ihn gibt. Rueckgabe: wurde etwas
     * geschlossen.
     */
    public static function settle(string $key, ?int $tenantId = null, ?CarbonImmutable $forDate = null, ?int $slot = null): bool
    {
        $dedupe = implode(':', [$tenantId ?? 'all', $key, $forDate?->toDateString() ?? '-', $slot ?? '-']);

        return self::query()
            ->where('dedupe_key', $dedupe)
            ->where('status', self::STATUS_OPEN)
            ->update([
                'status' => self::STATUS_RESOLVED,
                'resolved_at' => now(),
            ]) > 0;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Der Status-Token der Oberflaeche. Die Uebersicht faerbt ihre Baender
     * ueber die Status-Tokens aus resources/css/content/theme.css
     * (content-status--failed/-scheduled/-review), nicht ueber die Alarmstufe.
     */
    public function displayStatus(): string
    {
        return match ($this->level) {
            self::LEVEL_CRITICAL => 'failed',
            self::LEVEL_INFO => 'scheduled',
            default => 'review',
        };
    }

    /**
     * Empfaenger der Alarm- und Berichtsmails: die Inhaber des Content-Panels,
     * also Konten mit guide_role 'owner' und Administratoren ohne eigene Rolle
     * (App\Guide\Concerns\InteractsWithContentPanel::contentRole()).
     *
     * @return array<int, string>
     */
    public static function ownerRecipients(): array
    {
        return User::query()
            ->where('is_blocked', false)
            ->where(static function ($query): void {
                $query->where('guide_role', GuideRole::OWNER->value)
                    ->orWhere(static function ($query): void {
                        $query->where('is_admin', true)->whereNull('guide_role');
                    });
            })
            ->pluck('email')
            ->map(static fn ($email): string => (string) $email)
            ->filter()
            ->values()
            ->all();
    }
}
