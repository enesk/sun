<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Support\FieldRenderer;

/**
 * Aenderung uebernommen (SUN-RQ #10).
 *
 * Listet genau die Felder, die im Profil stehen — nicht die eingereichten.
 * Der Unterschied ist der Punkt dieser Mail: das Panel (#11) und die
 * Auto-Regel (#9) uebernehmen zeilenweise, es kann also sein, dass von fuenf
 * gewuenschten Angaben drei im Profil stehen und zwei noch bei Enes liegen.
 * Steht das nicht in der Mail, prueft der Betrieb sein Profil und schreibt
 * ein zweites Mal.
 */
class ChangesAppliedMail extends RequestMail
{
    /**
     * @param  list<string>  $appliedFields  Feldschluessel, die uebernommen wurden
     * @param  list<string>  $pendingFields  Feldschluessel, die noch offen sind
     */
    public function __construct(
        ProfileRequest $request,
        ?Tenant $tenant,
        public string $companyName,
        public array $appliedFields = [],
        public array $pendingFields = [],
        public ?string $profileUrl = null,
    ) {
        parent::__construct($request, $tenant);
    }

    protected function subjectLine(): string
    {
        return 'Ihre Änderung ist übernommen';
    }

    protected function viewName(): string
    {
        return 'emails.profile-requests.changes-applied';
    }

    protected function payload(): array
    {
        return [
            'companyName' => $this->companyName,
            'applied' => $this->labels($this->appliedFields),
            'pending' => $this->labels($this->pendingFields),
            'profileUrl' => $this->profileUrl,
        ];
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function labels(array $fields): array
    {
        return array_values(array_map(FieldRenderer::label(...), $fields));
    }
}
