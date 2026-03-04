<?php

namespace Database\Seeders;

use App\Models\Portal\Company;
use App\Models\Portal\Job;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JobSeeder extends Seeder
{
    /**
     * Seed Jobs fuer eine bestimmte Company.
     *
     * Usage: php artisan db:seed --class=JobSeeder
     * Erstellt 5 realistische Stellenanzeigen fuer Company 59396 (HSM Mario Ruhnke, Klempner & Sanitaer).
     */
    public function run(): void
    {
        $companyId = 59396;
        $company = Company::find($companyId);

        if (! $company) {
            $this->command->error("Company {$companyId} nicht gefunden.");

            return;
        }

        $this->command->info("Erstelle Jobs fuer: {$company->name} (ID: {$companyId})");

        $jobs = [
            [
                'title' => 'Anlagenmechaniker SHK (m/w/d) — Kundendienst',
                'description' => "Wir suchen einen erfahrenen Anlagenmechaniker fuer Sanitaer-, Heizungs- und Klimatechnik (SHK) fuer unseren Kundendienst in der Region Oschersleben.\n\nIhre Aufgaben:\n- Wartung und Instandhaltung von Heizungsanlagen (Gas, Oel, Waermepumpen)\n- Reparaturen im Bereich Sanitaer und Heizung bei unseren Kunden vor Ort\n- Fehlerdiagnose und Stoerungsbehebung\n- Kundenberatung zu Modernisierungsmoeglichkeiten\n- Dokumentation der durchgefuehrten Arbeiten\n\nWir bieten Ihnen einen sicheren Arbeitsplatz in einem familiaren Team mit kurzen Entscheidungswegen.",
                'requirements' => "- Abgeschlossene Ausbildung als Anlagenmechaniker SHK, Gas-Wasser-Installateur oder vergleichbar\n- Mindestens 3 Jahre Berufserfahrung im Kundendienst\n- Fuehrerschein Klasse B\n- Selbststaendige und zuverlaessige Arbeitsweise\n- Freundliches Auftreten gegenueber Kunden",
                'benefits' => "- Unbefristeter Arbeitsvertrag\n- Firmenwagen (auch zur Privatnutzung)\n- 30 Tage Urlaub\n- Leistungsgerechte Verguetung\n- Weiterbildungsmoeglichkeiten\n- Familiares Betriebsklima",
                'employment_type' => Job::TYPE_VOLLZEIT,
                'salary_min' => 2800,
                'salary_max' => 3500,
                'salary_type' => Job::SALARY_MONTHLY,
                'is_active' => true,
            ],
            [
                'title' => 'Klempner / Installateur (m/w/d)',
                'description' => "Zur Verstaerkung unseres Teams suchen wir einen Klempner bzw. Installateur fuer Sanitaerarbeiten.\n\nIhre Aufgaben:\n- Installation von Sanitaeranlagen in Neubauten und bei Sanierungen\n- Verlegung von Trinkwasser- und Abwasserleitungen\n- Montage von Badezimmerausstattungen\n- Rohrinstallationen und Leitungsbau\n- Zusammenarbeit mit anderen Gewerken auf der Baustelle\n\nWir sind ein etablierter Handwerksbetrieb in Oschersleben und suchen zuverlaessige Verstaerkung.",
                'requirements' => "- Abgeschlossene Ausbildung als Klempner, Installateur oder Anlagenmechaniker SHK\n- Berufserfahrung wuenschenswert, aber nicht zwingend\n- Fuehrerschein Klasse B\n- Teamfaehigkeit und Zuverlaessigkeit\n- Koerperliche Belastbarkeit",
                'benefits' => "- Uebertarifliche Bezahlung\n- Geregelte Arbeitszeiten (Mo-Fr)\n- Betriebliche Altersvorsorge\n- Moderne Werkzeugausstattung\n- Kurze Anfahrtswege in der Region",
                'employment_type' => Job::TYPE_VOLLZEIT,
                'salary_min' => 2500,
                'salary_max' => 3200,
                'salary_type' => Job::SALARY_MONTHLY,
                'is_active' => true,
            ],
            [
                'title' => 'Auszubildender Anlagenmechaniker SHK (m/w/d) — Ausbildung 2026',
                'description' => "Du suchst eine Ausbildung mit Zukunft? Werde Anlagenmechaniker fuer Sanitaer-, Heizungs- und Klimatechnik bei HSM Mario Ruhnke in Oschersleben!\n\nDas erwartet dich:\n- 3,5 Jahre duale Ausbildung (Betrieb + Berufsschule)\n- Abwechslungsreiche Taetigkeiten auf Baustellen und im Kundendienst\n- Installation von Heizungen, Sanitaeranlagen und Klimasystemen\n- Erlernen moderner Techniken (Waermepumpen, Solarthermie)\n- Eigenstaendige Projekte ab dem 2. Lehrjahr\n\nWir bilden seit ueber 15 Jahren erfolgreich aus und uebernehmen bei guter Leistung.",
                'requirements' => "- Haupt- oder Realschulabschluss\n- Technisches Verstaendnis und handwerkliches Geschick\n- Interesse an Sanitaer- und Heizungstechnik\n- Teamfaehigkeit und Lernbereitschaft\n- Fuehrerschein Klasse B oder Bereitschaft zum Erwerb",
                'benefits' => "- Attraktive Ausbildungsverguetung\n- Uebernahmegarantie bei guter Leistung\n- Firmenwagen ab dem 3. Lehrjahr\n- Unterstuetzung bei der Fuehrerscheinfinanzierung\n- Regelmaessige interne Schulungen",
                'employment_type' => Job::TYPE_AUSBILDUNG,
                'salary_min' => 850,
                'salary_max' => 1100,
                'salary_type' => Job::SALARY_MONTHLY,
                'is_active' => true,
            ],
            [
                'title' => 'Burokraft / Sachbearbeitung (m/w/d) — Teilzeit',
                'description' => "Fuer unser Buero in Oschersleben suchen wir eine zuverlaessige Burokraft in Teilzeit (20-25 Stunden/Woche).\n\nIhre Aufgaben:\n- Annahme und Koordination von Kundenanfragen und Auftraegen\n- Rechnungsstellung und vorbereitende Buchhaltung\n- Terminplanung fuer unsere Monteure\n- Bestellwesen und Lagerverwaltung\n- Allgemeine Bueroorganisation und Korrespondenz\n\nSie sind die zentrale Anlaufstelle in unserem Buero und sorgen dafuer, dass alles reibungslos laeuft.",
                'requirements' => "- Abgeschlossene kaufmaennische Ausbildung\n- Sicherer Umgang mit MS Office\n- Organisationstalent und Multitasking-Faehigkeit\n- Freundliche Telefonstimme\n- Erfahrung im Handwerk von Vorteil",
                'benefits' => "- Flexible Arbeitszeiten (Kernzeit 9-14 Uhr)\n- Familiares Betriebsklima\n- Kurzer Arbeitsweg (zentrale Lage in Oschersleben)\n- Unbefristeter Vertrag\n- 28 Tage Urlaub",
                'employment_type' => Job::TYPE_TEILZEIT,
                'salary_min' => 14,
                'salary_max' => 17,
                'salary_type' => Job::SALARY_HOURLY,
                'is_active' => true,
            ],
            [
                'title' => 'Praktikum Sanitaer- und Heizungstechnik (m/w/d)',
                'description' => "Du ueberlegst, ob eine Ausbildung im SHK-Handwerk das Richtige fuer dich ist? Finde es heraus!\n\nWir bieten dir ein 2-4 woechiges Praktikum in unserem Betrieb. Du begleitest unsere Monteure auf Baustellen, lernst die Grundlagen der Sanitaer- und Heizungstechnik kennen und bekommst einen echten Einblick in den Beruf.\n\nDas erwartet dich:\n- Mitarbeit auf Baustellen (unter Anleitung)\n- Einblick in verschiedene Arbeitsbereiche (Sanitaer, Heizung, Kundendienst)\n- Praktische Uebungen in unserer Werkstatt\n- Ehrliches Feedback und Berufsberatung",
                'requirements' => "- Mindestalter 15 Jahre\n- Interesse an handwerklicher Taetigkeit\n- Koerperliche Fitness\n- Praktikumsdauer: 2-4 Wochen (nach Absprache)",
                'benefits' => "- Kostenlose Arbeitskleidung\n- Verpflegungszuschuss\n- Praktikumszeugnis\n- Bei Eignung: Direkter Uebergang in Ausbildung moeglich",
                'employment_type' => Job::TYPE_PRAKTIKUM,
                'salary_min' => null,
                'salary_max' => null,
                'salary_type' => null,
                'is_active' => true,
            ],
        ];

        $created = 0;
        foreach ($jobs as $jobData) {
            $jobData['company_id'] = $companyId;
            $jobData['city_id'] = $company->city_id;
            $jobData['location'] = $company->city?->name ?? 'Oschersleben';

            Job::create($jobData);
            $created++;
            $this->command->info("  ✓ {$jobData['title']}");
        }

        $this->command->info("Fertig: {$created} Jobs erstellt fuer {$company->name}.");
    }
}
