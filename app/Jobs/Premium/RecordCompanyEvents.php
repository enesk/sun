<?php

namespace App\Jobs\Premium;

use App\Models\Portal\CompanyEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;

/**
 * Schreibt Statistik-Events (#15). Ein Job je Seitenaufruf, bei Listen mit
 * allen Betrieben der Seite. Der Tenant reist ueber den QueueTenancyBootstrapper mit.
 *
 * Deduplikation: Unique-Index auf Betrieb, Typ, Sitzung und Stunde, Dubletten
 * verwirft insertOrIgnore.
 */
class RecordCompanyEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const CHUNK = 500;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @param  list<int>  $companyIds
     */
    public function __construct(
        public array $companyIds,
        public string $eventType,
        public string $occurredAt,
        public string $sessionHash,
        public ?int $cityId = null,
        public ?string $source = null,
    ) {}

    public function handle(): void
    {
        $occurredAt = Carbon::parse($this->occurredAt)->setTimezone(config('app.timezone'));
        $occurredHour = $occurredAt->copy()->startOfHour();

        $rows = array_map(fn (int $companyId) => [
            'company_id' => $companyId,
            'event_type' => $this->eventType,
            'occurred_at' => $occurredAt,
            'occurred_hour' => $occurredHour,
            'session_hash' => $this->sessionHash,
            'city_id' => $this->cityId,
            'source' => $this->source,
        ], $this->companyIds);

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            CompanyEvent::query()->insertOrIgnore($chunk);
        }
    }
}
