<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Content\Models\TenantContentSetting;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seedet 30-80 Branchen-Keywords je Tenant in
 * `tenant_content_settings.branch_keywords_json` (#13), gruppiert nach
 * Unterthema im Format, das `TenantContext::branchKeywordGroups()` (#7)
 * erwartet.
 *
 * Aktualisiert die von `TenantContentSettingSeeder` bereits angelegte Zeile
 * je Tenant und ist idempotent: befuellt `branch_keywords_json` nur, wenn es
 * noch leer ist, damit im Content-Panel manuell gepflegte Keywords erhalten
 * bleiben.
 */
class BranchKeywordSeeder extends Seeder
{
    public function run(): void
    {
        $updated = 0;
        $skipped = 0;
        $unmatched = [];

        Tenant::query()->each(function (Tenant $tenant) use (&$updated, &$skipped, &$unmatched): void {
            $branch = BranchResolver::resolve($tenant);

            if ($branch === null) {
                $unmatched[] = $tenant->name;

                return;
            }

            $tenant->run(function () use ($branch, &$updated, &$skipped): void {
                $settings = TenantContentSetting::current();

                if (! empty($settings->branch_keywords_json)) {
                    $skipped++;

                    return;
                }

                $settings->update(['branch_keywords_json' => $this->keywordsFor($branch)]);
                $updated++;
            });
        });

        $this->command?->info("Branchen-Keywords: {$updated} Tenants befuellt, {$skipped} bereits vorhanden.");

        if ($unmatched !== []) {
            $this->command?->warn('Keine Branche erkannt fuer: '.implode(', ', $unmatched));
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function keywordsFor(string $branch): array
    {
        return match ($branch) {
            'sanitaer' => [
                'heizung' => ['heizung modernisieren', 'wärmepumpe kosten', 'gasheizung austausch', 'heizungswartung', 'hydraulischer abgleich', 'heizungsnotdienst', 'fußbodenheizung nachrüsten', 'pufferspeicher heizung', 'gas-brennwerttherme kosten', 'heizlastberechnung'],
                'bad' => ['badsanierung kosten', 'barrierefreies bad', 'bodengleiche dusche', 'gäste-wc einbauen', 'badplanung', 'wc austauschen', 'duschrinne einbauen', 'waschtisch montieren'],
                'trinkwasser' => ['legionellenprüfung', 'trinkwasserverordnung', 'wasserenthärtungsanlage', 'wasserhärte messen', 'trinkwasserqualität', 'durchlauferhitzer austauschen'],
                'notdienst' => ['heizungsnotdienst kosten', 'rohrbruch notdienst', 'sanitärnotdienst wochenende', 'abflussverstopfung notdienst', 'wasserschaden sofortmaßnahmen'],
                'installation' => ['sanitärinstallation kosten', 'rohrleitung erneuern', 'abwasserhebeanlage', 'rückstauklappe einbauen', 'zirkulationsleitung installieren'],
                'förderung' => ['förderung heizungstausch', 'bafa förderung heizung', 'wärmepumpe förderung 2026'],
            ],
            'fliesen' => [
                'bad' => ['badfliesen verlegen', 'fliesen bad kosten', 'bodengleiche dusche fliesen', 'fugensanierung bad', 'wandfliesen küche', 'rutschsichere fliesen bad', 'duschrinne fliesen einbauen'],
                'außenbereich' => ['terrassenfliesen verlegen', 'frostsichere fliesen', 'poolumrandung fliesen', 'balkonfliesen verlegen', 'fliesen außenbereich frostbeständig', 'natursteinterrasse frost'],
                'material' => ['feinsteinzeug verlegen', 'naturstein fliesen', 'großformatfliesen', 'mosaikfliesen', 'fliesenformat kalibriert', 'fliesenkleber neubau'],
                'sanierung' => ['fliesen entfernen kosten', 'überfliesen alter belag', 'fugen erneuern', 'abdichtung nassbereich', 'verbundabdichtung dusche', 'feuchtraumabdichtung keller'],
                'pflege' => ['naturstein imprägnieren', 'fugen reinigen schimmel', 'fliesen versiegeln'],
                'planung' => ['fliesenleger kosten pro quadratmeter', 'verlegemuster fliesen', 'fliesenverlegung dauer', 'fliesenleger termin sommer'],
            ],
            'elektro' => [
                'e-mobilität' => ['wallbox installieren', 'wallbox förderung', 'ladestation zuhause', 'wallbox mehrfamilienhaus', 'ladeleistung wallbox', 'ladeinfrastruktur tiefgarage'],
                'installation' => ['elektroinstallation altbau', 'sicherungskasten erneuern', 'zählerschrank austausch', 'fi-schutzschalter nachrüsten', 'unterverteilung elektro', 'leitungsschutzschalter'],
                'smart-home' => ['smart home nachrüsten', 'knx installation', 'smart home kosten', 'smart home bus system'],
                'sicherheit' => ['e-check kosten', 'blitzschutzanlage', 'elektroprüfung mietwohnung', 'rauchmelder installation', 'potentialausgleich prüfen', 'notstromaggregat hausanschluss'],
                'photovoltaik' => ['photovoltaik elektroanschluss', 'pv anlage anmelden netzbetreiber', 'wechselrichter anschließen'],
                'beleuchtung' => ['gartenbeleuchtung installation', 'außenbeleuchtung planen', 'bewegungsmelder außen', 'weihnachtsbeleuchtung sicherheit'],
                'sonstiges' => ['klimaanlage elektroanschluss', 'elektroheizung installation', 'gartenteich elektroinstallation'],
            ],
            'maler' => [
                'innen' => ['wohnung streichen kosten', 'tapezieren kosten', 'kinderzimmer streichen', 'schimmel wand behandeln', 'decke streichen kosten', 'raufasertapete entfernen'],
                'außen' => ['fassade streichen kosten', 'fassadendämmung mit anstrich', 'holz lackieren außen', 'fassade ausbessern', 'fassadenreinigung algen', 'fenster lackieren außen'],
                'material' => ['silikatfarbe', 'dispersionsfarbe', 'schadstofffreie farbe', 'reibeputz erneuern', 'lasur holz außen'],
                'gewerbe' => ['büro streichen', 'treppenhaus streichen', 'gewerbeflächen anstrich'],
                'spezial' => ['gartenzaun streichen', 'garage streichen', 'balkon lackieren', 'dachüberstand lackieren'],
                'planung' => ['maler kosten pro quadratmeter', 'malerarbeiten nach wasserschaden', 'neubau streichen'],
                'saisonal' => ['fassade streichen frühjahr', 'wohnzimmer streichen weihnachten', 'büro streichen ferien', 'treppenhaus streichen kosten'],
            ],
            'metallbau' => [
                'zaun-tor' => ['zaun montieren kosten', 'torantrieb wartung', 'schiebetor einfahrt', 'einbruchschutz metalltür', 'zaunanlage grundstücksgrenze', 'hoftor elektrifizieren'],
                'geländer' => ['geländer terrasse', 'balkongeländer sanieren', 'treppengeländer sanierung', 'edelstahlgeländer pool'],
                'treppen' => ['stahltreppe planen', 'außentreppe metall'],
                'überdachung' => ['carport metall', 'vordach montieren', 'terrassenüberdachung metall', 'fahrradunterstand metall'],
                'sicherheit' => ['rc-klasse einbruchschutz', 'kellerfenstergitter', 'schließanlage mehrfamilienhaus'],
                'sonstiges' => ['sichtschutzzaun metall', 'verzinkung rostschutz prüfen', 'toranlage einfahrt'],
                'material' => ['edelstahl metallbau v2a v4a', 'pulverbeschichtung metall', 'schweißverbindung metallbau', 'metallbau schlosserarbeiten'],
                'weitere' => ['zaun grundstücksgrenze nachbarrecht', 'geländerhöhe din vorschrift', 'toröffner nachrüsten', 'fahrradunterstand metall bauen', 'poolzaun sicherheit'],
            ],
            'geruestbau' => [
                'fassade' => ['fassadengerüst mieten', 'gerüst fassadendämmung', 'gerüst malerarbeiten', 'gerüst denkmalschutz'],
                'dach' => ['dachgerüst aufbauen', 'fanggerüst dach', 'gerüst dachrinne reinigen', 'absturzsicherung dach'],
                'genehmigung' => ['gerüst gehweg genehmigung', 'sondernutzungserlaubnis gerüst', 'gerüstprüfung befähigte person', 'gerüst haftung'],
                'miete' => ['gerüstmiete kosten', 'gerüst standzeit', 'systemgerüst mieten', 'gerüstbauer angebot vergleichen'],
                'sonderfälle' => ['gerüst enges grundstück', 'notgerüst sturmschaden', 'gerüst solarmontage', 'konsolgerüst'],
                'sicherheit' => ['gerüstkennzeichnung', 'schutzgerüst winter', 'gerüst abbau termin'],
                'technik' => ['konsolgerüst schmales grundstück', 'gerüstlast berechnen', 'rahmengerüst modulgerüst', 'gerüst wetterschutzplane'],
                'weitere' => ['gerüst kurzzeitmiete', 'fassadeninspektion mit gerüst', 'gerüst für pv installation', 'notabsicherung dach sturm', 'gerüst für außenanlagen'],
            ],
            'gartenbau' => [
                'gestaltung' => ['gartenneuanlage kosten', 'terrasse naturstein bauen', 'gartenweg pflastern', 'staudenbeet anlegen', 'terrassenüberdachung garten'],
                'pflege' => ['heckenschnitt termin', 'baumschnitt spätwinter', 'gartenpflegevertrag', 'rasen düngen', 'gartenpflege abo'],
                'wasser' => ['bewässerungsanlage garten', 'regenwassernutzung garten', 'gartenteich reinigung', 'tropfbewässerung installieren'],
                'genehmigung' => ['baumfällung genehmigung', 'heckenschnitt schonzeit', 'baumschutzsatzung'],
                'rasen' => ['rollrasen verlegen', 'rasen neu anlegen', 'rasenpflege frühling'],
                'bepflanzung' => ['sichtschutzhecke pflanzen', 'herbstbepflanzung balkon', 'weihnachtsbaum einpflanzen'],
                'sonstiges' => ['garten winterfest machen', 'gartenweg pflastern kosten', 'fahrradunterstand garten'],
                'weitere' => ['gartenteich anlegen kosten', 'pergola begrünung', 'gartengestaltung sichtschutz kombination', 'gartenpflege frühjahrsputz'],
            ],
            'hoch-tiefbau' => [
                'fundament' => ['bodenplatte kosten', 'fundament frostfrei', 'streifenfundament bauen', 'betonqualität expositionsklasse'],
                'erdarbeiten' => ['bodengutachten kosten', 'baugrube sichern', 'erdaushub kosten', 'baugrundklasse prüfen'],
                'abbruch' => ['gebäude abriss kosten', 'abbrucharbeiten genehmigung'],
                'außenanlagen' => ['einfahrt pflastern kosten', 'kanalanschluss neubau', 'entwässerungsplanung grundstück', 'straßenbau kosten'],
                'rohbau' => ['rohbauabnahme ablauf', 'setzrisse neubau ursache', 'bauzeitenplan rohbau'],
                'sonstiges' => ['drainage grundstück', 'baustelle absichern winter', 'winterbau beton', 'carport fundament'],
                'weitere' => ['kellerabdichtung nachträglich', 'baugrubenverbau', 'wegebau grundstück', 'bauleistung angebot vergleichen'],
                'zusatz' => ['erdarbeiten angebot vergleichen', 'grundstücksentwässerung regensaison', 'rissbildung neubau winter', 'fundament angebot einholen', 'baustellensicherheit winter', 'haftung rohbaumängel'],
            ],
            'solar-pv' => [
                'anlage' => ['photovoltaik installieren kosten', 'solaranlage dachfläche berechnen', 'photovoltaik neubau planen', 'photovoltaik gewerbedach'],
                'speicher' => ['batteriespeicher kosten', 'stromspeicher nachrüsten', 'notstrom photovoltaik'],
                'förderung' => ['photovoltaik förderung', 'einspeisevergütung aktuell', 'kfw förderung solaranlage', 'photovoltaikpflicht neubau'],
                'anmeldung' => ['marktstammdatenregister anmeldung', 'balkonkraftwerk anmelden', 'pv anlage netzbetreiber anmelden'],
                'balkonkraftwerk' => ['steckersolargerät regeln', 'balkonkraftwerk leistung', 'balkonkraftwerk kosten'],
                'betrieb' => ['photovoltaik wartung reinigung', 'wechselrichter austausch', 'photovoltaik amortisation'],
                'sonstiges' => ['solarcarport', 'volleinspeisung eigenverbrauch', 'photovoltaik denkmalschutz', 'verschattungsanalyse pv'],
                'weitere' => ['photovoltaik sturmschaden prüfen', 'notstromfunktion speicher', 'photovoltaik angebote vergleichen', 'einspeisevergütung jahreswechsel'],
                'zusatz' => ['photovoltaikpflicht bundesland', 'wechselrichter defekt austausch', 'pv anlage rentabilität berechnen', 'photovoltaik gewerbe steuer'],
            ],
            'energieberatung' => [
                'beratung' => ['energieberatung kosten', 'vor-ort-energieberatung', 'energieberater finden', 'energieberatung fördermittel'],
                'sanierung' => ['individueller sanierungsfahrplan', 'energetische sanierung kosten', 'dämmung förderung', 'dämmstandard dachausbau'],
                'förderung' => ['bafa förderung', 'kfw förderkredit sanierung', 'förderprogramme heizungstausch', 'förderprogramme 2026 überblick'],
                'nachweis' => ['energieausweis kosten', 'energieausweis pflicht', 'blower door test', 'thermografie hausbild'],
                'technik' => ['heizlastberechnung kosten', 'u-wert fenster prüfen', 'sommerlicher wärmeschutz'],
                'sonstiges' => ['geg beratungspflicht', 'wärme-contracting erklärt', 'energieeffizienzklasse verbessern'],
                'weitere' => ['energieberatung mehrfamilienhaus', 'fenstertausch förderung', 'förderprogramme dämmung aktuell', 'energieberatung heizungswahl'],
                'zusatz' => ['energieausweis vermietung pflicht', 'sanierungsfahrplan kosten', 'kfw förderung aktuell prüfen', 'dämmung fassade förderung'],
            ],
            'kfz' => [
                'wartung' => ['inspektion kosten auto', 'ölwechsel intervall', 'bremsenservice kosten', 'klimaanlage service auto', 'batteriecheck winter'],
                'reifen' => ['reifenwechsel termin', 'reifeneinlagerung service', 'achsvermessung kosten'],
                'prüfung' => ['hauptuntersuchung termin', 'abgasuntersuchung kosten', 'fehlerdiagnose obd auslesen'],
                'unfall' => ['unfallreparatur werkstatt', 'kfz gutachten nach unfall', 'werkstatt nach unfall wählen'],
                'e-auto' => ['e-auto wartung hochvolt', 'elektroauto service kosten'],
                'saison' => ['auto winterfest machen', 'standheizung wartung', 'anhängerkupplung nachrüsten'],
                'sonstiges' => ['motorrad frühjahrscheck', 'freie werkstattwahl', 'motorkontrollleuchte diagnose'],
                'weitere' => ['abgasuntersuchung tüv kombiniert', 'ölwechsel kosten', 'werkstattgarantie prüfen', 'kfz sachverständigen einschalten'],
                'zusatz' => ['reifeneinlagerung kosten', 'unfallschaden auto reparieren', 'seriöse werkstatt erkennen', 'anhängerkupplung montage kosten'],
            ],
            'spedition' => [
                'umzug' => ['umzug planen kosten', 'halteverbotszone umzug', 'firmenumzug organisieren', 'seniorenumzug service', 'studentenumzug günstig'],
                'transport' => ['sammelgut spedition', 'komplettladung teilladung', 'expressversand kosten', 'möbellift mieten'],
                'international' => ['zollabwicklung spedition', 'auslandsumzug organisieren'],
                'lager' => ['lagerlogistik kosten', 'kommissionierung lager'],
                'sicherheit' => ['umzugsversicherung transportschaden', 'gefahrguttransport adr', 'lenkzeiten tachograph'],
                'sonstiges' => ['klaviertransport kosten', 'umzugskartons bestellen', 'betriebsumzug ohne stillstand'],
                'weitere' => ['frühbucherangebot umzug', 'lieferengpass jahreswechsel', 'produktionsumzug logistik', 'umzug ins ausland vorbereiten'],
                'zusatz' => ['möbeltransport kosten', 'expressversand weihnachten', 'lagerlogistik saisonspitze', 'fuhrpark lenkzeiten gesetz', 'frachtbrief cmr ausfüllen', 'transportversicherung abschließen', 'kurier express paket kep'],
            ],
            'fahrschule' => [
                'führerscheinklassen' => ['führerschein klasse b kosten', 'motorradführerschein machen', 'lkw führerschein kosten', 'anhängerführerschein be', 'rollerführerschein ab 15'],
                'ausbildung' => ['begleitetes fahren ab 17', 'theorieprüfung vorbereitung', 'fahrstunden anzahl durchschnitt', 'sonderfahrten fahrschule'],
                'sonderfälle' => ['aufbauseminar punktesystem', 'mpu vorbereitung', 'führerschein umschreiben', 'praktische prüfung nicht bestanden'],
                'training' => ['fahrsicherheitstraining kosten', 'erste-hilfe-kurs führerschein'],
                'kosten' => ['führerschein kosten vergleich', 'fahrschule anmeldung kosten'],
                'sonstiges' => ['berufskraftfahrer ausbildung', 'sehtest führerschein', 'fahrprüfung winter vorbereitung'],
                'weitere' => ['nachtfahrt autobahnfahrt pflicht', 'fahrschule herbstangebot', 'fahrschule studenten anmeldung', 'führerschein wieder machen mpu'],
                'zusatz' => ['fahrschule preisvergleich', 'grundbetrag fahrschule', 'theoretische prüfung ablauf', 'fahrstunden sonderfahrten pflicht', 'unterlagen führerschein anmeldung', 'fahrerlaubnisverordnung fev regeln'],
            ],
            'tierarzt' => [
                'vorsorge' => ['welpen impfschema', 'wurmkur hund katze', 'zeckenschutz hund', 'vorsorgeuntersuchung haustier', 'fsme impfung hund'],
                'behandlung' => ['zahnsteinbehandlung hund', 'kastration kater kosten', 'notdienst tierklinik', 'seniorencheck hund katze'],
                'versicherung' => ['tierkrankenversicherung sinnvoll', 'op versicherung hund katze'],
                'notfall' => ['hitzschlag hund vorbeugen', 'giftköder hund erkennen', 'vergiftung hund erste hilfe', 'pilzvergiftung hund herbst'],
                'reise' => ['eu heimtierausweis', 'haustier im urlaub mitnehmen'],
                'sonstiges' => ['katze chippen kosten', 'kaninchen impfung myxomatose', 'streusalz hundepfoten', 'fellwechsel hund pflege'],
                'weitere' => ['erste-hilfe-set haustier', 'grillsaison gefahren haustier', 'gefahren grillen haustier'],
                'zusatz' => ['tierklinik notfall kosten', 'katzenregistrierung tasso', 'gebührenordnung tierärzte got', 'palliativbehandlung haustier', 'notfallsymptome haustier erkennen', 'anästhesierisiko haustier op'],
            ],
            'gutachter' => [
                'kfz' => ['kfz gutachten unfall kosten', 'merkantiler minderwert berechnen', 'kurzgutachten vollgutachten unterschied', 'wildunfall gutachten', 'hagelschaden gutachten auto'],
                'immobilie' => ['immobiliengutachten verkehrswert', 'verkehrswertermittlung immobilie', 'wertgutachten erbschaft'],
                'bau' => ['bausachverständiger hauskauf', 'baumängel prüfen', 'bauabnahme sachverständiger', 'feuchtigkeitsschäden gutachten'],
                'versicherung' => ['schiedsgutachten versicherung', 'gutachterkosten wer zahlt', 'beweissicherung sturmschaden'],
                'fachpersonal' => ['öffentlich bestellter sachverständiger finden'],
                'sonstiges' => ['fahrzeugbewertung verkauf', 'baumängel gewährleistung prüfen', 'starkregen schaden gutachten'],
                'weitere' => ['gutachten vor immobilienkauf', 'blechschaden wertminderung', 'kurzgutachten kosten', 'gutachten bauabnahme mängel'],
                'zusatz' => ['gutachterkosten gegenstandswert', 'unfallgutachten kostenübernahme', 'immobilienwert berechnen lassen', 'wertminderung fahrzeug nach unfall', 'gutachterstreit versicherung schlichten', 'vereidigter gutachter suchen', 'restwertermittlung fahrzeug'],
            ],
            'medizin' => [
                'vorsorge' => ['grippeimpfung termin', 'hautkrebsvorsorge termin', 'vorsorgeuntersuchung krankenkasse', 'fsme impfung', 'kinderimpfungen kita start'],
                'zahn' => ['professionelle zahnreinigung kosten', 'zahnersatz kosten zuzahlung', 'zahnärztlicher notdienst'],
                'notfall' => ['ärztlicher notdienst 116117', 'd-arzt arbeitsunfall', 'unfallarzt sturz', 'verbrennung erste hilfe'],
                'apotheke' => ['reiseapotheke zusammenstellen', 'reiseimpfung beratung', 'igel leistung hinterfragen', 'sonnenbrand behandeln apotheke'],
                'saisonal' => ['heuschnupfen apotheke beratung', 'grippewelle apotheke arztbesuch'],
                'sonstiges' => ['lebensmittelvergiftung symptome arzt', 'check-up 35 termin', 'zeckenbiss arzt'],
                'weitere' => ['zahnärztlicher notdienst wochenende', 'erkältung apotheke oder arzt', 'igel leistung zahnarzt', 'sonnenschutz hautkrebsvorsorge'],
                'zusatz' => ['reiseimpfung fernreise', 'apothekenpflicht medikamente', 'wechselwirkung arzneimittel prüfen', 'facharzttermin wartezeit', 'beipackzettel fachinformation lesen'],
            ],
            default => [],
        };
    }
}
