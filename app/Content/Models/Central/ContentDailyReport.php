<?php

declare(strict_types=1);

namespace App\Content\Models\Central;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Ein gespeicherter Tagesbericht (#22).
 *
 * Bewusst central und bewusst nicht im Cache: der Bericht entsteht in einem
 * Lauf ueber alle Mandanten, und der Dateicache liegt dabei im
 * mandantenspezifischen storage_path.
 *
 * @property \Illuminate\Support\Carbon|null $report_date
 * @property array<string, mixed> $report_json
 * @property \Illuminate\Support\Carbon|null $sent_at
 */
class ContentDailyReport extends Model
{
    use CentralConnection;

    protected $table = 'content_daily_reports';

    protected $fillable = [
        'report_date',
        'report_json',
        'published',
        'target',
        'cost_usd',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'report_json' => 'array',
            'published' => 'integer',
            'target' => 'integer',
            'cost_usd' => 'float',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Der juengste Bericht, hoechstens `$maxAgeDays` alt.
     */
    public static function latest(int $maxAgeDays = 2): ?self
    {
        return self::query()
            ->where('report_date', '>=', CarbonImmutable::today()->subDays($maxAgeDays)->toDateString())
            ->orderByDesc('report_date')
            ->first();
    }
}
