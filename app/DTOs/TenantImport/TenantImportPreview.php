<?php

declare(strict_types=1);

namespace App\DTOs\TenantImport;

readonly class TenantImportPreview
{
    public function __construct(
        public int $placesCount,
        public int $categoriesCount,
        public int $reviewsCount,
        public int $photosCount,
        public int $openingHoursCount,
        public int $duplicatesCount,
        public int $expectedNewCompanies,
        public array $missingCategories = [],
        public array $availableTenantIds = [],
    ) {}
}
