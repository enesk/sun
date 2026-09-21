<?php

declare(strict_types=1);

namespace App\Guide\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Gespeicherter Tagesbericht des Ratgebersystems (#13), eine Zeile je Tag.
 * Aufbau von report_json: App\Guide\Orchestration\DailyReportBuilder.
 *
 * @property \Illuminate\Support\Carbon $report_date
 * @property array<string, mixed> $report_json
 * @property \Illuminate\Support\Carbon|null $sent_at
 */
class GuideDailyReport extends Model
{
    use CentralConnection;

    protected $table = 'guide_daily_reports';

    protected $fillable = [
        'report_date',
        'report_json',
        'checked',
        'failed',
        'cost_usd',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'report_json' => 'array',
            'checked' => 'integer',
            'failed' => 'integer',
            'cost_usd' => 'float',
            'sent_at' => 'datetime',
        ];
    }
}
