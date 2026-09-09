<?php

declare(strict_types=1);

namespace App\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Einzelner belegbarer Fakt (Zahl, Frist, Regel) mit Quelle und Stand (#15).
 *
 * Seit #11 sind Faktenschnipsel nicht nur Beleg eines Entwurfs, sondern auch
 * eigene Datenhaltung: Statistik-Connector und PortalDataProvider legen
 * Kennzahlen unter einem stabilen `fact_key` je Region ab. `valid_until`
 * begrenzt die Haltbarkeit — abgelaufene Schnipsel gehen nicht in Artikel.
 *
 * Damit sind Schnipsel gemeinsamer Bestand des Mandanten und kein
 * Verbrauchsmaterial eines Artikels: `article_draft_id` ist nur die Herkunft
 * ("bei dieser Generierung entstanden"), siehe draft() (#63).
 */
class FactSnippet extends Model
{
    use TenantConnection;

    /**
     * Beschriftungen der bekannten Kennzahlen. Der aus `fact_key` abgeleitete
     * Name waere sonst maschinell ("Portal Provider Count State De By") und
     * stuende so in der Key-Facts-Tabelle des Artikels (#14).
     *
     * @var array<string, string>
     */
    private const METRIC_LABELS = [
        'provider_count' => 'Gelistete Betriebe',
        'avg_rating' => 'Durchschnittsbewertung',
        'review_count' => 'Bewertungen',
    ];

    /**
     * Kennzahlen, die keine Tabellenzeile hergeben: ihr Zahlenwert ist die
     * Laenge einer Liste ("3 Betriebe", "5 Leistungen"), der Inhalt steckt im
     * Satz. In einer Faktentabelle waere das irrefuehrend.
     *
     * @var array<int, string>
     */
    private const NON_TABULAR_METRICS = ['top_rated', 'common_services'];

    protected $fillable = [
        'article_draft_id',
        'topic_candidate_id',
        'source_item_id',
        'fingerprint',
        'fact_key',
        'statement',
        'value',
        'unit',
        'period',
        'region_scope',
        'region_code',
        'source_name',
        'source_url',
        'retrieved_at',
        'valid_until',
        'confidence',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'retrieved_at' => 'datetime',
            'valid_until' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Der Entwurf, bei dessen Generierung dieser Schnipsel entstanden ist —
     * eine reine Zuordnung, kein Eigentum (#63).
     *
     * Ein Schnipsel gehoert nie einem Artikel: dieselbe Zahl belegt einen
     * bundesweiten und einen regionalen Ratgeber, und ein zweiter
     * Generierungsversuch braucht dieselben Fakten wie der erste. Der
     * Fremdschluessel ist deshalb `nullOnDelete`; welche Fakten ein Entwurf
     * tatsaechlich belegt, steht in `article_drafts.outline_json.fact_snippet_ids`
     * und in `draft_sources`, nicht hier.
     *
     * Wer Fakten sucht, filtert nicht ueber diese Beziehung, sondern ueber
     * `withKey()` + `forRegion()` + `stillValid()` (siehe ContextAssembler).
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(ArticleDraft::class, 'article_draft_id');
    }

    public function topicCandidate(): BelongsTo
    {
        return $this->belongsTo(TopicCandidate::class);
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(SourceItem::class);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    public function scopeUnverified(Builder $query): Builder
    {
        return $query->whereNull('verified_at');
    }

    /**
     * Schnipsel, die zum Stichtag noch gelten. Ohne `valid_until` gilt ein
     * Schnipsel unbegrenzt — das betrifft Rechtsstaende ohne Ablaufdatum.
     */
    public function scopeStillValid(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at = $at ? Carbon::instance($at) : Carbon::now();

        return $query->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $at));
    }

    public function scopeWithKey(Builder $query, string $factKey): Builder
    {
        return $query->where('fact_key', $factKey);
    }

    /**
     * Ein Fakt passt zur Region, wenn er exakt dort gilt oder bundesweit.
     */
    public function scopeForRegion(Builder $query, string $scope, ?string $code = null): Builder
    {
        return $query->where(function (Builder $q) use ($scope, $code): void {
            $q->where('region_scope', 'national');

            if ($scope !== 'national') {
                $q->orWhere(fn (Builder $inner) => $inner
                    ->where('region_scope', $scope)
                    ->when($code !== null, fn (Builder $c) => $c->where('region_code', $code)));
            }
        });
    }

    /**
     * Der Metrik-Teil des `fact_key`: `portal.provider_count.city.hamburg`
     * ergibt `provider_count`. Region und Bereich fallen weg, sie stehen im
     * Artikel schon im Text.
     */
    public function metric(): string
    {
        $parts = array_values(array_filter(explode('.', trim((string) $this->fact_key))));

        if ($parts === []) {
            return '';
        }

        // Endet der Schluessel auf Bereich und Regionscode, faellt beides weg.
        $scopeIndex = array_search((string) $this->region_scope, $parts, true);

        if ($scopeIndex !== false && $scopeIndex > 0) {
            $parts = array_slice($parts, 0, (int) $scopeIndex);
        }

        return (string) end($parts);
    }

    /**
     * Beschriftung fuer Key-Facts-Tabelle und Regionalblock (#14).
     */
    public function displayLabel(): string
    {
        $metric = $this->metric();

        if ($metric === '') {
            return Str::limit((string) $this->statement, 60, '');
        }

        return self::METRIC_LABELS[$metric] ?? Str::headline(str_replace('_', ' ', $metric));
    }

    /**
     * Taugt der Schnipsel als Zeile einer Faktentabelle? Das setzt einen
     * Zahlenwert voraus, der fuer sich steht.
     */
    public function isTabular(): bool
    {
        if (trim((string) $this->value) === '') {
            return false;
        }

        return ! in_array($this->metric(), self::NON_TABULAR_METRICS, true);
    }

    /**
     * Der Berichtszeitraum, wenn er fuer den Leser etwas aussagt. Ein
     * Monatsstand von Portalzahlen ist Betriebsinformation und gehoert nicht
     * in die Beschriftung — die Aktualitaetszeile des Artikels nennt ihn (#27).
     */
    public function displayPeriod(): ?string
    {
        $period = trim((string) $this->period);

        if ($period === '' || preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
            return null;
        }

        return $period;
    }

    public function isExpired(?\DateTimeInterface $at = null): bool
    {
        if ($this->valid_until === null) {
            return false;
        }

        return Carbon::parse($this->valid_until)->isBefore($at ? Carbon::instance($at) : Carbon::now());
    }
}
