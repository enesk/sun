<?php

declare(strict_types=1);

namespace App\DTOs\TenantImport;

class TenantImportResult
{
    public int $companiesImported = 0;
    public int $companiesSkipped = 0;
    public int $companiesFailed = 0;
    public int $categoriesMapped = 0;
    public int $openingHoursImported = 0;
    public int $reviewsImported = 0;
    public int $photosImported = 0;
    public array $errors = [];

    public function toArray(): array
    {
        return [
            'companies_imported' => $this->companiesImported,
            'companies_skipped' => $this->companiesSkipped,
            'companies_failed' => $this->companiesFailed,
            'categories_mapped' => $this->categoriesMapped,
            'opening_hours_imported' => $this->openingHoursImported,
            'reviews_imported' => $this->reviewsImported,
            'photos_imported' => $this->photosImported,
            'errors' => $this->errors,
        ];
    }

    public function totalProcessed(): int
    {
        return $this->companiesImported + $this->companiesSkipped + $this->companiesFailed;
    }
}
