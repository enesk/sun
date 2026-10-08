<?php

declare(strict_types=1);

namespace App\Turnstile\Models;

use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\VerificationOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Eine Zeile je Pruefversuch (#3), geschrieben von #4, ausgewertet in #9/#12.
 * Liegt in der Tenant-DB.
 *
 * Es wird nie das Token gespeichert, nie die IP und nie die E-Mail im Klartext
 * — nur HMAC-SHA256 mit dem App-Key ({@see self::hash()}). Der Hash reicht fuer
 * Rate-Limit-Korrelation (#8) und Auswertung, laesst sich aber nicht in die
 * Ausgangsdaten zuruecklesen.
 *
 * @property TurnstileAction $action
 * @property VerificationOutcome $outcome
 * @property list<string>|null $error_codes_json
 * @property string|null $hostname_reported
 * @property string|null $hostname_expected
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property string|null $email_hash
 * @property int|null $duration_ms
 */
class TurnstileVerification extends Model
{
    use TenantConnection;

    public const UPDATED_AT = null;

    protected $table = 'turnstile_verifications';

    protected $fillable = [
        'action',
        'outcome',
        'error_codes_json',
        'hostname_reported',
        'hostname_expected',
        'ip_hash',
        'user_agent',
        'email_hash',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'action' => TurnstileAction::class,
            'outcome' => VerificationOutcome::class,
            'error_codes_json' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * HMAC-SHA256 mit dem App-Key als Schluessel, 64 Hexzeichen. Leere Eingabe
     * ergibt null, damit kein Hash des leeren Strings im Log landet.
     */
    public static function hash(?string $value, string $domain): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return hash_hmac('sha256', "turnstile|{$domain}|".mb_strtolower($value), (string) config('app.key'));
    }

    public static function hashIp(?string $ip): ?string
    {
        return self::hash($ip, 'ip');
    }

    public static function hashEmail(?string $email): ?string
    {
        return self::hash($email, 'email');
    }

    /** User-Agent auf Spaltenbreite kuerzen; die Spalte ist hart 255 Zeichen. */
    public static function trimUserAgent(?string $userAgent): ?string
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return null;
        }

        return mb_substr($userAgent, 0, 255);
    }

    public function scopeAction(Builder $query, TurnstileAction $action): Builder
    {
        return $query->where('action', $action->value);
    }

    public function scopeOutcome(Builder $query, VerificationOutcome $outcome): Builder
    {
        return $query->where('outcome', $outcome->value);
    }

    /** Fuer die Kennzahlen (#12): Zeitraum ab X Tagen rueckwaerts. */
    public function scopeSince(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
