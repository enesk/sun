<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Steps;

use App\Companies\Deletion\DeletionContext;
use App\Companies\Deletion\DeletionStep;
use App\Companies\Deletion\StepResult;
use App\Models\Portal\CompanyInquiry;
use App\Models\Portal\CompanyLead;

/**
 * Anfragen und exklusive Leads anonymisieren (#8) — nicht loeschen.
 *
 * `contact_name`, `contact_email`, `contact_phone` und die Freitext-Antworten
 * raus, `contact_purged_at` rein. Datensatz, Status, Score, Eingangsdatum und
 * damit der Abrechnungsbezug zu den widileads-Kaeufern bleiben: fuer die
 * bereits abgerechneten Leads gilt eine handelsrechtliche
 * Aufbewahrungspflicht, die Art. 17 Abs. 3 lit. b abdeckt.
 *
 * Die endgueltige Loeschung laeuft nicht hier, sondern ueber die bestehende
 * Frist `leads.exclusive.retention_months` (Scheduler
 * `leads:purge-contacts` bzw. `leads:inquiries:purge-contacts`) — dieselbe
 * Felderliste, derselbe Marker.
 *
 * `answers` wird ganz geleert, nicht feldweise: die Antworten tragen keinen
 * Feldtyp und koennen Freitext enthalten.
 */
class AnonymizeLeads implements DeletionStep
{
    public function key(): string
    {
        return 'anonymize_leads';
    }

    public function label(): string
    {
        return 'Anfragen anonymisieren';
    }

    public function plan(DeletionContext $context): string
    {
        $companyId = $context->company->getKey();

        return sprintf(
            '%d Marktplatz-Anfrage(n) und %d exklusive(n) Lead(s) anonymisieren (Kontaktfelder und Freitexte leeren, Abrechnungsbezug behalten).',
            CompanyInquiry::withTrashed()->where('company_id', $companyId)->whereNull('contact_purged_at')->count(),
            CompanyLead::query()->where('company_id', $companyId)->whereNull('contact_purged_at')->count(),
        );
    }

    public function run(DeletionContext $context): StepResult
    {
        $companyId = $context->company->getKey();
        $now = now();

        $blank = [
            'contact_name' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'answers' => [],
            'contact_purged_at' => $now,
        ];

        $inquiries = 0;

        CompanyInquiry::withTrashed()
            ->where('company_id', $companyId)
            ->whereNull('contact_purged_at')
            ->chunkById(500, function ($rows) use ($blank, &$inquiries): void {
                foreach ($rows as $inquiry) {
                    $inquiry->forceFill($blank)->save();
                    $inquiries++;
                }
            });

        $leads = 0;

        CompanyLead::query()
            ->where('company_id', $companyId)
            ->whereNull('contact_purged_at')
            ->chunkById(500, function ($rows) use ($blank, &$leads): void {
                foreach ($rows as $lead) {
                    $lead->forceFill($blank)->save();
                    $leads++;
                }
            });

        return StepResult::ok(
            sprintf('%d Anfrage(n) und %d Lead(s) anonymisiert.', $inquiries, $leads),
            [
                'inquiries_anonymized' => $inquiries,
                'leads_anonymized' => $leads,
                'retention_months' => (int) config('leads.exclusive.retention_months', 12),
            ],
        );
    }
}
