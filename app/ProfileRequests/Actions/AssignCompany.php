<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Models\User;
use App\ProfileRequests\Ai\CompanyCandidateFinder;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\TenantCompanyResolver;
use Illuminate\Support\Str;

/**
 * Portal und Betrieb an eine Anfrage haengen (SUN-RQ #7).
 *
 * Noetig, wenn die Mail-Erkennung (#5) kein Portal fand oder die
 * KI-Zuordnung (#6) unter `classification.min_confidence` blieb. Ohne diese
 * Zuordnung sind „Uebernehmen" und „Loeschung" im Panel gesperrt — eine
 * Loeschung im falschen Portal ist nicht rueckholbar.
 *
 * Geprueft wird vor dem Speichern, dass es den Betrieb in der Datenbank
 * **dieses** Portals wirklich gibt; die ID kommt aus dem Browser und waere
 * sonst eine Referenz ins Leere. Die Pruefung laeuft im Tenant-Kontext
 * ({@see TenantCompanyResolver}), geschrieben wird in der Central-DB.
 */
class AssignCompany
{
    /** Hoechstzahl Treffer der Namenssuche im Panel. */
    private const SEARCH_LIMIT = 20;

    public function __construct(
        private readonly TenantCompanyResolver $resolver,
        private readonly CompanyCandidateFinder $candidateFinder,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function __invoke(ProfileRequest $request, int $tenantId, ?int $companyId, ?User $actor = null): array
    {
        $tenant = $this->resolver->tenant($tenantId);

        if (! $tenant instanceof Tenant) {
            return ['ok' => false, 'message' => 'Dieses Portal gibt es nicht.'];
        }

        $name = null;

        if ($companyId !== null) {
            $company = $this->resolver->resolve($tenantId, $companyId, withTrashed: true);

            if ($company === null) {
                return ['ok' => false, 'message' => 'Dieser Betrieb liegt nicht in der Datenbank des gewählten Portals.'];
            }

            $name = (string) $company->name;
        }

        $vorher = ['tenant_id' => $request->tenant_id, 'company_id' => $request->company_id];

        $request->forceFill([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
        ])->save();

        $request->forgetCompany();

        $request->recordEvent(
            RequestEventType::NOTE,
            [
                'note' => $companyId === null ? 'Portal zugeordnet' : 'Betrieb zugeordnet',
                'von' => $vorher,
                'nach' => ['tenant_id' => $tenantId, 'company_id' => $companyId],
                'betrieb' => $name,
            ],
            $actor === null ? RequestEvent::ACTOR_SYSTEM : RequestEvent::ACTOR_USER,
            $actor?->getKey() === null ? null : (int) $actor->getKey(),
        );

        return [
            'ok' => true,
            'message' => $name === null
                ? 'Portal zugeordnet — der Betrieb fehlt noch.'
                : "Zugeordnet: {$name}.",
        ];
    }

    /**
     * Die KI-Kandidaten zu dieser Anfrage — dieselbe Suche, die vor der
     * Klassifikation lief (#6). Leere Liste, solange kein Portal feststeht.
     *
     * @return list<array<string, mixed>>
     */
    public function candidates(ProfileRequest $request): array
    {
        return $this->candidateFinder->forRequest($request);
    }

    /**
     * Namenssuche im Portal — der zweite Weg, wenn kein Kandidat passt.
     *
     * @return array<int, string> company_id => Beschriftung
     */
    public function search(int $tenantId, string $term): array
    {
        $term = Str::squish($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $rows = $this->resolver->runFor($tenantId, function () use ($term): array {
            return Company::query()
                ->withTrashed()
                ->where(function ($query) use ($term): void {
                    $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
                        ->orWhere('email', 'like', '%'.addcslashes($term, '%_\\').'%');
                })
                ->with('city')
                ->orderBy('name')
                ->limit(self::SEARCH_LIMIT)
                ->get()
                ->mapWithKeys(fn (Company $company): array => [(int) $company->getKey() => self::label($company)])
                ->all();
        });

        return $rows ?? [];
    }

    /**
     * Beschriftung eines einzelnen Betriebs — fuer die Vorbelegung der
     * Auswahl im Panel, die keinen Suchbegriff hat.
     */
    public function option(int $tenantId, int $companyId): ?string
    {
        $company = $this->resolver->resolve($tenantId, $companyId, withTrashed: true);

        return $company === null ? null : $this->resolver->runFor($tenantId, fn (): string => self::label($company));
    }

    private static function label(Company $company): string
    {
        return sprintf(
            '%s — %s %s (#%d)%s',
            $company->name,
            (string) $company->zipcode,
            (string) ($company->city->name ?? ''),
            $company->getKey(),
            $company->trashed() ? ' · gelöscht' : '',
        );
    }
}
