<?php

namespace App\Support\Translation;

use App\Models\TenantText;
use App\Support\Tenancy\TenantVertical;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Legt die Tenant-Overrides aus tenant_texts ueber die Sprachdateien (#4).
 *
 * Nur Gruppen der Anwendung ('*'-Namespace), keine Package-Namespaces und
 * keine JSON-Uebersetzungen. Tenant-ID steht im Cache-Key statt in Tags,
 * damit auch file- und database-Cache funktionieren; geleert wird der
 * Eintrag von TenantText::forgetCache().
 */
class TenantOverrideLoader implements Loader
{
    /**
     * Unterordner je Vertikale in lang/{locale}/ (#14).
     */
    public const VERTICALS_DIR = 'verticals';

    public function __construct(
        protected Loader $loader,
    ) {}

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        if ($group === '*' || ($namespace !== null && $namespace !== '*')) {
            return $lines;
        }

        $tenantId = $this->tenantId();

        if ($tenantId === null || str_starts_with($group, self::VERTICALS_DIR.'/')) {
            return $lines;
        }

        $lines = $this->withVertical($lines, $locale, $group);

        foreach ($this->overrides($tenantId, $locale, $group) as $key => $value) {
            Arr::set($lines, $key, $value);
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->loader->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->loader->namespaces();
    }

    /**
     * Vertikalen-Ebene (#14) zwischen Sprachdatei und DB-Overrides: legt
     * lang/{locale}/verticals/{vertical}/{group}.php ueber die Basis. Die Datei
     * enthaelt nur abweichende Keys, deshalb rekursiv ersetzen statt tauschen.
     * Kein eigener Cache-Key noetig: die Datei ist Code, und der Translator
     * haelt geladene Gruppen ohnehin je Tenant (TranslationBootstrapper).
     *
     * @param  array<string, mixed>  $lines
     * @return array<string, mixed>
     */
    protected function withVertical(array $lines, string $locale, string $group): array
    {
        $vertical = TenantVertical::resolve(tenant(TenantVertical::ATTRIBUTE));

        if ($vertical === TenantVertical::DEFAULT) {
            return $lines;
        }

        $verticalLines = $this->loader->load($locale, self::VERTICALS_DIR."/{$vertical}/{$group}");

        return $verticalLines === [] ? $lines : array_replace_recursive($lines, $verticalLines);
    }

    /**
     * @return array<string, string>
     */
    protected function overrides(int|string $tenantId, string $locale, string $group): array
    {
        return Cache::rememberForever(
            TenantText::cacheKey($tenantId, $locale, $group),
            fn (): array => TenantText::query()
                ->where('tenant_id', $tenantId)
                ->where('locale', $locale)
                ->where('group', $group)
                ->pluck('value', 'key')
                ->all(),
        );
    }

    protected function tenantId(): int|string|null
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        return tenant()?->getKey();
    }
}
