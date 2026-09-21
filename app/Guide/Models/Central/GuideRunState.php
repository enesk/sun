<?php

declare(strict_types=1);

namespace App\Guide\Models\Central;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Globaler Schalter "Tageslauf aktiv" (#33, design/guide-dashboard.md §9.1),
 * genau eine Zeile. Pausiert: DailyOrchestrator reiht nichts ein, der
 * Watchdog steht still, TopicRunStarter startet nichts, und jedes
 * Kettenglied beendet seinen Lauf, statt weiterzuarbeiten
 * (HandlesGuideRun::stopWhenPaused()).
 *
 * @property \Illuminate\Support\Carbon|null $paused_at
 * @property int|null $paused_by
 * @property string|null $paused_by_name
 */
class GuideRunState extends Model
{
    use CentralConnection;

    protected $table = 'guide_run_state';

    protected $fillable = [
        'paused_at',
        'paused_by',
        'paused_by_name',
    ];

    protected function casts(): array
    {
        return [
            'paused_at' => 'datetime',
            'paused_by' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * Ohne Tabelle (Migration noch nicht gelaufen) gilt der Tageslauf als aktiv.
     */
    public static function isPaused(): bool
    {
        try {
            return static::query()->whereNotNull('paused_at')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function pause(?User $user): void
    {
        $this->forceFill([
            'paused_at' => Carbon::now(),
            'paused_by' => $user?->getKey(),
            'paused_by_name' => $user?->name,
        ])->save();
    }

    public function resume(): void
    {
        $this->forceFill(['paused_at' => null, 'paused_by' => null, 'paused_by_name' => null])->save();
    }
}
