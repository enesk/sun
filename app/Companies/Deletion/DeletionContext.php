<?php

declare(strict_types=1);

namespace App\Companies\Deletion;

use App\Models\Portal\Company;
use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;

/**
 * Alles, was ein Schritt der Loeschpipeline (#8) braucht — und ein Notizbuch,
 * ueber das die Schritte sich untereinander verstaendigen (etwa die Pfade,
 * die `RegisterGoneUrl` eintraegt und `PingIndexNow` meldet).
 *
 * Eine Instanz je Lauf. Lebt nur im Tenant-Kontext des Portals.
 */
class DeletionContext
{
    /** @var array<string, mixed> */
    private array $notes = [];

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Company $company,
        public readonly string $runId,
        public readonly bool $dryRun = false,
        public readonly ?ProfileRequest $request = null,
        public readonly ?string $reason = null,
    ) {}

    /**
     * Die Pfade des Profils, **einmal** am Anfang des Laufs ermittelt. Der
     * Soft Delete haengt ein Suffix an den Slug; wer den Pfad spaeter
     * berechnet, bekommt den falschen.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        /** @var list<string> $paths */
        $paths = $this->note('paths', []);

        return $paths;
    }

    public function primaryPath(): ?string
    {
        return $this->paths()[0] ?? null;
    }

    public function note(string $key, mixed $default = null): mixed
    {
        return $this->notes[$key] ?? $default;
    }

    public function remember(string $key, mixed $value): void
    {
        $this->notes[$key] = $value;
    }

    public function requestId(): ?int
    {
        $id = $this->request?->getKey();

        return $id === null ? null : (int) $id;
    }
}
