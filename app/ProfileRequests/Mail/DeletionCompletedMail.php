<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;

/**
 * Bestaetigung an den Antragsteller, nachdem die Loeschpipeline gelaufen ist
 * (#8). Sie-Form, weil die Mail an einen Betrieb geht.
 *
 * Der Hinweis auf Google ist der Grund, warum es diese Mail ueberhaupt gibt:
 * die Seite ist beim Portal sofort weg (410), bei Google aber erst beim
 * naechsten Crawl. Ohne den Hinweis kommt zwei Tage spaeter die Nachfrage
 * „da steht immer noch was".
 *
 * Betreff, Message-ID, Antwortadresse und Branding kommen aus
 * {@see RequestMail} (#10).
 */
class DeletionCompletedMail extends RequestMail
{
    /**
     * @param  list<string>  $steps  Schluessel der Schritte, die etwas getan haben
     */
    public function __construct(
        ProfileRequest $request,
        ?Tenant $tenant,
        public string $companyName,
        public array $steps = [],
    ) {
        parent::__construct($request, $tenant);
    }

    protected function subjectLine(): string
    {
        return 'Ihr Eintrag wurde gelöscht';
    }

    protected function viewName(): string
    {
        return 'emails.profile-requests.deletion-completed';
    }

    protected function payload(): array
    {
        return [
            'companyName' => $this->companyName,
            'deletedKinds' => $this->deletedKinds(),
        ];
    }

    /**
     * Die geloeschten Datenarten in Worten — der Antragsteller soll lesen
     * koennen, was weg ist, ohne Schrittnamen aus dem Code zu kennen.
     *
     * @return list<string>
     */
    private function deletedKinds(): array
    {
        $labels = [
            'soft_delete_profile' => 'Ihr Firmenprofil samt Adresse, Kontaktdaten und Beschreibung',
            'remove_media' => 'Logo, Titelbild und alle Bilder der Galerie',
            'remove_reviews' => 'alle Bewertungen und Ihre Antworten darauf',
            'purge_related' => 'Öffnungszeiten, Leistungen, Referenzen, Statistiken, Stellenanzeigen und Nachweisdokumente',
            'anonymize_leads' => 'die Kontaktdaten aus allen Anfragen (die Anfragen selbst bleiben für die Abrechnung anonymisiert erhalten)',
            'register_gone_url' => 'die Profilseite — sie ist nicht mehr erreichbar',
        ];

        $kinds = [];

        foreach ($this->steps as $step) {
            if (isset($labels[$step])) {
                $kinds[] = $labels[$step];
            }
        }

        return $kinds;
    }
}
