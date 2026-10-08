<?php

declare(strict_types=1);

namespace App\AntiSpam;

use App\AntiSpam\Enums\CandidateKind;
use App\AntiSpam\Support\BotCandidate;
use App\AntiSpam\Support\BurstIndex;
use App\AntiSpam\Support\ScanHit;
use App\AntiSpam\Support\ScanResult;
use App\Enums\PlanTier;
use App\Jobs\GenerateTenantSitemapJob;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantCache;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Der Lauf ueber ein Portal (#10): bewerten, und auf Wunsch markieren.
 *
 * Was geprueft wird — und vor allem, was nicht:
 *
 *  * **Konten**: alle Nutzer, die diesem Portal zugeordnet sind (`tenant_user`).
 *    Ohne Administratoren und ohne freigegebene Datensaetze.
 *  * **Eintraege**: ausschliesslich Selbsteintragungen, also
 *    `google_places_id IS NULL`. Die importierten Google-Betriebe — bei
 *    sanitaerfinden.com rund 19.600 von 19.646 — werden nie bewertet. Das ist
 *    keine Optimierung, sondern die Absicherung: ein Importbestand hat
 *    regelmaessig leere Profilfelder und Inhabernamen als Firmennamen und
 *    wuerde reihenweise in die Quarantaene laufen.
 *  * Zahlende, verifizierte und hoeher eingestufte Betriebe bleiben aussen vor
 *    ({@see self::listings()}), zahlende Konten ebenso
 *    ({@see self::isProtected()}).
 *
 * Bewertet wird mit {@see BotScorer}; geschrieben ausschliesslich ueber
 * {@see BotQuarantine}. Ohne `$apply` wird nichts geschrieben — der Trockenlauf
 * nimmt genau denselben Weg und unterscheidet sich nur im letzten Schritt.
 *
 * Nach einem schreibenden Lauf werden die Ortslisten und Zaehler des Portals
 * verworfen und die Sitemap neu gebaut: ein stillgelegter Eintrag faellt aus
 * Company::active() heraus, die Caches wuessten davon sonst erst nach Ablauf.
 */
class SuspectedBotScanner
{
    /** Ortslisten und Zaehler, die aktive Betriebe enthalten. */
    private const CACHE_KEYS = [
        'portal.cities.hero',
        'portal.cities.sidebar',
        'portal.cities.public.index.top20',
        'portal.stats',
        'sun-v2.home.cities',
        'sun-v2.cities.index.v3',
    ];

    public function __construct(
        private readonly BotScorer $scorer,
        private readonly BotQuarantine $quarantine,
    ) {}

    public function scan(Tenant $tenant, int $threshold, bool $apply): ScanResult
    {
        try {
            return $tenant->run(fn (): ScanResult => $this->run($tenant, $threshold, $apply));
        } catch (Throwable $exception) {
            return ScanResult::failed(
                tenantId: (int) $tenant->getKey(),
                tenantName: (string) $tenant->name,
                threshold: $threshold,
                error: $exception->getMessage(),
            );
        }
    }

    private function run(Tenant $tenant, int $threshold, bool $apply): ScanResult
    {
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'suspected_bot_at')) {
            return ScanResult::failed(
                tenantId: (int) $tenant->getKey(),
                tenantName: (string) $tenant->name,
                threshold: $threshold,
                error: 'Spalten der Quarantäne fehlen — tenants:migrate ausstehend.',
            );
        }

        $accounts = $this->accounts($tenant);
        $listings = $this->listings();
        $protectedSkipped = 0;

        $hits = [
            ...$this->evaluateAccounts($accounts, $threshold, $apply, $protectedSkipped),
            ...$this->evaluateListings($listings, $threshold, $apply, $protectedSkipped),
        ];

        $result = new ScanResult(
            tenantId: (int) $tenant->getKey(),
            tenantName: (string) $tenant->name,
            threshold: $threshold,
            applied: $apply,
            accountsScanned: $accounts->count(),
            listingsScanned: $listings->count(),
            hits: $hits,
            protectedSkipped: $protectedSkipped,
        );

        if ($apply && $result->listingHits() > 0) {
            $this->refreshPortal($tenant);
        }

        return $result;
    }

    /**
     * Konten dieses Portals. Administratoren und freigegebene Datensaetze
     * fallen schon in der Abfrage heraus.
     *
     * @return EloquentCollection<int, User>
     */
    private function accounts(Tenant $tenant): EloquentCollection
    {
        /** @var EloquentCollection<int, User> $users */
        $users = $tenant->users()
            ->where(function ($query): void {
                $query->where('users.is_admin', false)->orWhereNull('users.is_admin');
            })
            ->whereNull('users.suspected_bot_cleared_at')
            ->orderBy('users.id')
            ->get();

        return $users;
    }

    /**
     * Selbsteintragungen dieses Portals. Alles, was Geld, eine Verifizierung
     * oder eine Places-ID traegt, bleibt aussen vor.
     *
     * @return EloquentCollection<int, Company>
     */
    private function listings(): EloquentCollection
    {
        /** @var EloquentCollection<int, Company> $companies */
        $companies = Company::query()
            ->with('owner')
            ->whereNull('google_places_id')
            ->whereNull('suspected_bot_cleared_at')
            ->where('is_premium', false)
            ->where('is_verified', false)
            ->where(function ($query): void {
                $query->whereNull('plan_tier')->orWhere('plan_tier', PlanTier::Free->value);
            })
            ->orderBy('id')
            ->get();

        return $companies;
    }

    /**
     * @param  EloquentCollection<int, User>  $users
     * @return list<ScanHit>
     */
    private function evaluateAccounts(EloquentCollection $users, int $threshold, bool $apply, int &$protectedSkipped): array
    {
        $index = BurstIndex::build($users->map(fn (User $user): array => [
            'created_at' => $user->created_at,
            'ip_hash' => $user->registration_ip_hash,
            'email' => $user->email,
        ])->all());

        $hits = [];

        foreach ($users as $user) {
            $candidate = BotCandidate::forUser(
                user: $user,
                hasListing: Company::query()->where('user_id', $user->getKey())->exists(),
                hasClaim: $user->first_claim_at !== null,
                burst: $index->countsFor($user->created_at, $user->registration_ip_hash, $user->email),
            );

            $score = $this->scorer->score($candidate);

            if (! $score->isSuspect($threshold)) {
                continue;
            }

            if ($this->isProtected($user)) {
                $protectedSkipped++;

                continue;
            }

            $hits[] = new ScanHit(
                kind: CandidateKind::Account,
                id: $candidate->id,
                name: $candidate->name,
                email: $candidate->email,
                score: $score,
                createdAt: $candidate->createdAt,
                marked: $apply && $this->quarantine->mark($user, $score),
            );
        }

        return $hits;
    }

    /**
     * @param  EloquentCollection<int, Company>  $companies
     * @return list<ScanHit>
     */
    private function evaluateListings(EloquentCollection $companies, int $threshold, bool $apply, int &$protectedSkipped): array
    {
        $index = BurstIndex::build($companies->map(fn (Company $company): array => [
            'created_at' => $company->created_at,
            'ip_hash' => $company->created_ip_hash,
            'email' => $company->email,
        ])->all());

        $hits = [];

        foreach ($companies as $company) {
            $candidate = BotCandidate::forCompany(
                company: $company,
                burst: $index->countsFor($company->created_at, $company->created_ip_hash, $company->email),
            );

            $score = $this->scorer->score($candidate);

            if (! $score->isSuspect($threshold)) {
                continue;
            }

            $owner = $company->owner;

            if ($owner !== null && $this->isProtected($owner)) {
                $protectedSkipped++;

                continue;
            }

            $hits[] = new ScanHit(
                kind: CandidateKind::Listing,
                id: $candidate->id,
                name: $candidate->name,
                email: $candidate->email,
                score: $score,
                createdAt: $candidate->createdAt,
                marked: $apply && $this->quarantine->mark($company, $score),
            );
        }

        return $hits;
    }

    /**
     * Konten, die nie markiert werden: Administratoren, Konten mit einem
     * Abonnement oder einer Bestellung, und Inhaber eines aktiven Eintrags in
     * diesem Portal. Geprueft wird erst beim Treffer — fuer den Normalfall
     * waeren das drei Abfragen je Konto ohne Gegenwert.
     */
    private function isProtected(User $user): bool
    {
        if ((bool) $user->is_admin) {
            return true;
        }

        if ($user->subscriptions()->exists() || $user->orders()->exists()) {
            return true;
        }

        return Company::query()
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->whereNull('suspected_bot_at')
            ->exists();
    }

    /**
     * Nach einem schreibenden Lauf: Ortslisten und Zaehler verwerfen, Sitemap
     * und robots.txt neu bauen. Die Sitemap laeuft ueber die Warteschlange —
     * sie kann bei 20.000 Betrieben Minuten dauern und darf keinen Lauf
     * aufhalten.
     */
    private function refreshPortal(Tenant $tenant): void
    {
        foreach (self::CACHE_KEYS as $key) {
            TenantCache::forget($key);
        }

        GenerateTenantSitemapJob::dispatch((string) $tenant->getKey());
    }
}
