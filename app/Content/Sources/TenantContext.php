<?php

declare(strict_types=1);

namespace App\Content\Sources;

use App\Content\Models\TenantContentSetting;
use App\Content\Sources\Support\StateCatalog;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Alles, was ein Connector ueber den Mandanten wissen muss (#7).
 *
 * Wird ausschliesslich innerhalb des Tenant-Kontexts erzeugt, weil die
 * Einstellungen in der Tenant-DB liegen. Connectoren bekommen den Kontext
 * als einzigen Parameter und greifen nie selbst auf Tenant() zu.
 */
final class TenantContext
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $tenantKey,
        public readonly string $name,
        public readonly ?string $domain,
        public readonly TenantContentSetting $settings,
    ) {}

    /**
     * Muss innerhalb von $tenant->run(...) aufgerufen werden.
     */
    public static function forTenant(Tenant $tenant): self
    {
        return new self(
            tenantId: (int) $tenant->getKey(),
            tenantKey: (string) $tenant->uuid,
            name: (string) $tenant->name,
            domain: $tenant->domain !== null && $tenant->domain !== '' ? (string) $tenant->domain : null,
            settings: TenantContentSetting::current(),
        );
    }

    /**
     * Branchen-Keywords des Mandanten, flach und dedupliziert.
     *
     * Der Texter (#13) pflegt sie entweder als flache Liste oder gruppiert
     * (["heizung" => ["waermepumpe", ...]]); beide Formen werden unterstuetzt.
     *
     * @return array<int, string>
     */
    public function branchKeywords(): array
    {
        $keywords = [];

        foreach ($this->settings->branch_keywords_json ?? [] as $entry) {
            foreach (is_array($entry) ? $entry : [$entry] as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $keywords[] = trim($value);
                }
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Branchen-Keywords in ihrer gepflegten Gruppierung; eine flache Liste
     * landet unter dem Schluessel 'default'.
     *
     * @return array<string, array<int, string>>
     */
    public function branchKeywordGroups(): array
    {
        $groups = [];

        foreach ($this->settings->branch_keywords_json ?? [] as $key => $entry) {
            if (is_array($entry)) {
                $groups[(string) $key] = array_values(array_filter(
                    array_map(fn ($value) => is_string($value) ? trim($value) : '', $entry),
                    fn (string $value) => $value !== '',
                ));

                continue;
            }

            if (is_string($entry) && trim($entry) !== '') {
                $groups['default'][] = trim($entry);
            }
        }

        return $groups;
    }

    /**
     * Branchen-Schluessel des Mandanten (BranchResolver), null ohne Treffer.
     * Steuert in #11, welche Feeds, Statistiktabellen und News-Suchen laufen.
     */
    public function branch(): ?string
    {
        return BranchResolver::resolveFrom($this->name, $this->domain);
    }

    public function branchLabel(): ?string
    {
        $branch = $this->branch();

        return $branch === null ? null : BranchResolver::label($branch);
    }

    /**
     * @return array<int, string>
     */
    public function allowedRegionScopes(): array
    {
        $scopes = $this->settings->allowed_region_scopes_json ?? [];

        return $scopes === [] ? ['national', 'state', 'city'] : array_values($scopes);
    }

    /**
     * Bevorzugte Bundeslaender als ISO-3166-2-Code ('DE-BY').
     *
     * Gepflegt sein darf sowohl der ISO-Code als auch der ausgeschriebene
     * Name oder ein alter Slug — nach aussen gibt es seit #33 nur noch das
     * ISO-Vokabular. Unbekannte Eintraege fallen weg.
     *
     * @return array<int, string>
     */
    public function preferredStates(): array
    {
        $codes = [];

        foreach ($this->settings->preferred_states_json ?? [] as $entry) {
            if (! is_string($entry) || trim($entry) === '') {
                continue;
            }

            $value = trim($entry);
            $iso = StateCatalog::exists(mb_strtoupper($value))
                ? mb_strtoupper($value)
                : StateCatalog::fromText($value);

            if ($iso !== null) {
                $codes[$iso] = true;
            }
        }

        return array_keys($codes);
    }

    public function allowsRegionScope(string $scope): bool
    {
        return in_array($scope, $this->allowedRegionScopes(), true);
    }

    public function baseUrl(): string
    {
        if ($this->domain === null) {
            return rtrim((string) config('app.url'), '/');
        }

        $scheme = app()->environment('production') ? 'https' : 'http';

        return "{$scheme}://{$this->domain}";
    }

    /**
     * Kontaktseite des Crawlers, landet im User-Agent jedes HTTP-Abrufs.
     */
    public function botUrl(): string
    {
        $path = trim((string) config('content.sources.http.bot_path', 'bot'), '/');

        return $this->baseUrl().'/'.$path;
    }

    public function slug(): string
    {
        return Str::slug($this->name);
    }
}
