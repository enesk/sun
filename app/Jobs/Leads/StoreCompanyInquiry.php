<?php

declare(strict_types=1);

namespace App\Jobs\Leads;

use App\Services\Leads\CompanyInquiryRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Legt eine Anfrage aus dem Webhook 'lead.created' ab (#31 -> #32).
 *
 * Wird im Tenant-Kontext dispatcht; der QueueTenancyBootstrapper stellt ihn
 * im Worker wieder her. Der Umschlag ist bereits signaturgeprueft.
 */
class StoreCompanyInquiry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    /**
     * @param  array<string, mixed>  $envelope
     */
    public function __construct(public readonly array $envelope) {}

    public function handle(CompanyInquiryRecorder $recorder): void
    {
        $recorder->record($this->envelope);
    }
}
