<?php

namespace App\Console\Commands;

use App\Mail\Job\JobExpired;
use App\Models\Portal\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ExpireJobs extends Command implements Isolatable
{
    protected $signature = 'app:expire-jobs
        {--tenant= : Nur fuer einen bestimmten Tenant ausfuehren}
        {--dry-run : Nur anzeigen was passieren wuerde, nichts aendern}';

    protected $description = 'Deaktiviert abgelaufene Stellenanzeigen und benachrichtigt die Firmeninhaber per E-Mail';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Keine Tenants gefunden.');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — keine Aenderungen werden vorgenommen.');
        }

        $totalExpired = 0;
        $totalNotified = 0;

        foreach ($tenants as $tenant) {
            [$expired, $notified] = $this->expireForTenant($tenant, $dryRun);
            $totalExpired += $expired;
            $totalNotified += $notified;
        }

        $this->newLine();
        $this->info("Fertig: {$totalExpired} Jobs abgelaufen, {$totalNotified} E-Mails gesendet.");

        return self::SUCCESS;
    }

    private function expireForTenant(Tenant $tenant, bool $dryRun): array
    {
        $expired = 0;
        $notified = 0;

        $tenant->run(function () use ($tenant, $dryRun, &$expired, &$notified) {
            // Finde alle Jobs die:
            // 1. noch als aktiv markiert sind (is_active = true)
            // 2. aber deren expires_at in der Vergangenheit liegt
            $expiredJobs = Job::on('tenant')
                ->where('is_active', true)
                ->where('expires_at', '<=', now())
                ->with(['company'])
                ->get();

            if ($expiredJobs->isEmpty()) {
                return;
            }

            $this->info("  {$tenant->name}: {$expiredJobs->count()} abgelaufene Jobs gefunden");

            foreach ($expiredJobs as $job) {
                $this->line("    - \"{$job->title}\" (Firma: {$job->company->name}, abgelaufen: {$job->expires_at->format('d.m.Y')})");

                if (! $dryRun) {
                    // Job deaktivieren (is_active = false)
                    $job->update(['is_active' => false]);
                    $expired++;

                    // E-Mail an Firmeninhaber senden
                    $notified += $this->notifyOwner($job, $tenant);
                } else {
                    $expired++;
                }
            }
        });

        return [$expired, $notified];
    }

    private function notifyOwner(Job $job, Tenant $tenant): int
    {
        $company = $job->company;

        if (! $company || ! $company->user_id) {
            return 0;
        }

        // User liegt in der zentralen DB
        $user = User::find($company->user_id);

        if (! $user || ! $user->email) {
            return 0;
        }

        try {
            Mail::to($user->email)->send(
                new JobExpired($job, $company, $tenant)
            );

            $this->line("      → E-Mail gesendet an {$user->email}");
            return 1;
        } catch (\Exception $e) {
            $this->error("      → E-Mail-Fehler: {$e->getMessage()}");
            return 0;
        }
    }
}
