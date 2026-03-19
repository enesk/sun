<?php

declare(strict_types=1);

namespace App\DTOs\TenantImport;

readonly class TempDatabaseInfo
{
    public function __construct(
        public string $databaseName,
        public string $connectionName,
        public string $uploadPath,
        public int $createdAt,
    ) {}
}
