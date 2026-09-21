<?php

declare(strict_types=1);

namespace App\Guide\Models;

use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Ein Lauf eines Themas durch die Job-Kette (docs/guide-system.md, §2).
 * Hoechstens ein Lauf je Thema und Tag (Unique-Index auf guide_topic_id, run_date).
 *
 * @property RunStatus $status
 * @property RunMode $mode
 * @property \Illuminate\Support\Carbon $run_date
 * @property array<int, string>|null $changed_section_ids_json
 * @property array<string, mixed>|null $probe_json
 * @property array<string, mixed>|null $research_json
 * @property array<string, mixed>|null $publish_json
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property-read Topic|null $topic
 */
class TopicRun extends Model
{
    use TenantConnection;

    protected $table = 'guide_topic_runs';

    protected $fillable = [
        'guide_topic_id',
        'run_date',
        'status',
        'mode',
        'probe_json',
        'research_json',
        'changed_section_ids_json',
        'change_summary',
        'quality_score',
        'quality_report_json',
        'publish_json',
        'cost_usd',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $attributes = [
        'status' => 'queued',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'status' => RunStatus::class,
            'mode' => RunMode::class,
            'probe_json' => 'array',
            'research_json' => 'array',
            'changed_section_ids_json' => 'array',
            'quality_score' => 'integer',
            'quality_report_json' => 'array',
            'publish_json' => 'array',
            'cost_usd' => 'decimal:6',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'guide_topic_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class, 'guide_topic_run_id');
    }

    public function scopeForDate(Builder $query, DateTimeInterface $date): Builder
    {
        return $query->whereDate('run_date', Carbon::instance($date)->toDateString());
    }

    public function scopeInFlight(Builder $query): Builder
    {
        $statuses = array_map(
            static fn (RunStatus $status): string => $status->value,
            array_values(array_filter(RunStatus::cases(), static fn (RunStatus $status): bool => $status->isInFlight())),
        );

        return $query->whereIn('status', $statuses);
    }
}
