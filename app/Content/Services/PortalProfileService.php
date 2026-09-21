<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Constants\TenantConfigConstants;
use App\Content\Models\TenantContentSetting;
use App\Content\Sources\Support\RegionResolver;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Portal- und Redaktionsprofil des laufenden Mandanten (#18).
 *
 * Einzige Quelle fuer die Angaben, die llms.txt, die Autorenseite und das
 * Organization-JSON-LD gemeinsam brauchen: Portalname, Kurzbeschreibung,
 * Branche, Region, Autor und sameAs-Profile. Die redaktionellen Felder liegen
 * in `tenant_content_settings`, das Uebrige in den Branding-Einstellungen des
 * Tenants.
 *
 * Portale ohne Content-Pipeline haben die Tabelle nicht — dort liefert der
 * Dienst die Branding-Werte und leere Profillisten, statt zu scheitern.
 */
class PortalProfileService
{
    private ?TenantContentSetting $settings = null;

    private bool $settingsLoaded = false;

    public function __construct(
        private readonly TenantBrandingService $branding,
    ) {}

    public function tenant(): ?Tenant
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    /**
     * Redaktionelle Einstellungen des Mandanten, ohne sie anzulegen: die
     * oeffentlichen Seiten lesen nur, schreiben duerfen Panel und Seeder.
     */
    public function settings(): ?TenantContentSetting
    {
        if ($this->settingsLoaded) {
            return $this->settings;
        }

        $this->settingsLoaded = true;

        $model = new TenantContentSetting;

        if (! Schema::connection($model->getConnectionName())->hasTable('tenant_content_settings')) {
            return $this->settings = null;
        }

        return $this->settings = TenantContentSetting::query()->first();
    }

    public function siteName(): string
    {
        $title = (string) $this->brandingValue(TenantConfigConstants::SITE_TITLE);

        return $title !== '' ? $title : ($this->tenant()?->name ?? (string) config('app.name'));
    }

    public function description(): string
    {
        $description = trim((string) $this->brandingValue(TenantConfigConstants::SITE_DESCRIPTION));

        if ($description !== '') {
            return $description;
        }

        $branch = $this->branchLabel();
        $region = $this->regionLabel();
        $subject = $branch !== null ? "Betriebe aus dem Bereich {$branch}" : 'Betriebe und Dienstleister';

        return "{$this->siteName()} ist ein Branchenportal für {$subject}"
            .($region !== null ? " in {$region}" : ' in Deutschland')
            .' mit Firmenverzeichnis, Ratgeber und Jobangeboten.';
    }

    public function branchLabel(): ?string
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return null;
        }

        $branch = BranchResolver::resolve($tenant);

        return $branch !== null ? BranchResolver::label($branch) : null;
    }

    /**
     * Schwerpunktregion aus den bevorzugten Bundeslaendern. Ohne Auswahl
     * arbeitet das Portal bundesweit.
     */
    public function regionLabel(): ?string
    {
        $states = $this->settings()?->preferred_states_json ?? [];

        $labels = array_values(array_filter(array_map(
            fn ($code): ?string => is_string($code) ? RegionResolver::stateLabel($code) : null,
            $states,
        )));

        return $labels === [] ? null : implode(', ', $labels);
    }

    public function authorName(): string
    {
        $name = trim((string) $this->settings()?->author_name);

        return $name !== '' ? $name : 'Redaktion '.$this->siteName();
    }

    public function authorSlug(): string
    {
        return Str::slug($this->authorName()) ?: 'redaktion';
    }

    public function authorUrl(): string
    {
        return route('portal.author.show', $this->authorSlug());
    }

    public function authorBio(): ?string
    {
        $bio = trim((string) $this->settings()?->author_bio);

        return $bio !== '' ? $bio : null;
    }

    /**
     * Profile der Redaktion; ohne eigene Angabe die des Portals.
     *
     * @return list<string>
     */
    public function authorSameAs(): array
    {
        $own = $this->urlList($this->settings()?->author_same_as_json ?? []);

        return $own !== [] ? $own : $this->organizationSameAs();
    }

    /**
     * Profile des Portals: gepflegte Verweise aus den Content-Einstellungen
     * und die Social-Media-Konten aus den Tenant-Einstellungen.
     *
     * @return list<string>
     */
    public function organizationSameAs(): array
    {
        $social = array_map(
            fn (string $key) => $this->brandingValue($key),
            [
                TenantConfigConstants::SOCIAL_FACEBOOK,
                TenantConfigConstants::SOCIAL_INSTAGRAM,
                TenantConfigConstants::SOCIAL_TWITTER,
                TenantConfigConstants::SOCIAL_LINKEDIN,
            ],
        );

        return $this->urlList(array_merge(
            $this->settings()?->organization_same_as_json ?? [],
            $social,
        ));
    }

    public function logoUrl(): ?string
    {
        $path = $this->brandingValue(TenantConfigConstants::LOGO_PATH);

        return is_string($path) && $path !== '' ? asset($path) : null;
    }

    /**
     * Organization-Knoten fuer die Ratgeber-Seiten. Die @id ist portalweit
     * stabil, damit Article, Person und Uebersichtsseiten denselben Herausgeber
     * meinen und nicht drei verschiedene.
     *
     * @return array<string, mixed>
     */
    public function organizationJsonLd(): array
    {
        return array_filter([
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => $this->siteName(),
            'url' => url('/'),
            'description' => $this->description(),
            'logo' => $this->logoUrl(),
            'sameAs' => $this->organizationSameAs(),
            'publishingPrinciples' => route('portal.blog.editorial'),
            'email' => $this->brandingValue(TenantConfigConstants::CONTACT_EMAIL) ?: null,
            'telephone' => $this->brandingValue(TenantConfigConstants::CONTACT_PHONE) ?: null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Person-Knoten der Autorenseite.
     *
     * @return array<string, mixed>
     */
    public function personJsonLd(): array
    {
        return array_filter([
            '@type' => 'Person',
            '@id' => $this->authorUrl().'#person',
            'name' => $this->authorName(),
            'url' => $this->authorUrl(),
            'description' => $this->authorBio(),
            'image' => $this->logoUrl(),
            'sameAs' => $this->authorSameAs(),
            'worksFor' => ['@id' => url('/').'#organization'],
            'publishingPrinciples' => route('portal.blog.editorial'),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    private function brandingValue(string $key): mixed
    {
        $tenant = $this->tenant();

        return $tenant !== null ? $this->branding->get($tenant, $key) : null;
    }

    /**
     * @param  iterable<mixed>  $values
     * @return list<string>
     */
    private function urlList(iterable $values): array
    {
        $urls = [];

        foreach ($values as $value) {
            $url = is_string($value) ? trim($value) : '';

            if ($url === '' || ! str_starts_with($url, 'http')) {
                continue;
            }

            $urls[$url] = $url;
        }

        return array_values($urls);
    }
}
