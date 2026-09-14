<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\NewCompanyNotification;
use App\Models\Portal\Company;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Informiert alle aktiven Administratoren per Mail, wenn jemand auf einem
 * Portal selbst eine Firma eintraegt (/eintragen). Bewusst nicht am
 * Company-Model: Importe und die Verwaltung legen ebenfalls Firmen an.
 * Ein Mailfehler bricht das Eintragen nie ab.
 */
final class NewCompanyNotifier
{
    public function notify(Company $company, User $owner): void
    {
        $recipients = User::query()
            ->admin()
            ->where('is_blocked', false)
            ->pluck('email')
            ->filter()
            ->unique();

        if ($recipients->isEmpty()) {
            Log::warning('NewCompanyNotifier: kein aktiver Administrator als Empfaenger', ['company_id' => $company->id]);

            return;
        }

        $details = [
            'company' => (string) $company->name,
            'address' => $company->full_address ?: null,
            'tel' => $company->tel ?: null,
            'website' => $company->website ?: null,
            'status' => $company->is_active ? 'online' : 'wartet auf Freischaltung',
            'owner' => (string) $owner->name,
            'owner_email' => (string) $owner->email,
            'portal' => (string) (tenant()?->getAttribute('name') ?? config('app.name')),
            'edit_url' => route('verwaltung.companies.edit', $company->id),
            'created_at' => $company->created_at?->timezone('Europe/Berlin')->format('d.m.Y H:i'),
        ];

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new NewCompanyNotification($details));
            } catch (Throwable $e) {
                Log::warning('NewCompanyNotifier: Mail nicht versendet', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
