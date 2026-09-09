<?php

namespace App\Http\Controllers\Portal;

use App\Constants\TenantConfigConstants;
use App\Http\Controllers\Controller;
use App\Services\TenantBrandingService;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    /**
     * Standardtext der Redaktionsprinzipien-Seite (Baustein D der Vorgabe
     * "KI-Transparenz und Autorschaft"). Greift, solange der Tenant keinen
     * eigenen Text hinterlegt hat.
     */
    private const EDITORIAL_PRINCIPLES_DEFAULT = <<<'HTML'
        <h2>Wie wir Themen auswählen</h2>
        <p>
            Wir beobachten, wonach Menschen in unserer Region suchen, welche Fragen im
            Verzeichnis häufig gestellt werden und welche Regeln, Fristen und Förderungen sich
            ändern. Daraus entsteht die Themenliste für unsere Ratgeber.
        </p>

        <h2>Wie ein Artikel entsteht</h2>
        <p>
            Wir recherchieren die Quellenlage zu einem Thema und lassen den Entwurf mit
            Unterstützung von KI schreiben. Jeder Entwurf durchläuft anschließend eine Prüfung:
            Stimmen die Angaben mit den Quellen überein? Ist der Text verständlich, vollständig
            und frei von Werbeversprechen? Erst danach erscheint ein Artikel.
        </p>

        <h2>Woher unsere Angaben stammen</h2>
        <p>
            Am Ende jedes Artikels finden Sie die verwendeten Quellen sowie das Datum der
            Erstellung und der letzten Prüfung. Wir verweisen bevorzugt auf amtliche und
            fachlich zuständige Stellen.
        </p>

        <h2>Wie wir Artikel aktuell halten</h2>
        <p>
            Regeln und Preise ändern sich. Wir prüfen unsere Ratgeber regelmäßig und
            aktualisieren sie, statt sie stehen zu lassen. Das Datum der letzten Prüfung steht
            in jedem Artikel.
        </p>

        <h2>Was unsere Ratgeber nicht sind</h2>
        <p>
            Unsere Artikel geben eine allgemeine Orientierung. Sie ersetzen keine Rechts-,
            Steuer- oder Fachberatung im Einzelfall. Für verbindliche Auskünfte wenden Sie sich
            bitte an eine fachlich zuständige Stelle oder an einen Anbieter aus unserem
            Verzeichnis.
        </p>

        <h2>Fehler gefunden?</h2>
        <p>
            Wenn Ihnen eine Angabe falsch oder veraltet erscheint, schreiben Sie uns. Wir prüfen
            jeden Hinweis und korrigieren, was zu korrigieren ist.
            <a href="/impressum">Kontakt</a>
        </p>

        <p>
            Verantwortlich für den redaktionellen Inhalt: [VERANTWORTLICH_NAME]. Vollständige
            Angaben im <a href="/impressum">Impressum</a>.
        </p>
        HTML;

    public function __construct(
        private TenantBrandingService $branding,
    ) {}

    public function impressum(): View
    {
        $content = $this->resolveContent(TenantConfigConstants::IMPRESSUM);

        return view('pages.impressum', compact('content'));
    }

    public function datenschutz(): View
    {
        $content = $this->resolveContent(TenantConfigConstants::DATENSCHUTZ);

        return view('pages.datenschutz', compact('content'));
    }

    /**
     * Redaktionsprinzipien "So arbeitet unsere Redaktion" (/ratgeber/redaktion).
     * Ziel des Verweises aus der Autorenbox und der publishingPrinciples-Auszeichnung.
     */
    public function editorial(): View
    {
        $content = $this->resolveContent(TenantConfigConstants::EDITORIAL_PRINCIPLES)
            ?? $this->branding->resolveLegalPlaceholders(self::EDITORIAL_PRINCIPLES_DEFAULT, tenant());

        return view('pages.blog.editorial', compact('content'));
    }

    private function resolveContent(string $key): ?string
    {
        $tenant = tenant();
        $raw = $this->branding->get($tenant, $key);

        if (! $raw) {
            return null;
        }

        return $this->branding->resolveLegalPlaceholders($raw, $tenant);
    }
}
