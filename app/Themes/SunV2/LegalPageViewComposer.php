<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Constants\TenantConfigConstants;
use App\Services\TenantBrandingService;
use App\Themes\ThemeManager;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Rechts- und Textseiten im Theme sun-v2 (Vorlage elektrikerportal-impressum.html):
 * Impressum, Datenschutz, Redaktionsprinzipien.
 *
 * Der Text bleibt der gepflegte Portaltext aus StaticPageController ($content,
 * Platzhalter schon aufgeloest). Hier wird er an den <h2> in Abschnitte mit
 * Sprungmarken zerlegt; die <h1> des Textes entfaellt (die Seite hat eine
 * eigene), ein "Stand:" vor dem ersten Abschnitt wandert in die Unterzeile.
 */
final class LegalPageViewComposer
{
    public function __construct(
        private readonly ThemeManager $themes,
        private readonly TenantBrandingService $branding,
    ) {}

    public function compose(View $view): void
    {
        if ($this->themes->active()?->slug !== HomeViewComposer::THEME) {
            return;
        }

        $document = $this->parse((string) ($view->getData()['content'] ?? ''));
        $tenant = tenant();
        $address = $tenant?->address()->first();

        $view->with('legal', [
            ...$document,
            'contact' => [
                'name' => (string) ($tenant?->getAttribute('name') ?? config('app.name')),
                'address' => array_values(array_filter([
                    $address?->address_line_1,
                    $address?->address_line_2,
                    trim(($address?->zip ?? '').' '.($address?->city ?? '')),
                    $address ? 'Deutschland' : null,
                ])),
                'email' => $tenant ? (string) $this->branding->get($tenant, TenantConfigConstants::CONTACT_EMAIL) : '',
                'phone' => $tenant ? (string) ($this->branding->get($tenant, TenantConfigConstants::CONTACT_PHONE) ?: $address?->phone) : '',
            ],
        ]);
    }

    /**
     * @return array{stand: ?string, intro: string, sections: array<int, array{id: string, title: string, html: string}>}
     */
    private function parse(string $html): array
    {
        $result = ['stand' => null, 'intro' => '', 'sections' => []];

        if (trim($html) === '') {
            return $result;
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="legal-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementById('legal-root');
        if (! $root) {
            return $result;
        }

        $current = null;
        $usedIds = [];

        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node instanceof DOMElement && strtolower($node->tagName) === 'h1') {
                continue;
            }

            if ($node instanceof DOMElement && strtolower($node->tagName) === 'h2') {
                if ($current) {
                    $result['sections'][] = $current;
                }

                // "1. Verantwortlicher" -> Titel ohne Nummer, die Nummer setzt das Inhaltsverzeichnis
                $title = trim((string) preg_replace('/^\s*\d+[.)]\s*/u', '', trim($node->textContent)));
                $id = Str::slug($title) ?: 'abschnitt';
                $id = isset($usedIds[$id]) ? $id.'-'.(++$usedIds[$id]) : $id;
                $usedIds[$id] ??= 1;

                $current = ['id' => $id, 'title' => $title, 'html' => ''];

                continue;
            }

            $markup = $this->outer($dom, $node);

            if ($current) {
                $current['html'] .= $markup;

                continue;
            }

            // Vor dem ersten Abschnitt: "Stand: Februar 2026" in die Unterzeile
            if ($result['stand'] === null && preg_match('/^\s*Stand:?\s*(.+)$/u', trim($node->textContent), $match)) {
                $result['stand'] = trim($match[1]);

                continue;
            }

            $result['intro'] .= $markup;
        }

        if ($current) {
            $result['sections'][] = $current;
        }

        $result['intro'] = trim(strip_tags($result['intro'])) === '' ? '' : $result['intro'];

        return $result;
    }

    private function outer(DOMDocument $dom, DOMNode $node): string
    {
        return (string) $dom->saveHTML($node);
    }
}
