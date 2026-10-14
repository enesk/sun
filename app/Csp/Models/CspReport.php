<?php

declare(strict_types=1);

namespace App\Csp\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Ein entdoppelter CSP-Verstoss (#51), zentral gespeichert.
 *
 * Geschrieben wird ausschliesslich ueber
 * {@see \App\Csp\Support\CspReportCollector}; der Controller validiert und
 * normalisiert vorher ({@see \App\Csp\Support\CspReportPayload}).
 *
 * @property string $portal
 * @property int|null $tenant_id
 * @property string $fingerprint
 * @property string $violated_directive
 * @property string|null $effective_directive
 * @property string $blocked_uri
 * @property string $document_uri
 * @property string|null $disposition
 * @property string|null $source_file
 * @property int|null $line_number
 * @property int|null $column_number
 * @property string|null $user_agent
 * @property int $hits
 * @property \Illuminate\Support\Carbon|null $first_seen_at
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 */
class CspReport extends Model
{
    use CentralConnection;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'csp_reports';

    protected $fillable = [
        'portal',
        'tenant_id',
        'fingerprint',
        'violated_directive',
        'effective_directive',
        'blocked_uri',
        'document_uri',
        'disposition',
        'source_file',
        'line_number',
        'column_number',
        'user_agent',
        'hits',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'column_number' => 'integer',
            'hits' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Entdopplungsschluessel: Portal + Direktive + blockierte Quelle. Bewusst
     * ohne document_uri — ein kaputtes Werbe-Schnipsel steckt im Layout und
     * meldet von jeder Unterseite; sonst waere je Seite eine Zeile.
     */
    public static function fingerprint(string $portal, string $violatedDirective, string $blockedUri): string
    {
        return hash('sha256', implode('|', [
            mb_strtolower($portal),
            mb_strtolower($violatedDirective),
            mb_strtolower($blockedUri),
        ]));
    }

    /** Fuer die Auswertung: Zeitraum ab X Stunden rueckwaerts. */
    public function scopeSinceHours(Builder $query, int $hours): Builder
    {
        return $query->where('last_seen_at', '>=', now()->subHours($hours));
    }
}
