<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Dto\Leads\FunnelDefinition;
use App\Enums\PremiumFeature;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use App\Models\Portal\Review;
use App\Services\Leads\FunnelDefinitionClient;
use App\Services\Premium\CompanyEntitlementService;
use App\Support\Breadcrumb;
use App\Support\CityUrl;
use App\Support\PhoneNumber;
use App\Support\TenantCache;
use App\Themes\ThemeManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Firmenprofil im Theme sun-v2 (Vorlage sun-v2--profil.html): Schnellfakten,
 * Oeffnungszeiten je Wochentag, Bewertungsverteilung, Kartenlink und weitere
 * Betriebe am selben Ort. Dazu der Anfrage-Funnel des Portals (#26): ohne
 * veroeffentlichten Funnel zeigt das Profil statt "Angebot anfragen" nur
 * "Jetzt anrufen".
 *
 * Die Firma selbst laedt CompanyController::renderCompanyShow() fuer alle
 * Themes; hier kommt nur dazu, was die Vorlage zusaetzlich zeigt.
 */
final class ProfileViewComposer
{
    private const DAYS = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

    public function __construct(
        private readonly ThemeManager $themes,
        private readonly FunnelDefinitionClient $funnels,
        private readonly CompanyEntitlementService $entitlements,
    ) {}

    public function compose(View $view): void
    {
        if ($this->themes->active()?->slug !== HomeViewComposer::THEME) {
            return;
        }

        /** @var Company $company */
        $company = $view->getData()['company'];
        $cityName = $company->city?->getAttribute('name');
        // Werbefreies Profil (#8): keine weiteren Betriebe, Abfrage entfaellt
        $adFree = $this->entitlements->can($company, PremiumFeature::AdFree);

        $view->with('profile', [
            'initials' => self::initials($company->name),
            // Logo aus der Media Library, sonst Spalte logo_path; ohne Logo bleiben die Initialen
            'logo' => $company->logo_url,
            'status' => OpeningStatus::for($company),
            'facts' => $this->facts($company),
            'paragraphs' => self::paragraphs((string) $company->description),
            'hours' => $this->hours($company),
            'distribution' => $this->distribution($company),
            'mapsUrl' => $company->full_address
                ? 'https://maps.google.com/?q='.urlencode($company->full_address)
                : null,
            // E.164 fuer tel:-Links und JSON-LD, null wenn nicht normalisierbar
            'phone' => PhoneNumber::toE164($company->tel),
            'phoneDisplay' => PhoneNumber::display($company->tel),
            'cityLabel' => $cityName ? __('portal.profile.subtitle', ['stadt' => $cityName]) : null,
            'cityUrl' => $company->city
                ? CityUrl::show($company->city)
                : route('portal.companies.index'),
            'breadcrumb' => Breadcrumb::forCompany($company),
            'nearby' => $adFree ? new Collection : $this->nearby($company),
            'nearbyCount' => $adFree ? 0 : $this->nearbyCount($company),
            'leadFunnel' => $this->leadFunnel(),
        ]);
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name))) ?: [];

        $initials = count($words) > 1
            ? mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1)
            : mb_substr($words[0] ?? '?', 0, 2);

        return mb_strtoupper($initials);
    }

    /**
     * Schnellfakten nur aus vorhandenen Daten; Antwortzeit und Anfragezahlen
     * der Vorlage gibt es im Datenmodell nicht.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function facts(Company $company): array
    {
        return array_values(array_filter([
            $company->city ? ['label' => __('portal.profile.facts.location'), 'value' => (string) $company->city->getAttribute('name')] : null,
            $company->rating_count > 0 ? ['label' => __('portal.profile.facts.reviews'), 'value' => number_format($company->rating_count, 0, ',', '.')] : null,
            $company->categories->isNotEmpty() ? ['label' => __('portal.profile.facts.services'), 'value' => (string) $company->categories->count()] : null,
            $company->created_at ? ['label' => __('portal.profile.facts.member_since'), 'value' => $company->created_at->format('Y')] : null,
        ]));
    }

    /**
     * Beschreibung als Absaetze. Die Texte sind groesstenteils Markdown aus
     * dem Generator (**fett**, ### Zwischentitel, Aufzaehlungen mit
     * Zeilenumbruch): Markierungen fallen weg, Zwischentitel werden als
     * Titelzeile markiert, Zeilenumbrueche im Absatz bleiben. Ein
     * einzeiliger fetter Titel am Anfang doppelt die Ueberschrift "Über …"
     * und entfaellt, ebenso die Generator-Zeile "Optimiert für Suchbegriffe".
     * Ab dem zweiten Absatz klappt "Mehr lesen" auf.
     *
     * @return array<int, array{title: string|null, text: string}>
     */
    private static function paragraphs(string $text): array
    {
        $blocks = preg_split('/\R\s*\R/u', trim($text)) ?: [];
        $paragraphs = [];

        foreach ($blocks as $index => $block) {
            $block = trim($block);
            $heading = (bool) preg_match('/^#{1,6}\s/u', $block)
                || ((bool) preg_match('/^\*\*[^\n]+\*\*$/u', $block) && ! str_contains($block, "\n"));

            $clean = trim((string) preg_replace(
                ['/^#{1,6}\s*/mu', '/\*\*(.+?)\*\*/u', '/(?<![\p{L}\p{N}])[*_](.+?)[*_](?![\p{L}\p{N}])/u', '/[ \t]+$/mu'],
                ['', '$1', '$1', ''],
                $block,
            ));

            if ($clean === '' || str_starts_with($clean, 'Optimiert für Suchbegriffe')) {
                continue;
            }

            if ($index === 0 && $heading && count($blocks) > 1) {
                continue;
            }

            // Zwischentitel: erste Zeile als Titel, Rest als Absatz darunter
            [$title, $body] = $heading
                ? array_pad(preg_split('/\R/u', $clean, 2) ?: [], 2, '')
                : [null, $clean];

            $paragraphs[] = ['title' => $title, 'text' => trim((string) $body)];
        }

        return $paragraphs;
    }

    /**
     * Alle sieben Tage, je Tag die Zeitfenster; heute hervorgehoben.
     *
     * @return array<int, array{day: string, slots: array<int, string>, today: bool}>
     */
    private function hours(Company $company): array
    {
        if ($company->openingHours->isEmpty()) {
            return [];
        }

        $today = CarbonImmutable::now('Europe/Berlin')->dayOfWeekIso - 1;
        $byDay = $company->openingHours->groupBy('day_of_week');

        return collect(self::DAYS)->map(fn (string $day, int $index): array => [
            'day' => $day,
            'slots' => $byDay->get($index, collect())
                ->filter(fn (CompanyOpeningHour $hour): bool => ! $hour->is_closed && $hour->opens_at && $hour->closes_at)
                ->sortBy('opens_at')
                ->map(fn (CompanyOpeningHour $hour): string => substr($hour->opens_at, 0, 5).'–'.substr($hour->closes_at, 0, 5))
                ->values()
                ->all(),
            'today' => $index === $today,
        ])->all();
    }

    /**
     * Anzahl freigegebener Bewertungen je Sternzahl 5..1 (auf ganze Sterne gerundet).
     *
     * @return array<int, int>
     */
    private function distribution(Company $company): array
    {
        if ($company->rating_count <= 0) {
            return [];
        }

        $counts = Review::approved()
            ->where('company_id', $company->id)
            ->selectRaw('ROUND(rating) as stars, COUNT(*) as total')
            ->groupBy('stars')
            ->pluck('total', 'stars');

        return collect([5, 4, 3, 2, 1])
            ->mapWithKeys(fn (int $stars): array => [$stars => (int) ($counts[$stars] ?? 0)])
            ->all();
    }

    /**
     * @return Collection<int, Company>
     */
    private function nearby(Company $company): Collection
    {
        if (! $company->city_id) {
            return new Collection;
        }

        return Cache::remember(TenantCache::key("sun-v2.profile.nearby.{$company->city_id}.{$company->id}"), 3600, fn (): Collection => Company::active()
            ->where('city_id', $company->city_id)
            ->whereKeyNot($company->id)
            ->with(['categories', 'city'])
            ->orderByDesc('rating')
            ->orderByDesc('rating_count')
            ->take(3)
            ->get());
    }

    private function nearbyCount(Company $company): int
    {
        if (! $company->city_id) {
            return 0;
        }

        return (int) Cache::remember(TenantCache::key("sun-v2.profile.city_count.{$company->city_id}"), 3600, fn (): int => Company::active()
            ->where('city_id', $company->city_id)
            ->count());
    }

    private function leadFunnel(): ?FunnelDefinition
    {
        $token = tenant()?->leadFunnelToken();

        return $token === null ? null : $this->funnels->get($token);
    }
}
