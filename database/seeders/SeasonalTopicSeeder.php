<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Content\Models\SeasonalTopic;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seedet den Saisonkalender (#13) je Tenant: 20-40 Themen mit Vorlauf,
 * abgeleitet aus der ueber `BranchResolver` erkannten Branche des Mandanten.
 *
 * Laeuft im Tenant-Kontext (`$tenant->run(...)`), da `seasonal_topics` eine
 * Tenant-Tabelle ist (#3). Idempotent: legt nur an, wenn fuer den Tenant noch
 * keine Eintraege existieren, damit manuell im Content-Panel gepflegte oder
 * bereits erzeugte Themen nicht ueberschrieben werden.
 *
 * Tupel-Format je Thema: [title, primary_keyword, secondary_keywords[],
 * start_month, end_month, lead_time_days, region_scope, region_code, weight].
 */
class SeasonalTopicSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;
        $skippedTenants = 0;
        $unmatched = [];

        Tenant::query()->each(function (Tenant $tenant) use (&$created, &$skippedTenants, &$unmatched): void {
            $branch = BranchResolver::resolve($tenant);

            if ($branch === null) {
                $unmatched[] = $tenant->name;

                return;
            }

            $topics = $this->topicsFor($branch);

            $tenant->run(function () use ($topics, &$created, &$skippedTenants): void {
                if (SeasonalTopic::query()->exists()) {
                    $skippedTenants++;

                    return;
                }

                foreach ($topics as $topic) {
                    [$title, $primaryKeyword, $keywords, $start, $end, $lead, $scope, $region, $weight] = $topic;

                    SeasonalTopic::create([
                        'title' => $title,
                        'primary_keyword' => $primaryKeyword,
                        'keywords_json' => $keywords,
                        'start_month' => $start,
                        'end_month' => $end,
                        'lead_time_days' => $lead,
                        'region_scope' => $scope,
                        'region_code' => $region,
                        'weight' => $weight,
                        'is_active' => true,
                    ]);

                    $created++;
                }
            });
        });

        $this->command?->info("Saisonthemen: {$created} angelegt, {$skippedTenants} Tenants bereits befuellt.");

        if ($unmatched !== []) {
            $this->command?->warn('Keine Branche erkannt fuer: '.implode(', ', $unmatched));
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: array<int, string>, 3: int, 4: int, 5: int, 6: string, 7: ?string, 8: float}>
     */
    private function topicsFor(string $branch): array
    {
        return match ($branch) {
            'sanitaer' => [
                ['Heizungscheck vor dem Winter', 'heizungscheck vor dem winter', ['heizung warten lassen'], 9, 11, 30, 'national', null, 1.0],
                ['Rohrbruch durch Frost vermeiden', 'rohrbruch frost vermeiden', ['wasserleitung einfrieren'], 11, 2, 14, 'national', null, 1.0],
                ['Legionellenprüfung im Sommer', 'legionellenprüfung warmwasser', ['trinkwasserverordnung'], 6, 8, 21, 'national', null, 0.8],
                ['Wärmepumpen-Förderung zum Jahresstart', 'wärmepumpe förderung', ['heizungstausch kosten'], 1, 3, 21, 'national', null, 1.2],
                ['Frühjahrscheck für Wasserleitungen', 'wasserleitung frühjahrscheck', ['leitungswasser prüfen'], 3, 4, 14, 'national', null, 0.7],
                ['Gartendusche und Außenwasserhahn installieren', 'außenwasserhahn installieren', ['gartendusche anschließen'], 4, 6, 21, 'national', null, 0.6],
                ['Heizungsausfall über die Feiertage', 'heizungsnotdienst weihnachten', ['heizung ausgefallen'], 11, 12, 21, 'national', null, 0.9],
                ['Badsanierung für das neue Jahr planen', 'badsanierung planen', ['neues bad kosten'], 1, 2, 30, 'national', null, 1.0],
                ['Wasserenthärter vor dem Sommer', 'wasserenthärter kaufen', ['kalkschutz wasser'], 5, 7, 21, 'national', null, 0.7],
                ['Heizkörper vor der Heizsaison entlüften', 'heizkörper entlüften', ['heizkörper gluckert'], 9, 10, 7, 'national', null, 0.6],
                ['Fußbodenheizung vor dem Winter warten', 'fußbodenheizung wartung', ['fußbodenheizung entlüften'], 9, 10, 14, 'national', null, 0.6],
                ['Frostschutz für Außenarmaturen', 'frostschutz außenarmatur', ['wasserhahn winterfest'], 10, 11, 7, 'national', null, 0.7],
                ['Fristen zum Heizungstausch nach GEG', 'gebäudeenergiegesetz heizungstausch', ['heizungsgesetz frist'], 1, 3, 30, 'national', null, 1.1],
                ['Wasserleitungen vor dem Urlaub absperren', 'wasserleitung urlaub absperren', ['wasserschaden vermeiden urlaub'], 6, 8, 7, 'national', null, 0.5],
                ['Barrierefreies Bad im Frühjahr umbauen', 'barrierefreies bad umbau', ['bodengleiche dusche'], 3, 5, 21, 'national', null, 0.8],
                ['Heizungsnotdienst bei Kälteeinbruch', 'heizungsnotdienst kälte', ['heizung fällt aus winter'], 12, 2, 7, 'national', null, 0.8],
                ['Kaminofen mit Heizung kombinieren', 'kaminofen heizungskombination', ['kaminofen nachrüsten'], 9, 11, 21, 'national', null, 0.6],
                ['Wärmepumpe im Bestandsbau nachrüsten', 'wärmepumpe bestandsbau', ['wärmepumpe altbau'], 3, 6, 30, 'national', null, 0.9],
                ['Regenwassernutzung für den Garten', 'regenwassernutzung garten', ['regenwasser sammeln'], 4, 6, 14, 'national', null, 0.5],
                ['Rohrreinigung nach dem Winter', 'rohrreinigung frühjahr', ['abfluss verstopft'], 3, 4, 7, 'national', null, 0.5],
                ['Trinkwasserqualität im Altbau prüfen', 'trinkwasserqualität altbau', ['bleileitung austauschen'], 2, 4, 21, 'national', null, 0.6],
                ['Heizungspumpe tauschen und Strom sparen', 'heizungspumpe tauschen', ['umwälzpumpe effizienzklasse'], 9, 11, 14, 'national', null, 0.6],
            ],
            'fliesen' => [
                ['Badsanierung für das neue Jahr planen', 'badsanierung planen', ['bad renovieren kosten'], 1, 2, 30, 'national', null, 1.0],
                ['Terrassenfliesen im Frühjahr verlegen', 'terrassenfliesen verlegen', ['fliesen außenbereich'], 3, 5, 21, 'national', null, 0.9],
                ['Balkonabdichtung vor dem Sommer', 'balkonabdichtung fliesen', ['balkon abdichten'], 4, 6, 21, 'national', null, 0.7],
                ['Fliesen im Außenbereich frostsicher verlegen', 'frostsichere fliesen', ['fliesen frostbeständig'], 10, 11, 14, 'national', null, 0.6],
                ['Rutschsichere Fliesen für den Poolbereich', 'rutschsichere fliesen pool', ['fliesen r-klasse'], 5, 7, 21, 'national', null, 0.6],
                ['Fugensanierung nach dem Winter', 'fugensanierung fliesen', ['fugen erneuern bad'], 3, 4, 14, 'national', null, 0.5],
                ['Naturstein pflegen im Frühjahrsputz', 'naturstein pflege', ['naturstein imprägnieren'], 3, 4, 7, 'national', null, 0.5],
                ['Barrierefreies Bad im Herbst umbauen', 'barrierefreies bad fliesen', ['bodengleiche dusche fliesen'], 9, 11, 30, 'national', null, 0.8],
                ['Feuchtraumabdichtung im Keller sanieren', 'feuchtraumabdichtung keller', ['kellerabdichtung fliesen'], 2, 4, 21, 'national', null, 0.6],
                ['Küche vor den Feiertagen renovieren', 'küchenfliesen renovierung', ['küche fliesen erneuern'], 10, 12, 30, 'national', null, 0.7],
                ['Frostschäden an Terrassenfliesen vorbeugen', 'frostschäden terrassenfliesen', ['terrasse winterfest'], 10, 11, 14, 'national', null, 0.6],
                ['Handwerkertermine vor dem Sommerurlaub sichern', 'fliesenleger termin sommer', ['fliesenleger verfügbarkeit'], 5, 7, 30, 'national', null, 0.5],
                ['Fliesenformate und Trends zum Jahresstart', 'fliesen trends', ['großformatfliesen'], 1, 2, 14, 'national', null, 0.5],
                ['Wandfliesen in der Küche erneuern', 'wandfliesen küche', ['küchenrückwand fliesen'], 2, 4, 21, 'national', null, 0.5],
                ['Poolumrandung im Frühsommer fliesen', 'poolumrandung fliesen', ['schwimmbad fliesen'], 4, 6, 21, 'national', null, 0.5],
                ['Bodengleiche Dusche ohne Duschtasse', 'bodengleiche dusche', ['dusche ebenerdig'], 3, 5, 21, 'national', null, 0.7],
                ['Fugenreinigung im Frühjahrsputz', 'fugen reinigen', ['schimmel fugen bad'], 3, 4, 7, 'national', null, 0.5],
                ['Balkonsanierung vor dem Herbstregen', 'balkon fliesen sanierung', ['balkon undicht'], 7, 9, 21, 'national', null, 0.6],
                ['Natursteinterrasse vor Frost schützen', 'natursteinterrasse frost', ['naturstein winterfest'], 10, 11, 14, 'national', null, 0.5],
                ['Badezimmertrends zum Jahreswechsel', 'badezimmer trends', ['bad modernisieren'], 12, 1, 14, 'national', null, 0.5],
                ['Fliesenkleber und Untergrund im Neubau', 'fliesenkleber neubau', ['estrich für fliesen'], 4, 6, 21, 'national', null, 0.4],
                ['Mosaikfliesen für Dusche und Bad', 'mosaikfliesen bad', ['mosaik dusche'], 2, 4, 14, 'national', null, 0.4],
            ],
            'elektro' => [
                ['Wallbox vor dem Winter installieren', 'wallbox installieren kosten', ['wallbox förderung'], 9, 11, 21, 'national', null, 1.0],
                ['Sicherungskasten vor dem Hauskauf prüfen', 'sicherungskasten prüfen', ['elektroinstallation altbau'], 1, 3, 14, 'national', null, 0.8],
                ['E-Check vor der Vermietung', 'e-check kosten', ['elektroprüfung mietwohnung'], 2, 4, 14, 'national', null, 0.6],
                ['Smart Home zu Weihnachten nachrüsten', 'smart home nachrüsten', ['smart home kosten'], 10, 12, 21, 'national', null, 0.7],
                ['Gartenbeleuchtung im Frühjahr planen', 'gartenbeleuchtung installation', ['außenbeleuchtung garten'], 3, 5, 14, 'national', null, 0.5],
                ['Blitzschutz vor der Gewittersaison', 'blitzschutzanlage prüfen', ['blitzschutz haus'], 4, 6, 21, 'national', null, 0.6],
                ['Elektroinstallation im Altbau sanieren', 'elektroinstallation sanieren', ['stromleitung erneuern altbau'], 1, 3, 30, 'national', null, 0.7],
                ['Photovoltaik-Anschluss beim Elektriker anmelden', 'photovoltaik elektroanschluss', ['pv anlage anmelden'], 4, 7, 21, 'national', null, 0.7],
                ['Weihnachtsbeleuchtung sicher anschließen', 'weihnachtsbeleuchtung sicherheit', ['außensteckdose garten'], 11, 12, 7, 'national', null, 0.5],
                ['FI-Schutzschalter nachrüsten', 'fi-schutzschalter nachrüsten', ['fehlerstromschutzschalter pflicht'], 2, 4, 14, 'national', null, 0.5],
                ['Klimaanlage elektrisch anschließen im Sommer', 'klimaanlage elektroanschluss', ['klimaanlage installation'], 5, 7, 21, 'national', null, 0.6],
                ['Wallbox-Förderung zum Jahresstart', 'wallbox förderung 2026', ['e-auto laden zuhause'], 1, 3, 14, 'national', null, 0.8],
                ['Elektrofachkraft für den Gartenteich', 'gartenteich elektroinstallation', ['teichpumpe stromanschluss'], 3, 5, 14, 'national', null, 0.4],
                ['Herbstliche Beleuchtung und Bewegungsmelder', 'bewegungsmelder außen', ['außenlicht herbst'], 9, 10, 14, 'national', null, 0.4],
                ['Elektroheizung als Übergangslösung', 'elektroheizung installation', ['heizlüfter fest installiert'], 10, 11, 7, 'national', null, 0.4],
                ['Zählerschrank modernisieren', 'zählerschrank austausch', ['zählerschrank norm'], 2, 4, 21, 'national', null, 0.5],
                ['KNX-Verkabelung im Neubau planen', 'knx installation neubau', ['smart home verkabelung'], 3, 6, 30, 'national', null, 0.5],
                ['Notstromversorgung vor dem Winter', 'notstromaggregat hausanschluss', ['stromausfall vorsorge'], 10, 12, 21, 'national', null, 0.5],
                ['Ladeinfrastruktur für die Tiefgarage', 'ladeinfrastruktur mehrfamilienhaus', ['wallbox mehrere stellplätze'], 4, 6, 30, 'national', null, 0.6],
                ['Elektroinstallation nach Wasserschaden prüfen', 'elektroinstallation wasserschaden', ['stromkreis nach überschwemmung'], 6, 8, 7, 'national', null, 0.4],
                ['Rauchmelderpflicht und Elektroinstallation', 'rauchmelder elektrisch', ['rauchmelderpflicht'], 9, 11, 14, 'national', null, 0.5],
                ['Gartenpumpe und Bewässerung elektrisch anschließen', 'gartenpumpe stromanschluss', ['bewässerung automatisch'], 4, 6, 14, 'national', null, 0.4],
            ],
            'maler' => [
                ['Fassade im Frühjahr streichen lassen', 'fassade streichen kosten', ['fassadenanstrich frühjahr'], 3, 5, 21, 'national', null, 1.0],
                ['Innenräume vor dem Winter streichen', 'wohnung streichen lassen', ['maler kosten pro quadratmeter'], 9, 11, 14, 'national', null, 0.8],
                ['Frühjahrsputz mit neuem Anstrich', 'frühjahrsputz streichen', ['wände streichen frühling'], 3, 4, 14, 'national', null, 0.6],
                ['Fassadendämmung mit Anstrich im Sommer', 'wdvs mit anstrich', ['fassadendämmung kosten'], 5, 8, 30, 'national', null, 0.7],
                ['Kinderzimmer vor der Geburt streichen', 'kinderzimmer streichen', ['schadstofffreie farbe'], 1, 12, 14, 'national', null, 0.4],
                ['Schimmel nach dem Winter behandeln', 'schimmel wand streichen', ['schimmelentfernung wand'], 2, 4, 14, 'national', null, 0.7],
                ['Gartenzaun und Holz vor dem Sommer lackieren', 'holz lackieren außen', ['gartenzaun streichen'], 4, 6, 14, 'national', null, 0.5],
                ['Fassade vor dem Winter ausbessern', 'fassade ausbessern herbst', ['fassadenschäden reparieren'], 9, 10, 14, 'national', null, 0.5],
                ['Tapezieren vor dem Umzug', 'tapezieren kosten', ['wohnung tapezieren'], 1, 12, 21, 'national', null, 0.5],
                ['Treppenhaus vor den Feiertagen streichen', 'treppenhaus streichen', ['gemeinschaftsflächen anstrich'], 10, 12, 21, 'national', null, 0.5],
                ['Balkon und Terrasse lackieren im Frühjahr', 'balkon lackieren', ['terrassengeländer streichen'], 3, 5, 14, 'national', null, 0.4],
                ['Fassadenfarbe nach RAL wählen', 'fassadenfarbe auswählen', ['farbton fassade'], 2, 4, 14, 'national', null, 0.4],
                ['Malerarbeiten nach Wasserschaden', 'wand nach wasserschaden streichen', ['feuchte wand sanieren'], 6, 8, 7, 'national', null, 0.5],
                ['Büroräume in den Sommerferien streichen', 'büro streichen ferien', ['gewerbeflächen anstrich'], 6, 8, 21, 'national', null, 0.4],
                ['Fenster und Türen im Frühjahr lackieren', 'fenster lackieren außen', ['holzfenster streichen'], 3, 5, 14, 'national', null, 0.4],
                ['Reibeputz und Kratzputz erneuern', 'reibeputz erneuern', ['fassadenputz sanieren'], 5, 7, 21, 'national', null, 0.4],
                ['Wand mit Silikatfarbe vor Schimmel schützen', 'silikatfarbe schimmel', ['schimmelschutzfarbe'], 9, 11, 14, 'national', null, 0.5],
                ['Neubau vor dem Einzug streichen lassen', 'neubau streichen', ['erstbezug malerarbeiten'], 1, 12, 21, 'national', null, 0.5],
                ['Dachüberstand und Holzverkleidung streichen', 'dachüberstand lackieren', ['holzverkleidung streichen'], 5, 7, 21, 'national', null, 0.3],
                ['Garage von innen streichen', 'garage streichen', ['garageninnenwand anstrich'], 3, 5, 14, 'national', null, 0.3],
                ['Herbstliche Fassadenreinigung vor dem Anstrich', 'fassadenreinigung herbst', ['algen fassade entfernen'], 9, 10, 14, 'national', null, 0.4],
                ['Weihnachtsvorbereitung: Wohnzimmer streichen', 'wohnzimmer streichen weihnachten', ['festliches wohnzimmer'], 10, 11, 14, 'national', null, 0.3],
            ],
            'metallbau' => [
                ['Zaun im Frühjahr montieren lassen', 'zaun montieren kosten', ['gartenzaun metall'], 3, 5, 21, 'national', null, 0.9],
                ['Torantrieb vor dem Winter warten', 'torantrieb wartung', ['elektrisches tor prüfen'], 9, 11, 14, 'national', null, 0.6],
                ['Einbruchschutz vor der dunklen Jahreszeit', 'einbruchschutz metalltür', ['rc-klasse einbruchschutz'], 9, 11, 21, 'national', null, 0.9],
                ['Geländer für die Terrasse im Frühjahr', 'geländer terrasse', ['absturzsicherung balkon'], 3, 5, 14, 'national', null, 0.6],
                ['Carport vor dem Winter aufstellen', 'carport metall aufstellen', ['carport kosten'], 8, 10, 30, 'national', null, 0.6],
                ['Stahltreppe für den Neubau planen', 'stahltreppe planen', ['außentreppe metall'], 2, 4, 30, 'national', null, 0.5],
                ['Verzinkung und Rostschutz im Frühjahr prüfen', 'verzinkung rostschutz', ['metallzaun rost'], 3, 5, 14, 'national', null, 0.5],
                ['Schiebetor für die Einfahrt', 'schiebetor einfahrt', ['toranlage einfahrt'], 4, 6, 21, 'national', null, 0.5],
                ['Vordach vor dem Herbstregen montieren', 'vordach metall montieren', ['hauseingang überdachung'], 7, 9, 21, 'national', null, 0.4],
                ['Balkongeländer sanieren im Frühjahr', 'balkongeländer sanieren', ['geländer rost entfernen'], 3, 5, 14, 'national', null, 0.4],
                ['Schließanlage fürs Mehrfamilienhaus', 'schließanlage mehrfamilienhaus', ['schließzylinder austausch'], 1, 3, 21, 'national', null, 0.4],
                ['Rolltor für die Garage nachrüsten', 'rolltor garage', ['garagentor elektrisch'], 9, 11, 21, 'national', null, 0.5],
                ['Edelstahlgeländer für den Pool', 'edelstahlgeländer pool', ['poolzaun sicherheit'], 4, 6, 21, 'national', null, 0.4],
                ['Winterdienst für Toranlagen sicherstellen', 'torantrieb winterfest', ['tor vereist'], 10, 12, 14, 'national', null, 0.4],
                ['Fahrradunterstand aus Metall bauen lassen', 'fahrradunterstand metall', ['unterstand fahrrad garten'], 3, 5, 14, 'national', null, 0.3],
                ['Zaunanlage nach Grundstücksgrenze prüfen', 'zaun grundstücksgrenze', ['nachbarrecht zaun'], 3, 5, 14, 'national', null, 0.5],
                ['Treppengeländer nach DIN sanieren', 'treppengeländer sanierung', ['geländerhöhe norm'], 2, 4, 21, 'national', null, 0.4],
                ['Sichtschutzzaun aus Metall im Sommer', 'sichtschutzzaun metall', ['sichtschutz garten'], 4, 6, 14, 'national', null, 0.4],
                ['Hoftor elektrifizieren', 'hoftor elektrisch nachrüsten', ['toröffner nachrüsten'], 5, 7, 21, 'national', null, 0.4],
                ['Kellerfenstergitter gegen Einbruch', 'kellerfenstergitter einbruchschutz', ['fenstergitter metall'], 9, 11, 14, 'national', null, 0.5],
                ['Metallüberdachung für die Terrasse', 'terrassenüberdachung metall', ['pergola metall'], 4, 6, 21, 'national', null, 0.4],
            ],
            'geruestbau' => [
                ['Fassadengerüst für die Frühjahrssanierung', 'fassadengerüst mieten', ['gerüst kosten pro quadratmeter'], 3, 5, 21, 'national', null, 0.9],
                ['Dachgerüst vor der Sturmsaison', 'dachgerüst aufbauen', ['gerüst für dacharbeiten'], 8, 10, 21, 'national', null, 0.7],
                ['Gerüst für die Weihnachtsbeleuchtung am Haus', 'gerüst weihnachtsbeleuchtung', ['gerüst kurzzeitmiete'], 10, 11, 14, 'national', null, 0.3],
                ['Gerüstprüfung nach dem Winter', 'gerüstprüfung befähigte person', ['standsicherheit gerüst'], 2, 4, 14, 'national', null, 0.5],
                ['Fanggerüst für Dacharbeiten im Sommer', 'fanggerüst dach', ['absturzsicherung dach'], 5, 7, 21, 'national', null, 0.5],
                ['Gerüst für die Fassadendämmung planen', 'gerüst fassadendämmung', ['wdvs gerüst'], 4, 6, 30, 'national', null, 0.7],
                ['Sondernutzung Gehweg für Gerüst beantragen', 'gerüst gehweg genehmigung', ['sondernutzungserlaubnis gerüst'], 2, 4, 30, 'national', null, 0.6],
                ['Gerüststandzeit richtig kalkulieren', 'gerüst standzeit kosten', ['gerüstmiete dauer'], 3, 5, 14, 'national', null, 0.4],
                ['Notgerüst nach Sturmschaden', 'gerüst sturmschaden', ['dach notabsicherung'], 9, 11, 7, 'national', null, 0.4],
                ['Gerüst für Malerarbeiten im Frühjahr', 'gerüst malerarbeiten', ['fassadengerüst maler'], 3, 5, 21, 'national', null, 0.6],
                ['Konsolgerüst für schmale Grundstücke', 'konsolgerüst', ['gerüst enges grundstück'], 4, 6, 21, 'national', null, 0.3],
                ['Gerüstabbau nach Bauabschluss', 'gerüst abbau termin', ['gerüst zurückgeben'], 6, 8, 14, 'national', null, 0.3],
                ['Systemgerüst für den Neubau mieten', 'systemgerüst mieten', ['rahmengerüst modulgerüst'], 3, 6, 21, 'national', null, 0.5],
                ['Gerüstkennzeichnung und Haftungsfragen', 'gerüstschild kennzeichnung', ['gerüst haftung'], 2, 4, 14, 'national', null, 0.4],
                ['Winterbaustelle mit Schutzgerüst absichern', 'schutzgerüst winter', ['gerüst wetterschutz'], 10, 12, 21, 'national', null, 0.4],
                ['Gerüstlast für Solarmontage einplanen', 'gerüst solarmontage', ['gerüst pv installation'], 4, 6, 21, 'national', null, 0.5],
                ['Gerüstmiete im Handwerkervergleich', 'gerüstmiete vergleich', ['gerüstbauer angebot'], 3, 5, 21, 'national', null, 0.4],
                ['Absturzsicherung bei Dachrinnenarbeiten', 'gerüst dachrinne reinigen', ['dachrinnenreinigung gerüst'], 9, 11, 14, 'national', null, 0.3],
                ['Gerüst für die Denkmalsanierung', 'gerüst denkmalschutz', ['fassadensanierung altbau gerüst'], 3, 6, 30, 'national', null, 0.3],
                ['Frühjahrsinspektion an der Fassade mit Gerüst', 'fassadeninspektion gerüst', ['fassadenschäden erkennen'], 3, 4, 14, 'national', null, 0.4],
            ],
            'gartenbau' => [
                ['Gartenneuanlage im Frühjahr planen', 'gartenneuanlage kosten', ['garten planen lassen'], 2, 4, 30, 'national', null, 1.0],
                ['Heckenschnitt vor der Schonzeit', 'heckenschnitt termin', ['heckenschnitt schonzeit'], 1, 2, 14, 'national', null, 0.9],
                ['Terrasse aus Naturstein im Frühsommer', 'terrasse naturstein bauen', ['terrasse pflastern kosten'], 4, 6, 30, 'national', null, 0.8],
                ['Rollrasen verlegen im Frühjahr', 'rollrasen verlegen', ['rasen neu anlegen'], 3, 5, 21, 'national', null, 0.7],
                ['Bewässerungsanlage vor dem Sommer', 'bewässerungsanlage garten', ['tropfbewässerung installieren'], 3, 5, 21, 'national', null, 0.6],
                ['Baumfällung im Winterhalbjahr', 'baumfällung genehmigung', ['baum fällen lassen'], 10, 2, 21, 'national', null, 0.7],
                ['Herbstlaub und Gartenpflege im Abo', 'gartenpflege herbst', ['laub entsorgen garten'], 9, 11, 14, 'national', null, 0.5],
                ['Staudenbeet im Frühjahr anlegen', 'staudenbeet anlegen', ['gartenbeet gestalten'], 3, 4, 14, 'national', null, 0.4],
                ['Sichtschutzhecke pflanzen im Herbst', 'sichtschutzhecke pflanzen', ['hecke als sichtschutz'], 9, 10, 21, 'national', null, 0.5],
                ['Gartenweg pflastern vor dem Winter', 'gartenweg pflastern', ['gehweg garten anlegen'], 8, 10, 21, 'national', null, 0.5],
                ['Rasen düngen im Frühjahr', 'rasen düngen frühjahr', ['rasenpflege frühling'], 3, 4, 7, 'national', null, 0.5,
                ],
                ['Wintervorbereitung für den Garten', 'garten winterfest machen', ['pflanzen winterschutz'], 10, 11, 14, 'national', null, 0.6],
                ['Baumschnitt im Spätwinter', 'baumschnitt spätwinter', ['obstbaumschnitt termin'], 1, 3, 14, 'national', null, 0.6],
                ['Gartenteich im Frühjahr reinigen', 'gartenteich reinigung', ['teich frühjahrsputz'], 3, 4, 14, 'national', null, 0.4],
                ['Terrassenüberdachung mit Begrünung', 'pergola begrünung', ['terrassenüberdachung garten'], 4, 6, 21, 'national', null, 0.4],
                ['Gartenpflegevertrag vor der Saison abschließen', 'gartenpflegevertrag', ['gartenpflege abo'], 2, 4, 21, 'national', null, 0.5],
                ['Regenwassernutzung im Garten einrichten', 'regenwassertonne garten', ['regenwasser garten nutzen'], 3, 5, 14, 'national', null, 0.4],
                ['Herbstbepflanzung für Balkon und Beet', 'herbstbepflanzung', ['balkonpflanzen herbst'], 9, 10, 14, 'national', null, 0.4],
                ['Gartengestaltung mit Sichtschutzzaun kombinieren', 'sichtschutz garten kombination', ['zaun und hecke'], 4, 6, 14, 'national', null, 0.3],
                ['Frühlingsputz im Garten nach dem Winter', 'gartenpflege frühjahrsputz', ['garten aufräumen frühling'], 3, 4, 7, 'national', null, 0.5],
                ['Weihnachtsbaum im Garten pflanzen', 'weihnachtsbaum einpflanzen', ['tannenbaum mit wurzelballen'], 12, 1, 7, 'national', null, 0.2],
            ],
            'hoch-tiefbau' => [
                ['Bodenplatte im Frühjahr betonieren', 'bodenplatte kosten', ['fundament haus bauen'], 3, 5, 30, 'national', null, 0.9],
                ['Bodengutachten vor dem Hausbau', 'bodengutachten kosten', ['baugrund prüfen'], 1, 12, 30, 'national', null, 0.7],
                ['Abbrucharbeiten vor dem Neubau planen', 'abbruch gebäude kosten', ['haus abreißen lassen'], 2, 4, 30, 'national', null, 0.6],
                ['Kanalanschluss für den Neubau', 'kanalanschluss neubau', ['grundleitung verlegen'], 3, 6, 30, 'national', null, 0.5],
                ['Frostfreie Gründung vor dem Winter fertigstellen', 'frostfreie gründung', ['fundament frost'], 9, 10, 21, 'national', null, 0.5],
                ['Einfahrt und Zufahrt pflastern im Sommer', 'einfahrt pflastern kosten', ['grundstückszufahrt bauen'], 5, 7, 21, 'national', null, 0.5],
                ['Rohbauabnahme vor dem Innenausbau', 'rohbauabnahme ablauf', ['rohbau prüfen mängel'], 1, 12, 14, 'national', null, 0.4],
                ['Baugrubensicherung im Frühjahr', 'baugrubenverbau', ['baugrube sichern'], 3, 5, 21, 'national', null, 0.4],
                ['Straßenbauarbeiten vor dem Winter abschließen', 'straßenbau kosten', ['wegebau grundstück'], 8, 10, 21, 'national', null, 0.4],
                ['Kellerabdichtung nach Starkregen', 'kellerabdichtung nachträglich', ['keller feucht sanieren'], 6, 8, 21, 'national', null, 0.6],
                ['Entwässerungsplanung fürs Grundstück', 'entwässerungsplanung grundstück', ['regenwasser ableiten'], 3, 5, 21, 'national', null, 0.4],
                ['Betonqualität und Expositionsklassen erklärt', 'betonqualität expositionsklasse', ['beton für fundament'], 1, 12, 14, 'national', null, 0.3],
                ['Frühjahrsstart für Erdarbeiten', 'erdarbeiten frühjahr', ['baugrund aushub'], 3, 4, 21, 'national', null, 0.5],
                ['Winterbauweise bei Frost', 'winterbau beton', ['betonieren bei frost'], 11, 2, 14, 'national', null, 0.3],
                ['Carport-Fundament vor dem Herbst', 'carport fundament', ['fundament carport bauen'], 7, 9, 21, 'national', null, 0.3],
                ['Baustellenabsicherung im Winter', 'baustelle absichern winter', ['baustellensicherheit'], 10, 12, 14, 'national', null, 0.3],
                ['Rissbildung im Neubau nach dem ersten Winter', 'risse neubau ursache', ['setzrisse haus'], 2, 4, 14, 'national', null, 0.5],
                ['Grundstücksentwässerung vor der Regensaison', 'grundstücksentwässerung', ['drainage grundstück'], 3, 5, 21, 'national', null, 0.4],
                ['Bauzeitenplan für den Rohbau erstellen', 'bauzeitenplan rohbau', ['hausbau ablaufplan'], 1, 12, 21, 'national', null, 0.3],
                ['Frühjahrsangebote für Erd- und Fundamentarbeiten', 'erdarbeiten angebot vergleichen', ['fundament angebot einholen'], 2, 3, 21, 'national', null, 0.4],
            ],
            'solar-pv' => [
                ['Photovoltaik-Förderung zum Jahresstart prüfen', 'photovoltaik förderung 2026', ['pv förderung aktuell'], 1, 3, 21, 'national', null, 1.2],
                ['Photovoltaikanlage vor dem Sommer installieren', 'photovoltaik installieren', ['solaranlage kosten'], 3, 6, 30, 'national', null, 1.1],
                ['Balkonkraftwerk zum Frühjahr anmelden', 'balkonkraftwerk anmelden', ['steckersolargerät regeln'], 2, 4, 14, 'national', null, 1.0],
                ['Batteriespeicher vor dem Winter nachrüsten', 'batteriespeicher nachrüsten', ['stromspeicher kosten'], 9, 11, 21, 'national', null, 0.9],
                ['PV-Anlage und Wallbox kombinieren', 'pv anlage wallbox kombination', ['solarstrom fürs auto'], 4, 6, 21, 'national', null, 0.7],
                ['Einspeisevergütung zum Jahreswechsel', 'einspeisevergütung aktuell', ['eeg vergütung höhe'], 12, 1, 14, 'national', null, 0.8],
                ['Verschattungsanalyse vor der Installation', 'verschattungsanalyse pv', ['solaranlage ertrag prüfen'], 2, 4, 21, 'national', null, 0.5],
                ['Photovoltaik auf dem Gewerbedach im Sommer', 'photovoltaik gewerbedach', ['gewerbe solaranlage'], 5, 7, 30, 'national', null, 0.5,
                ],
                ['Wartung der Photovoltaikanlage vor dem Winter', 'photovoltaik wartung', ['pv anlage reinigen'], 9, 10, 14, 'national', null, 0.5],
                ['Marktstammdatenregister-Anmeldung erklärt', 'marktstammdatenregister anmeldung', ['pv anlage anmelden pflicht'], 1, 12, 14, 'national', null, 0.6],
                ['Volleinspeisung oder Eigenverbrauch wählen', 'volleinspeisung eigenverbrauch', ['pv anlage betriebsmodell'], 2, 4, 14, 'national', null, 0.6],
                ['Solaranlage nach Sturmschäden prüfen', 'solaranlage sturmschaden', ['pv module beschädigt'], 9, 11, 7, 'national', null, 0.3],
                ['Photovoltaik im Neubau von Anfang an einplanen', 'photovoltaik neubau planen', ['solaranlage hausbau'], 3, 6, 30, 'national', null, 0.6],
                ['Amortisationszeit einer PV-Anlage berechnen', 'photovoltaik amortisation', ['pv anlage rentabilität'], 1, 3, 14, 'national', null, 0.6],
                ['Solarcarport für Auto und Stromproduktion', 'solarcarport', ['carport mit pv'], 4, 6, 21, 'national', null, 0.4],
                ['Wechselrichter tauschen nach Garantieablauf', 'wechselrichter austausch', ['pv anlage wechselrichter defekt'], 2, 4, 14, 'national', null, 0.4],
                ['Photovoltaik und Denkmalschutz', 'photovoltaik denkmalschutz', ['solaranlage altbau genehmigung'], 3, 5, 21, 'national', null, 0.3],
                ['Notstrom über die Photovoltaikanlage', 'pv anlage notstromfunktion', ['solarspeicher notstrom'], 9, 11, 14, 'national', null, 0.4],
                ['Frühjahrsangebote für Solaranlagen vergleichen', 'solaranlage angebote vergleichen', ['photovoltaik angebot einholen'], 2, 3, 21, 'national', null, 0.5],
                ['Photovoltaik-Pflicht bei Neubauten', 'photovoltaikpflicht neubau', ['solarpflicht bundesland'], 1, 3, 21, 'national', null, 0.6],
            ],
            'energieberatung' => [
                ['Energieberatung zum Jahresstart nutzen', 'energieberatung kosten förderung', ['energieberater finden'], 1, 3, 21, 'national', null, 1.1],
                ['Sanierungsfahrplan vor der Heizsaison erstellen', 'individueller sanierungsfahrplan', ['isfp kosten'], 2, 4, 30, 'national', null, 1.0],
                ['Energieausweis vor dem Immobilienverkauf', 'energieausweis pflicht verkauf', ['energieausweis kosten'], 1, 12, 21, 'national', null, 0.9],
                ['Förderung für Dämmmaßnahmen im Frühjahr', 'dämmung förderung', ['bafa förderung dämmung'], 2, 4, 21, 'national', null, 1.0],
                ['Heizlastberechnung vor dem Heizungstausch', 'heizlastberechnung kosten', ['heizlast berechnen lassen'], 1, 3, 21, 'national', null, 0.7],
                ['Thermografie im Winter zur Wärmeverlustanalyse', 'thermografie hausbild', ['wärmebild haus'], 12, 2, 14, 'national', null, 0.5],
                ['Blower-Door-Test nach der Sanierung', 'blower door test kosten', ['luftdichtheitsprüfung haus'], 3, 5, 14, 'national', null, 0.4],
                ['KfW-Förderkredit zum Jahreswechsel prüfen', 'kfw förderkredit sanierung', ['kfw förderung aktuell'], 12, 2, 14, 'national', null, 0.9],
                ['Energieeffizienzklasse verbessern vor dem Verkauf', 'energieeffizienzklasse verbessern', ['haus energetisch aufwerten'], 2, 4, 21, 'national', null, 0.6],
                ['Contracting als Alternative zur eigenen Heizung', 'wärme-contracting erklärt', ['heizung mieten contracting'], 9, 11, 21, 'national', null, 0.4],
                ['Vor-Ort-Beratung für Fördermittel beantragen', 'vor-ort-energieberatung', ['energieberatung fördermittel'], 1, 3, 21, 'national', null, 0.6],
                ['U-Wert der Fenster vor dem Austausch prüfen', 'u-wert fenster prüfen', ['fenster austausch dämmwert'], 2, 4, 14, 'national', null, 0.4],
                ['Sommerlicher Wärmeschutz beraten lassen', 'sommerlicher wärmeschutz', ['haus vor hitze schützen'], 5, 7, 21, 'national', null, 0.4],
                ['Gebäudeenergiegesetz und Beratungspflicht', 'geg beratungspflicht', ['energieberatung pflicht'], 1, 3, 21, 'national', null, 0.6],
                ['Förderung für Fenstertausch im Herbst', 'fenstertausch förderung', ['neue fenster förderung'], 9, 11, 21, 'national', null, 0.7],
                ['Energieberatung für Mehrfamilienhäuser', 'energieberatung mehrfamilienhaus', ['sanierungsfahrplan mfh'], 2, 4, 30, 'national', null, 0.5],
                ['Dämmstandard beim Dachausbau', 'dämmstandard dachausbau', ['dachdämmung förderung'], 3, 5, 21, 'national', null, 0.5],
                ['Energieausweis-Pflicht bei Vermietung', 'energieausweis vermietung pflicht', ['energieausweis anzeige pflicht'], 1, 12, 14, 'national', null, 0.6],
                ['Beratung vor dem Heizungstausch im Winter', 'energieberatung heizungstausch', ['heizungswahl beratung'], 10, 12, 21, 'national', null, 0.7],
                ['Fördermittel-Änderungen zum Jahreswechsel', 'förderprogramme änderung jahreswechsel', ['förderung 2026 überblick'], 12, 1, 14, 'national', null, 0.9],
            ],
            'kfz' => [
                ['Reifenwechsel zur Wintersaison', 'reifenwechsel winter termin', ['winterreifen wechseln kosten'], 9, 11, 14, 'national', null, 1.0],
                ['Reifenwechsel zur Sommersaison', 'reifenwechsel sommer termin', ['sommerreifen wechseln kosten'], 3, 4, 14, 'national', null, 0.9],
                ['Inspektion vor der Urlaubsfahrt', 'inspektion vor urlaub', ['auto check vor reise'], 5, 7, 14, 'national', null, 0.8],
                ['Hauptuntersuchung rechtzeitig planen', 'hauptuntersuchung termin', ['tüv kosten'], 1, 12, 21, 'national', null, 0.7],
                ['Klimaanlage vor dem Sommer warten', 'klimaanlage service auto', ['klimaanlage auto kosten'], 3, 5, 14, 'national', null, 0.6],
                ['Batteriecheck vor dem Winter', 'autobatterie prüfen winter', ['batterie schwach kälte'], 9, 11, 7, 'national', null, 0.7],
                ['Unfallinstandsetzung nach Glätteunfall', 'unfallreparatur werkstatt', ['auto unfallschaden reparieren'], 12, 2, 7, 'national', null, 0.6],
                ['Achsvermessung nach dem Winter', 'achsvermessung kosten', ['spur einstellen auto'], 3, 4, 14, 'national', null, 0.4],
                ['Reifeneinlagerung organisieren', 'reifeneinlagerung service', ['reifen einlagern kosten'], 3, 4, 7, 'national', null, 0.5],
                ['Bremsencheck vor der Wintersaison', 'bremsen prüfen winter', ['bremsenservice kosten'], 9, 10, 14, 'national', null, 0.6],
                ['E-Auto-Wartung beim Hochvoltsystem', 'e-auto wartung hochvolt', ['elektroauto service'], 1, 12, 21, 'national', null, 0.5],
                ['Fehlerdiagnose bei der Motorkontrollleuchte', 'motorkontrollleuchte diagnose', ['obd auslesen kosten'], 1, 12, 7, 'national', null, 0.4],
                ['Werkstattwahl nach einem Unfall', 'werkstatt nach unfall wählen', ['freie werkstattwahl'], 1, 12, 7, 'national', null, 0.5],
                ['Frühjahrscheck fürs Motorrad', 'motorrad frühjahrscheck', ['motorrad service saisonstart'], 2, 4, 14, 'national', null, 0.5],
                ['Abgasuntersuchung mit der Hauptuntersuchung', 'abgasuntersuchung kosten', ['au tüv kombiniert'], 1, 12, 14, 'national', null, 0.4],
                ['Standheizung vor dem Winter prüfen', 'standheizung wartung', ['standheizung nachrüsten'], 9, 10, 14, 'national', null, 0.3],
                ['Ölwechsel-Intervall richtig einhalten', 'ölwechsel intervall', ['motoröl wechseln kosten'], 1, 12, 7, 'national', null, 0.4],
                ['Auto winterfest machen', 'auto winterfest machen', ['scheibenfrostschutz'], 10, 11, 7, 'national', null, 0.6],
                ['Kfz-Sachverständigen nach Unfall einschalten', 'kfz gutachten nach unfall', ['unfallgutachten auto'], 1, 12, 7, 'national', null, 0.4],
                ['Anhängerkupplung nachrüsten vor dem Urlaub', 'anhängerkupplung nachrüsten', ['ahk montage kosten'], 4, 6, 14, 'national', null, 0.4],
            ],
            'spedition' => [
                ['Umzug im Frühjahr planen', 'umzug planen kosten', ['umzugsunternehmen angebot'], 2, 4, 30, 'national', null, 1.0],
                ['Halteverbotszone für den Umzugstag beantragen', 'halteverbotszone umzug', ['umzugsgenehmigung parken'], 2, 12, 21, 'national', null, 0.6],
                ['Firmenumzug in den Sommerferien', 'firmenumzug organisieren', ['büroumzug kosten'], 5, 7, 30, 'national', null, 0.5],
                ['Sammelgutverkehr vor Weihnachten', 'sammelgut spedition', ['palette versenden kosten'], 10, 12, 21, 'national', null, 0.5],
                ['Zollabwicklung für internationale Transporte', 'zollabwicklung spedition', ['export verzollen'], 1, 12, 21, 'national', null, 0.4],
                ['Umzugsversicherung vor dem Umzug abschließen', 'umzugsversicherung transportschaden', ['transportversicherung umzug'], 2, 4, 14, 'national', null, 0.5],
                ['Studentenumzug zum Semesterstart', 'studentenumzug günstig', ['kleiner umzug kosten'], 8, 10, 21, 'national', null, 0.5],
                ['Lagerlogistik vor dem Weihnachtsgeschäft', 'lagerlogistik saisonspitze', ['lager kommissionierung'], 9, 11, 21, 'national', null, 0.4],
                ['Gefahrguttransport nach ADR-Vorschrift', 'gefahrguttransport adr', ['gefahrgut versenden regeln'], 1, 12, 21, 'national', null, 0.3],
                ['Möbellift für die Wohnung im Obergeschoss', 'möbellift mieten', ['umzug ohne treppenhaus'], 2, 4, 14, 'national', null, 0.4],
                ['Seniorenumzug mit Begleitung organisieren', 'seniorenumzug service', ['umzug mit betreuung'], 3, 5, 21, 'national', null, 0.4],
                ['Frühbucherangebote für den Sommerumzug', 'umzug frühbucher angebot', ['umzugstermin sommer sichern'], 1, 3, 30, 'national', null, 0.5],
                ['Expressversand zur Weihnachtszeit', 'expressversand weihnachten', ['kurierdienst express kosten'], 11, 12, 14, 'national', null, 0.4],
                ['Komplettladung versus Teilladung wählen', 'komplettladung teilladung', ['frachtraum buchen'], 1, 12, 14, 'national', null, 0.3],
                ['Umzug ins Ausland vorbereiten', 'auslandsumzug organisieren', ['internationale spedition'], 3, 6, 30, 'national', null, 0.4],
                ['Lenkzeiten und Tachograph im Fuhrpark', 'lenkzeiten tachograph', ['fahrpersonalgesetz spedition'], 1, 12, 14, 'national', null, 0.3],
                ['Umzugskartons und Verpackungsmaterial planen', 'umzugskartons bestellen', ['verpackungsmaterial umzug'], 2, 4, 14, 'national', null, 0.3],
                ['Betriebsumzug mit laufendem Betrieb', 'betriebsumzug ohne stillstand', ['produktionsumzug logistik'], 4, 6, 30, 'national', null, 0.3],
                ['Klaviertransport durch Fachbetrieb', 'klaviertransport kosten', ['klavier umzug fachfirma'], 1, 12, 14, 'national', null, 0.3],
                ['Silvester-Lieferengpässe einplanen', 'lieferengpass jahreswechsel', ['spedition kapazität silvester'], 11, 12, 14, 'national', null, 0.3],
            ],
            'fahrschule' => [
                ['Führerschein zum Schulabschluss machen', 'führerschein nach abitur', ['fahrschule anmeldung sommer'], 5, 7, 21, 'national', null, 0.9],
                ['Begleitetes Fahren ab 17 anmelden', 'begleitetes fahren ab 17', ['bf17 kosten'], 1, 12, 30, 'national', null, 0.8],
                ['Theorieprüfung vor den Sommerferien bestehen', 'theorieprüfung vorbereitung', ['führerschein theorie lernen'], 4, 6, 14, 'national', null, 0.6],
                ['Motorradführerschein zum Saisonstart', 'motorradführerschein machen', ['führerschein klasse a kosten'], 2, 4, 30, 'national', null, 0.7],
                ['Führerschein-Aufbauseminar nach Punkten', 'aufbauseminar punktesystem', ['fahreignungsseminar kosten'], 1, 12, 21, 'national', null, 0.4],
                ['Anhängerführerschein vor der Urlaubssaison', 'anhängerführerschein be', ['führerschein be kosten'], 3, 5, 21, 'national', null, 0.5],
                ['Fahrschulplatz zum Semesterstart sichern', 'fahrschule anmeldung herbst', ['fahrschule studenten'], 8, 10, 21, 'national', null, 0.5],
                ['Praktische Prüfung nicht bestanden — wie weiter', 'praktische prüfung nicht bestanden', ['führerschein prüfung wiederholen'], 1, 12, 7, 'national', null, 0.5],
                ['Fahrsicherheitstraining vor dem Winter', 'fahrsicherheitstraining winter', ['glätte fahrtraining'], 9, 11, 21, 'national', null, 0.5],
                ['Lkw-Führerschein für den Berufseinstieg', 'lkw führerschein kosten', ['führerschein klasse c'], 1, 12, 30, 'national', null, 0.4],
                ['Führerschein umschreiben nach Umzug', 'führerschein umschreiben', ['ausländischen führerschein umschreiben'], 1, 12, 14, 'national', null, 0.4],
                ['Nachtfahrten und Sonderfahrten planen', 'sonderfahrten fahrschule', ['nachtfahrt autobahnfahrt pflicht'], 3, 5, 14, 'national', null, 0.3],
                ['Erste-Hilfe-Kurs für den Führerschein', 'erste-hilfe-kurs führerschein', ['sofortmaßnahmen kurs'], 1, 12, 14, 'national', null, 0.4],
                ['Rollerführerschein zum Frühlingsstart', 'rollerführerschein ab 15', ['mofa führerschein kosten'], 2, 4, 21, 'national', null, 0.4],
                ['Führerschein-Kosten im Vergleich', 'führerschein kosten vergleich', ['fahrschule preisvergleich'], 1, 3, 21, 'national', null, 0.6],
                ['Wiedereinstieg nach Führerscheinentzug', 'mpu vorbereitung', ['führerschein wieder machen'], 1, 12, 21, 'national', null, 0.3],
                ['Fahrschule für Berufskraftfahrer', 'berufskraftfahrer ausbildung', ['cpc weiterbildung'], 1, 12, 30, 'national', null, 0.3],
                ['Herbstrabatt für die Fahrschulanmeldung', 'fahrschule herbstangebot', ['fahrschule rabatt anmeldung'], 9, 10, 14, 'national', null, 0.3],
                ['Sehtest für den Führerschein', 'sehtest führerschein', ['führerschein unterlagen'], 1, 12, 7, 'national', null, 0.3],
                ['Vorbereitung auf die Winterfahrprüfung', 'fahrprüfung winter vorbereitung', ['fahrstunden glätte'], 11, 1, 14, 'national', null, 0.4],
            ],
            'tierarzt' => [
                ['Zeckenschutz für den Frühling', 'zeckenschutz hund frühling', ['zeckenmittel hund katze'], 3, 5, 21, 'national', null, 1.0],
                ['Silvesterangst bei Hunden vorbeugen', 'hund silvesterangst', ['tierarzt beruhigungsmittel hund'], 11, 12, 14, 'national', null, 0.8],
                ['Sommerhitze und Hitzschlag beim Haustier', 'hitzschlag hund vorbeugen', ['haustier sommerhitze'], 6, 8, 14, 'national', null, 0.8],
                ['Impfschema für Welpen im Frühjahr', 'welpen impfschema', ['grundimmunisierung welpe'], 2, 4, 21, 'national', null, 0.7],
                ['Kastration beim Kater vor dem Frühjahr', 'kastration kater kosten', ['katze kastrieren wann'], 1, 3, 21, 'national', null, 0.6],
                ['Reisevorbereitung für Haustiere im Sommer', 'haustier reise eu heimtierausweis', ['tier im urlaub mitnehmen'], 4, 6, 21, 'national', null, 0.6],
                ['Fellwechsel und Hautpflege im Frühjahr', 'fellwechsel hund pflege', ['hautprobleme haustier frühjahr'], 3, 5, 14, 'national', null, 0.4],
                ['Notdienst an Weihnachten und Silvester', 'tierärztlicher notdienst feiertage', ['tierklinik notfall'], 12, 1, 14, 'national', null, 0.6],
                ['Zahnsteinbehandlung beim Hund im Herbst', 'zahnsteinbehandlung hund', ['zahnreinigung tier kosten'], 9, 11, 21, 'national', null, 0.5],
                ['Wurmkur im Frühjahr auffrischen', 'wurmkur hund katze frühjahr', ['entwurmung intervall'], 2, 4, 14, 'national', null, 0.5],
                ['Tierkrankenversicherung vor der Anschaffung', 'tierkrankenversicherung sinnvoll', ['op versicherung hund katze'], 1, 12, 21, 'national', null, 0.6],
                ['Giftköder-Saison im Frühjahr', 'giftköder hund erkennen', ['vergiftung hund erste hilfe'], 3, 5, 14, 'national', null, 0.5],
                ['Kaninchen- und Kleintierimpfungen im Frühjahr', 'kaninchen impfung myxomatose', ['kleintier impfschema'], 3, 5, 14, 'national', null, 0.3],
                ['Altersvorsorge fürs Haustier: Seniorencheck', 'seniorencheck hund katze', ['tier altersuntersuchung'], 9, 11, 21, 'national', null, 0.4],
                ['Grillsaison: Gefahren für Haustiere', 'gefahren grillen haustier', ['haustier sicherheit garten sommer'], 5, 7, 14, 'national', null, 0.3],
                ['Winterpfoten: Streusalz und Haustiere', 'streusalz hundepfoten', ['pfotenschutz winter'], 11, 1, 14, 'national', null, 0.4],
                ['Katzen im Freilauf vor dem Frühjahr chippen', 'katze chippen kosten', ['katzenregistrierung'], 3, 4, 14, 'national', null, 0.3],
                ['Vorsorgeuntersuchung zum Jahresstart', 'vorsorgeuntersuchung haustier', ['jährlicher tierarztcheck'], 1, 2, 14, 'national', null, 0.5],
                ['Notfallapotheke fürs Haustier zusammenstellen', 'erste-hilfe-set haustier', ['haustier notfallapotheke'], 4, 6, 14, 'national', null, 0.3],
                ['Herbstliche Pilzvergiftung bei Hunden', 'pilzvergiftung hund herbst', ['hund frisst pilz'], 9, 11, 7, 'national', null, 0.4],
            ],
            'gutachter' => [
                ['Kfz-Gutachten nach Glätteunfällen im Winter', 'kfz gutachten unfall winter', ['unfallgutachten kosten'], 11, 2, 14, 'national', null, 0.8],
                ['Immobiliengutachten vor dem Frühjahrsverkauf', 'immobiliengutachten verkehrswert', ['hausbewertung kosten'], 1, 3, 21, 'national', null, 0.9],
                ['Bausachverständiger vor dem Hauskauf', 'bausachverständiger hauskauf', ['baumängel prüfen kosten'], 2, 4, 21, 'national', null, 0.8],
                ['Wertgutachten für die Erbschaftsteuer', 'wertgutachten erbschaft', ['immobilie erben bewertung'], 1, 12, 21, 'national', null, 0.6],
                ['Kfz-Gutachten bei Wildunfällen im Herbst', 'wildunfall gutachten', ['unfallschaden reh auto'], 9, 11, 14, 'national', null, 0.5],
                ['Minderwert nach einem Unfall geltend machen', 'merkantiler minderwert berechnen', ['fahrzeug wertminderung unfall'], 1, 12, 14, 'national', null, 0.5],
                ['Bauabnahme mit Sachverständigem im Frühjahr', 'bauabnahme sachverständiger', ['neubau abnahme mängel'], 3, 5, 21, 'national', null, 0.5],
                ['Beweissicherung bei Sturmschäden', 'beweissicherung sturmschaden', ['schaden dokumentieren gutachten'], 9, 11, 14, 'national', null, 0.4],
                ['Schiedsgutachten bei Streit mit der Versicherung', 'schiedsgutachten versicherung', ['gutachterstreit versicherung'], 1, 12, 21, 'national', null, 0.4],
                ['Kurzgutachten oder Vollgutachten wählen', 'kurzgutachten vollgutachten unterschied', ['kfz gutachten art wählen'], 1, 12, 14, 'national', null, 0.4],
                ['Verkehrswertermittlung nach ImmoWertV', 'verkehrswertermittlung immobilie', ['immobilienwert berechnen'], 2, 4, 21, 'national', null, 0.5],
                ['Gutachterkosten nach einem Unfall — wer zahlt', 'gutachterkosten unfall wer zahlt', ['kfz gutachten kostenübernahme'], 1, 12, 14, 'national', null, 0.6],
                ['Feuchtigkeitsschäden im Altbau begutachten', 'feuchtigkeitsschäden gutachten', ['schimmelgutachten altbau'], 2, 4, 21, 'national', null, 0.4],
                ['Gutachten vor dem Immobilienkauf im Frühjahr', 'gutachten vor immobilienkauf', ['hauskauf gutachter beauftragen'], 2, 4, 21, 'national', null, 0.5],
                ['Wertminderung bei Blechschäden im Winter', 'blechschaden wertminderung', ['kfz schaden bewerten'], 11, 2, 14, 'national', null, 0.4],
                ['Öffentlich bestellte Sachverständige finden', 'öffentlich bestellter sachverständiger finden', ['vereidigter gutachter suchen'], 1, 12, 14, 'national', null, 0.4],
                ['Gutachten bei Starkregenschäden im Sommer', 'starkregen schaden gutachten', ['wasserschaden gutachter'], 6, 8, 14, 'national', null, 0.4],
                ['Fahrzeugbewertung vor dem Verkauf', 'fahrzeugbewertung verkauf', ['auto wertgutachten verkauf'], 1, 12, 14, 'national', null, 0.4],
                ['Baumängel vor Ablauf der Gewährleistung prüfen', 'baumängel gewährleistung prüfen', ['mängelrüge bau'], 3, 5, 21, 'national', null, 0.4],
                ['Hagelschäden am Auto begutachten lassen', 'hagelschaden gutachten auto', ['hagelschaden kosten'], 5, 7, 14, 'national', null, 0.5],
            ],
            'medizin' => [
                ['Grippeimpfung im Herbst', 'grippeimpfung termin herbst', ['impfsprechstunde praxis'], 9, 11, 14, 'national', null, 1.0],
                ['Zahnreinigung vor den Feiertagen', 'professionelle zahnreinigung kosten', ['zahnreinigung termin'], 10, 12, 21, 'national', null, 0.7],
                ['Sonnenschutz und Hautkrebsvorsorge im Sommer', 'hautkrebsvorsorge termin', ['hautarzt sommer check'], 5, 7, 21, 'national', null, 0.8],
                ['Zeckenbiss und FSME-Impfung im Frühjahr', 'fsme impfung zeckenschutz', ['zeckenbiss arzt'], 3, 5, 14, 'national', null, 0.8],
                ['Reiseapotheke für den Sommerurlaub', 'reiseapotheke zusammenstellen', ['apotheke reisemedikamente'], 5, 7, 14, 'national', null, 0.6],
                ['Erkältungszeit: Apotheke oder Arztbesuch', 'erkältung apotheke oder arzt', ['grippaler infekt behandlung'], 11, 2, 14, 'national', null, 0.7],
                ['D-Arzt nach einem Arbeitsunfall finden', 'd-arzt arbeitsunfall', ['durchgangsarzt verfahren'], 1, 12, 14, 'national', null, 0.5],
                ['Zahnersatz-Kosten und Zuzahlung', 'zahnersatz kosten zuzahlung', ['festzuschuss zahnersatz'], 1, 12, 21, 'national', null, 0.6],
                ['Notdienst an Weihnachten und Silvester', 'ärztlicher notdienst feiertage', ['bereitschaftsdienst 116117'], 12, 1, 14, 'national', null, 0.7],
                ['Allergiesaison: Pollenflug und Apothekenberatung', 'heuschnupfen apotheke beratung', ['pollenflug allergie behandeln'], 3, 5, 14, 'national', null, 0.6],
                ['Grillunfälle und Erste Hilfe im Sommer', 'verbrennung erste hilfe grillen', ['unfallarzt verbrennung'], 5, 7, 7, 'national', null, 0.4],
                ['Impfschutz vor der Fernreise prüfen', 'reiseimpfung beratung', ['impfberatung apotheke'], 3, 6, 21, 'national', null, 0.5],
                ['Vorsorgeuntersuchungen zum Jahresstart wahrnehmen', 'vorsorgeuntersuchung krankenkasse', ['check-up 35 termin'], 1, 3, 14, 'national', null, 0.5],
                ['Winterglätte: Unfallarzt bei Stürzen', 'unfallarzt sturz glätte', ['knochenbruch behandlung'], 12, 2, 7, 'national', null, 0.5],
                ['IGeL-Leistungen beim Zahnarzt hinterfragen', 'igel leistung zahnarzt', ['individuelle gesundheitsleistung kosten'], 1, 12, 14, 'national', null, 0.5],
                ['Sonnenbrand-Behandlung in der Apotheke', 'sonnenbrand behandeln apotheke', ['sonnenbrand hausmittel arzt'], 6, 8, 7, 'national', null, 0.4],
                ['Grippewelle: Wann zum Arzt, wann zur Apotheke', 'grippewelle apotheke arztbesuch', ['fieber behandeln erwachsene'], 12, 2, 14, 'national', null, 0.6],
                ['Kinderimpfungen zum Kita-Start', 'kinderimpfungen kita start', ['impfkalender kind'], 7, 9, 21, 'national', null, 0.5],
                ['Zahnärztlicher Bereitschaftsdienst am Wochenende', 'zahnärztlicher notdienst wochenende', ['zahnschmerzen notdienst'], 1, 12, 7, 'national', null, 0.5],
                ['Grillsaison-Hygiene und Lebensmittelvergiftung', 'lebensmittelvergiftung symptome arzt', ['salmonellen grillsaison'], 5, 8, 7, 'national', null, 0.3],
            ],
            default => [],
        };
    }
}
