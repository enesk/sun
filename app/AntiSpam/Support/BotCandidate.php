<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use App\AntiSpam\Enums\CandidateKind;
use App\Models\Portal\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Ein zu bewertender Datensatz, aus der Sicht der Regeln (#10).
 *
 * Die Regeln arbeiten absichtlich nicht auf den Models: `users` liegt zentral,
 * `companies` je Portal, und die Serien-Regel braucht Werte, die in keiner
 * Spalte stehen ({@see BurstCounts}). Dieses Objekt haelt genau die Felder, die
 * docs/turnstile.md §7 nennt — eine neue Regel darf lesen, was hier steht, und
 * fuehrt keine eigene Abfrage.
 *
 * Alles ist nullable: der Bestand ist gewachsen, und eine Regel, die auf einem
 * fehlenden Wert umfaellt, markiert im Zweifel einen echten Betrieb.
 */
final class BotCandidate
{
    /**
     * @param  array<string, string|null>  $fields  Profilfelder eines Eintrags
     *                                              (description, street, zipcode, tel, email, website)
     */
    public function __construct(
        public readonly CandidateKind $kind,
        public readonly int $id,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly ?CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $verifiedAt = null,
        public readonly ?CarbonImmutable $lastSeenAt = null,
        public readonly ?string $ipHash = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $ownerName = null,
        public readonly bool $hasPlacesId = false,
        public readonly bool $hasListing = false,
        public readonly bool $hasClaim = false,
        public readonly array $fields = [],
        public readonly BurstCounts $burst = new BurstCounts,
    ) {}

    public static function forUser(User $user, bool $hasListing, bool $hasClaim, BurstCounts $burst): self
    {
        return new self(
            kind: CandidateKind::Account,
            id: (int) $user->getKey(),
            name: $user->name,
            email: $user->email,
            createdAt: self::moment($user->created_at),
            verifiedAt: self::moment($user->email_verified_at),
            lastSeenAt: self::moment($user->last_seen_at),
            ipHash: $user->registration_ip_hash,
            userAgent: $user->registration_user_agent,
            hasListing: $hasListing,
            hasClaim: $hasClaim,
            burst: $burst,
        );
    }

    public static function forCompany(Company $company, BurstCounts $burst): self
    {
        $owner = $company->owner;

        return new self(
            kind: CandidateKind::Listing,
            id: (int) $company->getKey(),
            name: $company->name,
            email: $company->email,
            createdAt: self::moment($company->created_at),
            ipHash: $company->created_ip_hash,
            userAgent: $company->created_user_agent,
            ownerName: $owner instanceof User ? $owner->name : null,
            hasPlacesId: filled($company->google_places_id),
            fields: [
                'description' => $company->description,
                'street' => $company->street,
                'zipcode' => $company->zipcode,
                'tel' => $company->tel,
                'email' => $company->email,
                'website' => $company->website,
            ],
            burst: $burst,
        );
    }

    public function isAccount(): bool
    {
        return $this->kind === CandidateKind::Account;
    }

    public function isListing(): bool
    {
        return $this->kind === CandidateKind::Listing;
    }

    /** Alter in Tagen, oder null wenn kein Zeitstempel vorliegt. */
    public function ageInDays(): ?int
    {
        return $this->createdAt === null
            ? null
            : (int) $this->createdAt->diffInDays(CarbonImmutable::now());
    }

    /** Wie viele der genannten Profilfelder sind leer? */
    public function emptyFieldCount(string ...$keys): int
    {
        $leer = 0;

        foreach ($keys as $key) {
            if (blank($this->fields[$key] ?? null)) {
                $leer++;
            }
        }

        return $leer;
    }

    private static function moment(mixed $value): ?CarbonImmutable
    {
        return $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : null;
    }
}
