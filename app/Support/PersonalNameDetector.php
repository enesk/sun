<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Tenancy\TenantTerms;

/**
 * Erkennt Selbsteintragungen, bei denen kein Betrieb, sondern eine
 * Privatperson im Feld "Name" steht (#15).
 *
 * Anlass: alle Selbsteintragungen auf fahrschulefinder.de der letzten 90 Tage
 * sind Fahrschueler, die ihren eigenen Namen als Firmennamen eingetragen
 * haben ("Lara Hartig", "Vilk Jaquelin", ...) — `companies.name` ist dort
 * identisch zu `users.name`. Die Erkennung dient allein einer Rueckfrage im
 * Formular, nie einer Ablehnung: ein Betrieb darf nach seinem Inhaber heissen
 * ("Hartig Fahrschule" faellt durch das Branchenwort heraus, "Lara Hartig"
 * nicht).
 *
 * Merkmale eines Personennamens, alle muessen zutreffen:
 *  - zwei oder drei Woerter (ein Wort ist zu oft eine Marke, vier und mehr
 *    sind fast immer ein Firmenname),
 *  - nur Buchstaben, Bindestrich und Apostroph — kein "&", keine Ziffer,
 *    kein Punkt (der steckt in Rechtsformen wie "e.K." und "Inh."),
 *  - kein Wort, das eine Rechtsform, ein Firmenwort oder ein Branchenbegriff
 *    des Portals ist (tenant('terms'), siehe TenantTerms).
 */
final class PersonalNameDetector
{
    /**
     * Rechtsformen und branchenneutrale Firmenwoerter, in der Normalform von
     * self::normalize() (klein, Umlaute als ae/oe/ue/ss).
     *
     * @var list<string>
     */
    private const BUSINESS_WORDS = [
        // Rechtsformen
        'gmbh', 'ug', 'ag', 'kg', 'ohg', 'gbr', 'mbh', 'eg', 'ev', 'ek', 'se',
        'kgaa', 'ltd', 'llc', 'bv', 'co', 'cie', 'partg', 'ggmbh', 'inh',
        'inhaber', 'inhaberin', 'nachfolger', 'nachf', 'soehne', 'sohn',
        'gebrueder', 'gebr', 'und', 'gruppe', 'group', 'holding',
        // Firmen- und Branchenwoerter
        'betrieb', 'betriebe', 'fachbetrieb', 'meisterbetrieb', 'familienbetrieb',
        'firma', 'service', 'services', 'team', 'partner', 'profi', 'profis',
        'meister', 'zentrum', 'center', 'haus', 'hof', 'werk', 'werke',
        'werkstatt', 'schule', 'praxis', 'klinik', 'apotheke', 'studio',
        'salon', 'atelier', 'agentur', 'kanzlei', 'institut', 'akademie',
        'buero', 'laden', 'markt', 'shop', 'garage', 'autohaus', 'technik',
        'bau', 'handel', 'montage', 'dienst', 'dienste', 'notdienst',
        'anlagen', 'systeme', 'solutions', 'consulting', 'manufaktur',
        'genossenschaft', 'verein', 'stiftung', 'gesellschaft',
        // haeufige Branchenwoerter, die als zweites Wort einen Ort tragen
        // ("Taxi Koeln") und sonst als Personenname gelesen wuerden
        'taxi', 'pizzeria', 'restaurant', 'cafe', 'hotel', 'pension', 'kiosk',
        'immobilien', 'versicherung', 'versicherungen', 'reisen', 'reisebuero',
        'friseur', 'friseure', 'kosmetik', 'fitness', 'sport', 'garten',
        'elektro', 'sanitaer', 'heizung', 'maler', 'fliesen', 'dach', 'kfz',
        'auto', 'autos', 'reifen', 'glas', 'metall', 'holz', 'stahl', 'kueche',
        'kuechen', 'moebel', 'fahrzeuge', 'fahrschule', 'fahrschulen',
    ];

    /**
     * Wortenden, die ein Wort zum Firmenwort machen. Nur ab vier Zeichen —
     * kurze Enden treffen zu oft Nachnamen ("Haag" auf "ag").
     *
     * @var list<string>
     */
    private const BUSINESS_SUFFIXES = [
        'technik', 'service', 'handel', 'schule', 'werkstatt', 'werke',
        'zentrum', 'center', 'studio', 'praxis', 'klinik', 'haus', 'hoefe',
        'dienst', 'dienste', 'montage', 'systeme', 'anlagen', 'meister',
        'betrieb', 'betriebe', 'bedarf', 'logistik', 'transporte', 'transport',
        'reinigung', 'sanierung', 'pflege', 'ausbau', 'innung', 'bauzentrum',
        'gmbh', 'kgaa',
        // -erei/-eria deckt Baeckerei, Metzgerei, Tischlerei, Malerei,
        // Gaertnerei, Pizzeria ab; als Nachnamensende kommt es nicht vor
        'erei', 'eria', 'theke', 'therapie',
    ];

    /**
     * Wortfuellsel in Branchenbegriffen, die als Firmenwort nichts aussagen
     * (":branche_akk" ist "einen Elektriker").
     *
     * @var list<string>
     */
    private const TERM_STOPWORDS = ['ein', 'eine', 'einen', 'der', 'die', 'das'];

    public static function looksPersonal(?string $name): bool
    {
        $words = self::words($name);

        if (count($words) < 2 || count($words) > 3) {
            return false;
        }

        foreach ($words as $word) {
            if (preg_match('/^\p{L}[\p{L}\'’\-]*$/u', $word) !== 1) {
                return false;
            }

            if (self::isBusinessWord($word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Firmenname und Personenname sind derselbe Text — das staerkste Merkmal
     * einer zweckverfehlten Eintragung, unabhaengig von der Wortform.
     */
    public static function isSamePerson(?string $company, ?string $person): bool
    {
        $company = self::normalize(implode(' ', self::words($company)));
        $person = self::normalize(implode(' ', self::words($person)));

        return $company !== '' && $company === $person;
    }

    private static function isBusinessWord(string $word): bool
    {
        $word = self::normalize($word);

        if (in_array($word, self::BUSINESS_WORDS, true)) {
            return true;
        }

        foreach (self::BUSINESS_SUFFIXES as $suffix) {
            if (str_ends_with($word, $suffix)) {
                return true;
            }
        }

        return in_array($word, self::termWords(), true);
    }

    /**
     * Einzelwoerter der Branchenbegriffe des Portals: "Fahrschule Hartig" ist
     * damit ein Firmenname, auch wenn das Wort in keiner Liste oben steht.
     *
     * @return list<string>
     */
    private static function termWords(): array
    {
        $terms = tenancy()->initialized
            ? TenantTerms::resolve((array) tenant(TenantTerms::ATTRIBUTE))
            : TenantTerms::defaults();

        $words = [];

        foreach ($terms as $term) {
            foreach (self::words($term) as $word) {
                $word = self::normalize($word);

                if ($word !== '' && ! in_array($word, self::TERM_STOPWORDS, true)) {
                    $words[] = $word;
                }
            }
        }

        return array_values(array_unique($words));
    }

    /**
     * @return list<string>
     */
    private static function words(?string $value): array
    {
        $words = preg_split('/\s+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : array_values($words);
    }

    private static function normalize(string $value): string
    {
        return strtr(mb_strtolower($value), [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'é' => 'e', 'è' => 'e', 'á' => 'a', 'à' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            '’' => '\'',
        ]);
    }
}
