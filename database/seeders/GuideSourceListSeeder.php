<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Guide\Models\TenantGuideSetting;
use App\Guide\Support\BranchResolver;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Befuellt `tenant_guide_settings.source_whitelist_json` und
 * `source_blacklist_json` je Portal-Branche (#7).
 *
 * Whitelist-Eintrag: {domain, publisher, type, trust_level}; `type` ist
 * behoerde|verband|kammer|foerderstelle|fachpresse|normung|wissenschaft,
 * `trust_level` gehoert zu App\Guide\Enums\TrustLevel (official|trade|press).
 * Blacklist-Eintrag: {domain, reason}; `reason` ist forum|content_farm|
 * wettbewerber_verzeichnis|social. Domains gelten samt Subdomains.
 *
 * Die Domains stammen aus dem Fachwissen der Redaktion und sind nicht live
 * geprueft; vor Go-Live (#21) sind sie stichprobenhaft zu kontrollieren.
 *
 * Tenant-Seeder-Muster: idempotent, nur leere Listen werden gefuellt,
 * redaktionell gepflegte Listen bleiben unangetastet. Nicht in DatabaseSeeder
 * registriert; Aufruf: `php artisan db:seed --class=GuideSourceListSeeder`.
 * Tenants ohne erkannte Branche bekommen nur die gemeinsamen Listen.
 */
class GuideSourceListSeeder extends Seeder
{
    public function run(): void
    {
        $filled = 0;
        $skipped = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$filled, &$skipped): void {
            $tenant->run(function () use ($tenant, &$filled, &$skipped): void {
                $setting = TenantGuideSetting::query()->first();

                if ($setting === null) {
                    $this->command?->warn("Tenant {$tenant->getTenantKey()}: keine tenant_guide_settings, zuerst TenantGuideSettingSeeder ausfuehren.");

                    return;
                }

                $branch = BranchResolver::resolve($tenant);
                $changed = false;

                if (empty($setting->source_whitelist_json)) {
                    $setting->source_whitelist_json = self::whitelist($branch);
                    $changed = true;
                }

                if (empty($setting->source_blacklist_json)) {
                    $setting->source_blacklist_json = self::blacklist($branch);
                    $changed = true;
                }

                if ($changed) {
                    $setting->save();
                    $filled++;

                    return;
                }

                $skipped++;
            });
        });

        $this->command?->info("Quellenlisten: {$filled} Portale befuellt, {$skipped} unveraendert.");
    }

    /**
     * @return array<int, array{domain: string, publisher: string, type: string, trust_level: string}>
     */
    public static function whitelist(?string $branch): array
    {
        return self::unique([...self::commonWhitelist(), ...(self::branchWhitelist()[$branch] ?? [])]);
    }

    /**
     * @return array<int, array{domain: string, reason: string}>
     */
    public static function blacklist(?string $branch): array
    {
        return self::unique([...self::commonBlacklist(), ...(self::branchBlacklist()[$branch] ?? [])]);
    }

    /**
     * @param  array<int, array<string, string>>  $entries
     * @return array<int, array<string, string>>
     */
    private static function unique(array $entries): array
    {
        $byDomain = [];

        foreach ($entries as $entry) {
            $byDomain[$entry['domain']] ??= $entry;
        }

        return array_values($byDomain);
    }

    /**
     * @return array{domain: string, publisher: string, type: string, trust_level: string}
     */
    private static function w(string $domain, string $publisher, string $type): array
    {
        return [
            'domain' => $domain,
            'publisher' => $publisher,
            'type' => $type,
            'trust_level' => match ($type) {
                'behoerde', 'foerderstelle', 'normung', 'wissenschaft' => 'official',
                'verband', 'kammer' => 'trade',
                default => 'press',
            },
        ];
    }

    /**
     * @return array{domain: string, reason: string}
     */
    private static function b(string $domain, string $reason): array
    {
        return ['domain' => $domain, 'reason' => $reason];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function commonWhitelist(): array
    {
        return [
            self::w('gesetze-im-internet.de', 'Bundesministerium der Justiz', 'behoerde'),
            self::w('bundesanzeiger.de', 'Bundesanzeiger Verlag', 'behoerde'),
            self::w('bundesregierung.de', 'Bundesregierung', 'behoerde'),
            self::w('destatis.de', 'Statistisches Bundesamt', 'behoerde'),
            self::w('verbraucherzentrale.de', 'Verbraucherzentrale Bundesverband', 'verband'),
            self::w('test.de', 'Stiftung Warentest', 'wissenschaft'),
            self::w('dihk.de', 'Deutscher Industrie- und Handelskammertag', 'kammer'),
            self::w('ihk.de', 'Industrie- und Handelskammern', 'kammer'),
            self::w('zdh.de', 'Zentralverband des Deutschen Handwerks', 'verband'),
            self::w('handwerkskammer.de', 'Handwerkskammern', 'kammer'),
            self::w('din.de', 'DIN Deutsches Institut für Normung', 'normung'),
        ];
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private static function branchWhitelist(): array
    {
        return [
            'fliesen' => [
                self::w('fachverband-fliesen.de', 'Fachverband Fliesen und Naturstein im ZDB', 'verband'),
                self::w('zdb.de', 'Zentralverband Deutsches Baugewerbe', 'verband'),
                self::w('dguv.de', 'Deutsche Gesetzliche Unfallversicherung', 'behoerde'),
                self::w('baunetz.de', 'BauNetz', 'fachpresse'),
            ],
            'metallbau' => [
                self::w('metall.de', 'Bundesverband Metall', 'verband'),
                self::w('dguv.de', 'Deutsche Gesetzliche Unfallversicherung', 'behoerde'),
                self::w('baua.de', 'Bundesanstalt für Arbeitsschutz und Arbeitsmedizin', 'behoerde'),
                self::w('baunetz.de', 'BauNetz', 'fachpresse'),
            ],
            'geruestbau' => [
                self::w('geruestbauhandwerk.de', 'Bundesverband Gerüstbau', 'verband'),
                self::w('dguv.de', 'Deutsche Gesetzliche Unfallversicherung', 'behoerde'),
                self::w('baua.de', 'Bundesanstalt für Arbeitsschutz und Arbeitsmedizin', 'behoerde'),
                self::w('bgbau.de', 'BG BAU', 'behoerde'),
            ],
            'gartenbau' => [
                self::w('galabau.de', 'Bundesverband Garten-, Landschafts- und Sportplatzbau', 'verband'),
                self::w('bmel.de', 'Bundesministerium für Ernährung und Landwirtschaft', 'behoerde'),
                self::w('bfn.de', 'Bundesamt für Naturschutz', 'behoerde'),
                self::w('umweltbundesamt.de', 'Umweltbundesamt', 'behoerde'),
            ],
            'hoch-tiefbau' => [
                self::w('zdb.de', 'Zentralverband Deutsches Baugewerbe', 'verband'),
                self::w('bauindustrie.de', 'Hauptverband der Deutschen Bauindustrie', 'verband'),
                self::w('bbsr.bund.de', 'Bundesinstitut für Bau-, Stadt- und Raumforschung', 'behoerde'),
                self::w('dguv.de', 'Deutsche Gesetzliche Unfallversicherung', 'behoerde'),
                self::w('baunetz.de', 'BauNetz', 'fachpresse'),
            ],
            'solar-pv' => [
                self::w('bundesnetzagentur.de', 'Bundesnetzagentur', 'behoerde'),
                self::w('marktstammdatenregister.de', 'Marktstammdatenregister', 'behoerde'),
                self::w('bafa.de', 'Bundesamt für Wirtschaft und Ausfuhrkontrolle', 'foerderstelle'),
                self::w('kfw.de', 'KfW', 'foerderstelle'),
                self::w('bsw-solar.de', 'Bundesverband Solarwirtschaft', 'verband'),
                self::w('ise.fraunhofer.de', 'Fraunhofer-Institut für Solare Energiesysteme', 'wissenschaft'),
                self::w('pv-magazine.de', 'pv magazine', 'fachpresse'),
            ],
            'energieberatung' => [
                self::w('bafa.de', 'Bundesamt für Wirtschaft und Ausfuhrkontrolle', 'foerderstelle'),
                self::w('kfw.de', 'KfW', 'foerderstelle'),
                self::w('dena.de', 'Deutsche Energie-Agentur', 'behoerde'),
                self::w('energie-effizienz-experten.de', 'Energie-Effizienz-Experten', 'behoerde'),
                self::w('gebaeudeforum.de', 'Zukunft Altbau / Gebäudeforum klimaneutral', 'behoerde'),
                self::w('bmwk.de', 'Bundesministerium für Wirtschaft und Klimaschutz', 'behoerde'),
                self::w('umweltbundesamt.de', 'Umweltbundesamt', 'behoerde'),
            ],
            'sanitaer' => [
                self::w('zvshk.de', 'Zentralverband Sanitär Heizung Klima', 'verband'),
                self::w('dvgw.de', 'Deutscher Verein des Gas- und Wasserfaches', 'verband'),
                self::w('bdew.de', 'BDEW Bundesverband der Energie- und Wasserwirtschaft', 'verband'),
                self::w('umweltbundesamt.de', 'Umweltbundesamt', 'behoerde'),
                self::w('bafa.de', 'Bundesamt für Wirtschaft und Ausfuhrkontrolle', 'foerderstelle'),
                self::w('kfw.de', 'KfW', 'foerderstelle'),
                self::w('haustec.de', 'Haustec', 'fachpresse'),
                self::w('ikz.de', 'IKZ Fachzeitschrift', 'fachpresse'),
            ],
            'elektro' => [
                self::w('zveh.de', 'Zentralverband der Deutschen Elektro- und Informationstechnischen Handwerke', 'verband'),
                self::w('vde.com', 'VDE Verband der Elektrotechnik', 'normung'),
                self::w('dke.de', 'DKE Deutsche Kommission Elektrotechnik', 'normung'),
                self::w('bundesnetzagentur.de', 'Bundesnetzagentur', 'behoerde'),
                self::w('dguv.de', 'Deutsche Gesetzliche Unfallversicherung', 'behoerde'),
                self::w('elektro.net', 'elektro.net', 'fachpresse'),
            ],
            'maler' => [
                self::w('farbe.de', 'Bundesverband Farbe Gestaltung Bautenschutz', 'verband'),
                self::w('umweltbundesamt.de', 'Umweltbundesamt', 'behoerde'),
                self::w('baua.de', 'Bundesanstalt für Arbeitsschutz und Arbeitsmedizin', 'behoerde'),
                self::w('bgbau.de', 'BG BAU', 'behoerde'),
                self::w('malerblatt.de', 'Malerblatt', 'fachpresse'),
            ],
            'kfz' => [
                self::w('zdk.de', 'Zentralverband Deutsches Kraftfahrzeuggewerbe', 'verband'),
                self::w('kba.de', 'Kraftfahrt-Bundesamt', 'behoerde'),
                self::w('bmv.bund.de', 'Bundesministerium für Verkehr', 'behoerde'),
                self::w('tuev-verband.de', 'TÜV-Verband', 'verband'),
                self::w('dekra.de', 'DEKRA', 'wissenschaft'),
                self::w('adac.de', 'ADAC', 'verband'),
                self::w('kfz-betrieb.vogel.de', 'kfz-betrieb', 'fachpresse'),
            ],
            'spedition' => [
                self::w('dslv.org', 'Deutscher Speditions- und Logistikverband', 'verband'),
                self::w('bgl-ev.de', 'Bundesverband Güterkraftverkehr Logistik und Entsorgung', 'verband'),
                self::w('balm.bund.de', 'Bundesamt für Logistik und Mobilität', 'behoerde'),
                self::w('bmv.bund.de', 'Bundesministerium für Verkehr', 'behoerde'),
                self::w('verkehrsrundschau.de', 'VerkehrsRundschau', 'fachpresse'),
            ],
            'fahrschule' => [
                self::w('kba.de', 'Kraftfahrt-Bundesamt', 'behoerde'),
                self::w('bmv.bund.de', 'Bundesministerium für Verkehr', 'behoerde'),
                self::w('bast.de', 'Bundesanstalt für Straßenwesen', 'behoerde'),
                self::w('tuev-verband.de', 'TÜV-Verband', 'verband'),
                self::w('dekra.de', 'DEKRA', 'wissenschaft'),
                self::w('dvr.de', 'Deutscher Verkehrssicherheitsrat', 'verband'),
                self::w('adac.de', 'ADAC', 'verband'),
            ],
            'tierarzt' => [
                self::w('bundestieraerztekammer.de', 'Bundestierärztekammer', 'kammer'),
                self::w('fli.de', 'Friedrich-Loeffler-Institut', 'wissenschaft'),
                self::w('bvl.bund.de', 'Bundesamt für Verbraucherschutz und Lebensmittelsicherheit', 'behoerde'),
                self::w('bmel.de', 'Bundesministerium für Ernährung und Landwirtschaft', 'behoerde'),
                self::w('tierschutzbund.de', 'Deutscher Tierschutzbund', 'verband'),
            ],
            'gutachter' => [
                self::w('bvs-ev.de', 'Bundesverband öffentlich bestellter und vereidigter Sachverständiger', 'verband'),
                self::w('svv.ihk.de', 'IHK-Sachverständigenverzeichnis', 'kammer'),
                self::w('dekra.de', 'DEKRA', 'wissenschaft'),
                self::w('bmj.de', 'Bundesministerium der Justiz', 'behoerde'),
                self::w('bbsr.bund.de', 'Bundesinstitut für Bau-, Stadt- und Raumforschung', 'behoerde'),
            ],
            'medizin' => [
                self::w('rki.de', 'Robert Koch-Institut', 'behoerde'),
                self::w('bfarm.de', 'Bundesinstitut für Arzneimittel und Medizinprodukte', 'behoerde'),
                self::w('g-ba.de', 'Gemeinsamer Bundesausschuss', 'behoerde'),
                self::w('gkv-spitzenverband.de', 'GKV-Spitzenverband', 'verband'),
                self::w('kbv.de', 'Kassenärztliche Bundesvereinigung', 'kammer'),
                self::w('bzaek.de', 'Bundeszahnärztekammer', 'kammer'),
                self::w('abda.de', 'ABDA Bundesvereinigung Deutscher Apothekerverbände', 'kammer'),
                self::w('116117.de', 'Ärztlicher Bereitschaftsdienst', 'behoerde'),
                self::w('gesund.bund.de', 'Nationales Gesundheitsportal', 'behoerde'),
                self::w('aerzteblatt.de', 'Deutsches Ärzteblatt', 'fachpresse'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function commonBlacklist(): array
    {
        return [
            self::b('gutefrage.net', 'forum'),
            self::b('reddit.com', 'forum'),
            self::b('quora.com', 'forum'),
            self::b('wer-weiss-was.de', 'forum'),
            self::b('facebook.com', 'social'),
            self::b('instagram.com', 'social'),
            self::b('tiktok.com', 'social'),
            self::b('pinterest.com', 'social'),
            self::b('11880.com', 'wettbewerber_verzeichnis'),
            self::b('gelbeseiten.de', 'wettbewerber_verzeichnis'),
            self::b('dasoertliche.de', 'wettbewerber_verzeichnis'),
            self::b('myhammer.de', 'wettbewerber_verzeichnis'),
            self::b('my-hammer.de', 'wettbewerber_verzeichnis'),
            self::b('blauarbeit.de', 'wettbewerber_verzeichnis'),
            self::b('werkenntdenbesten.de', 'wettbewerber_verzeichnis'),
            self::b('wlw.de', 'wettbewerber_verzeichnis'),
            self::b('yelp.de', 'wettbewerber_verzeichnis'),
            self::b('check24.de', 'wettbewerber_verzeichnis'),
        ];
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private static function branchBlacklist(): array
    {
        $bauForen = [
            self::b('haustechnikdialog.de', 'forum'),
            self::b('hausbau-forum.de', 'forum'),
            self::b('energiesparhaus.at', 'forum'),
            self::b('bauforum24.de', 'forum'),
        ];

        return [
            'fliesen' => $bauForen,
            'maler' => $bauForen,
            'sanitaer' => $bauForen,
            'elektro' => $bauForen,
            'solar-pv' => [
                self::b('photovoltaikforum.com', 'forum'),
            ],
            'energieberatung' => [
                self::b('energiesparhaus.at', 'forum'),
            ],
            'kfz' => [
                self::b('motor-talk.de', 'forum'),
            ],
            'medizin' => [
                self::b('gesundheitsfrage.net', 'forum'),
                self::b('med1.de', 'forum'),
                self::b('jameda.de', 'wettbewerber_verzeichnis'),
                self::b('doctolib.de', 'wettbewerber_verzeichnis'),
            ],
            'gutachter' => [
                self::b('123recht.net', 'forum'),
                self::b('frag-einen-anwalt.de', 'forum'),
            ],
        ];
    }
}
