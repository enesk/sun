<?php

declare(strict_types=1);

namespace App\Listeners\Company;

use App\Events\Company\CompanyActivated;
use App\Mail\Company\CompanyActivatedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Schickt dem Inhaber die Freischalt-Mail. Laeuft ueber die Queue, der Tenant
 * reist per QueueTenancyBootstrapper mit. Ein Mailfehler darf die
 * Freischaltung nie zurueckrollen, deshalb nur eine Logzeile.
 */
class SendCompanyActivatedMail implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(CompanyActivated $event): void
    {
        /** @var \App\Models\User|null $owner */
        $owner = $event->company->owner;

        if ($owner === null || ! filter_var((string) $owner->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($owner->email)->send(new CompanyActivatedMail($event->company, $event->tenant));
        } catch (Throwable $e) {
            Log::warning('CompanyActivated: Freischalt-Mail nicht versendet', [
                'company_id' => $event->company->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
