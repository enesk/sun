<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Enums\TopicStatus;
use App\Guide\Events\RunRequested;
use App\Guide\Models\Central\GuideRunState;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use Illuminate\Support\Carbon;

/**
 * Legt einen Lauf fuer ein einzelnes Thema ausserhalb des Tageslaufs an
 * (`guide:run --topic=`, Dashboard "Jetzt ausfuehren", #15).
 *
 * Laeuft im Tenant-Kontext. Es gilt dieselbe Regel wie im Tageslauf:
 * hoechstens ein Lauf je Thema und Tag (Unique-Index auf guide_topic_id,
 * run_date). Steht heute schon ein Lauf in der Kette, wird er nicht
 * verdoppelt; ist er heute schon beendet, gibt es erst morgen einen neuen.
 */
class TopicRunStarter
{
    public const STARTED = 'started';

    public const ALREADY_RUNNING = 'running';

    public const ALREADY_RAN_TODAY = 'done_today';

    public const NOT_RUNNABLE = 'not_runnable';

    /**
     * @return array{outcome: string, run_id: int|null, reason: string|null}
     */
    public function start(Topic $topic, int|string $tenantId): array
    {
        // Globale Pause (#33): ab sofort startet kein neuer Lauf.
        if (GuideRunState::isPaused()) {
            return [
                'outcome' => self::NOT_RUNNABLE,
                'run_id' => null,
                'reason' => __('Der Tageslauf ist global pausiert. Fortsetzen unter Einstellungen › Tageslauf.'),
            ];
        }

        if (! $topic->status->isDispatchable()) {
            return [
                'outcome' => self::NOT_RUNNABLE,
                'run_id' => null,
                'reason' => $topic->status === TopicStatus::OUTLINE_PENDING
                    ? __('Die Gliederung ist noch nicht bestätigt.')
                    : __('Das Thema ist :status.', ['status' => mb_strtolower($topic->status->label())]),
            ];
        }

        $today = Carbon::now(config('guide.timezone'))->toDateString();

        /** @var TopicRun|null $existing */
        $existing = $topic->runs()->whereDate('run_date', $today)->first();

        if ($existing !== null) {
            return [
                'outcome' => $existing->status->isTerminal() ? self::ALREADY_RAN_TODAY : self::ALREADY_RUNNING,
                'run_id' => (int) $existing->getKey(),
                'reason' => $existing->status->isTerminal()
                    ? __('Heute gab es bereits einen Lauf; der nächste ist morgen möglich.')
                    : __('Der Lauf von heute ist bereits unterwegs.'),
            ];
        }

        /** @var TopicRun $run */
        $run = $topic->runs()->create([
            'run_date' => $today,
            'status' => RunStatus::QUEUED,
            'mode' => $topic->article_id !== null ? RunMode::UPDATE : RunMode::CREATE,
        ]);

        RunRequested::dispatch($tenantId, (int) $topic->getKey(), (int) $run->getKey());

        return ['outcome' => self::STARTED, 'run_id' => (int) $run->getKey(), 'reason' => null];
    }
}
