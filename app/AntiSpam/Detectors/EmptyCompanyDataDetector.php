<?php

declare(strict_types=1);

namespace App\AntiSpam\Detectors;

use App\AntiSpam\Support\BotCandidate;

/**
 * Leere Firmendaten (#10, Teil von R5).
 *
 * Ein Eintrag, der ausser einem Namen nichts traegt, ist kein Betrieb: kein
 * Beschreibungstext, keine Strasse, keine Rufnummer, keine Adresse, keine
 * Webseite. Gezaehlt werden die fuenf Felder; ab {@see self::MIN_EMPTY}
 * leeren Feldern trifft die Regel.
 *
 * Eingetragene Betriebe aus dem Places-Import sind davon nicht betroffen: der
 * Lauf bewertet nur Selbsteintragungen (`google_places_id IS NULL`, siehe
 * {@see \App\AntiSpam\SuspectedBotScanner}); die Pruefung steht hier zusaetzlich,
 * damit die Regel auch einzeln richtig antwortet.
 *
 * Gilt nur fuer Eintraege: ein Konto hat diese Felder nicht.
 */
final class EmptyCompanyDataDetector implements Detector
{
    /** Ab so vielen leeren Feldern von fuenf greift die Regel. */
    private const MIN_EMPTY = 4;

    public function code(): string
    {
        return 'empty_company_data';
    }

    public function label(): string
    {
        return __('Firmendaten praktisch leer');
    }

    public function supports(BotCandidate $candidate): bool
    {
        return $candidate->isListing() && ! $candidate->hasPlacesId;
    }

    public function matches(BotCandidate $candidate): bool
    {
        return $candidate->emptyFieldCount('description', 'street', 'tel', 'email', 'website') >= self::MIN_EMPTY;
    }
}
