<?php

declare(strict_types=1);

namespace App\Content\Assets;

use App\Constants\TenantConfigConstants;
use App\Content\Models\ArticleDraft;
use App\Content\Models\TenantContentSetting;
use App\Content\Services\ArticleBlockPresenter;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Die Key-Facts-Tabelle als SVG-Infografik (#16).
 *
 * Server-seitig und ohne Modellaufruf: die Zeilen stehen bereits belegt in
 * article_drafts.key_facts_json (der HtmlAssembler baut sie aus den
 * Faktenschnipseln), die Grafik ordnet sie nur an. Damit kann in der Grafik
 * keine Zahl stehen, die nicht auch im Artikel belegt ist.
 *
 * Ausgabe ist reines SVG aus resources/views/content/infographic.blade.php —
 * kein Rasterbild, keine eingebettete Schrift, keine externe Referenz. Das
 * haelt die Datei bei wenigen Kilobyte und laesst sie in jedem Kontrastmodus
 * scharf bleiben.
 *
 * Die Farben kommen aus tenant_content_settings.brand_colors_json. Ohne
 * Pflege zieht der Renderer die Branding-Farben des Mandanten aus der
 * Central-DB, zuletzt die Vorgabe aus config('content.assets.infographic').
 */
class InfographicRenderer
{
    public function __construct(
        private readonly ArticleBlockPresenter $presenter,
        private readonly TenantBrandingService $branding,
    ) {}

    /**
     * Fertiges SVG, oder null wenn der Entwurf keine tabellarischen Fakten
     * hat — eine Infografik ohne Zahlen waere ein leerer Rahmen.
     */
    public function render(Tenant $tenant, ArticleDraft $draft, ?TenantContentSetting $settings = null): ?string
    {
        // Dieselbe Quelle wie die Tabelle im Artikel, damit Grafik und Text
        // nicht auseinanderlaufen.
        $facts = $this->presenter->keyFacts($draft);
        $rows = array_slice($facts['rows'], 0, max(1, (int) config('content.assets.infographic.max_rows', 6)));

        if ($rows === []) {
            return null;
        }

        $svg = View::make('content.infographic', [
            'title' => Str::limit((string) $draft->title, 90),
            'subtitle' => $this->subtitle($tenant, $draft),
            'rows' => $rows,
            'caption' => $facts['caption'] === null ? null : Str::limit((string) $facts['caption'], 110),
            'colors' => $this->colors($tenant, $settings),
            'width' => max(400, (int) config('content.assets.infographic.width', 1200)),
            'height' => max(300, (int) config('content.assets.infographic.height', 675)),
        ])->render();

        // Blade laesst die Einrueckung der @foreach-Schleife stehen; im SVG ist
        // sie nur Ballast. Gekuerzt wird ausschliesslich am Zeilenanfang, damit
        // kein Textinhalt angefasst wird.
        $svg = preg_replace('/^[ \t]+/m', '', $svg) ?? $svg;

        return trim(preg_replace('/\n{2,}/', "\n", $svg) ?? $svg);
    }

    private function subtitle(Tenant $tenant, ArticleDraft $draft): string
    {
        $region = (string) $draft->region_scope === 'national' || $draft->region_code === null
            ? 'bundesweit'
            : (string) (config("content.regions.states.{$draft->region_code}.name") ?? $draft->region_code);

        return trim((string) $tenant->name).' · '.$region;
    }

    /**
     * Farbpalette der Grafik: gepflegte Markenfarben, sonst das Branding des
     * Mandanten, sonst die Vorgabe.
     *
     * @return array<string, string>
     */
    public function colors(Tenant $tenant, ?TenantContentSetting $settings = null): array
    {
        $defaults = (array) config('content.assets.infographic.default_colors', []);
        $settings ??= TenantContentSetting::current();
        $stored = (array) ($settings->brand_colors_json ?? []);

        $branding = [
            'primary' => $this->branding->get($tenant, TenantConfigConstants::PRIMARY_COLOR),
            'secondary' => $this->branding->get($tenant, TenantConfigConstants::SECONDARY_COLOR),
            'accent' => $this->branding->get($tenant, TenantConfigConstants::ACCENT_COLOR),
        ];

        $colors = [];

        foreach ($defaults as $key => $default) {
            $colors[$key] = $this->hex($stored[$key] ?? null)
                ?? $this->hex($branding[$key] ?? null)
                ?? (string) $default;
        }

        return $colors;
    }

    /**
     * Nur eine gueltige Hex-Farbe darf ins SVG; alles andere waere eine
     * Einschleusung ueber ein Einstellungsfeld.
     */
    private function hex(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1 ? $value : null;
    }
}
