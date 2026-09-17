<?php

namespace Database\Factories\Portal;

use App\Constants\CompanyInquiryStatus;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Testanfragen fuer die Seite "Anfragen" (#33), solange das Leadsystem noch
 * keine Antworten per Webhook liefert (#30). Antworten im Format von
 * StoreCompanyInquiry: key, label, value, value_label.
 *
 * @extends Factory<CompanyInquiry>
 */
class CompanyInquiryFactory extends Factory
{
    protected $model = CompanyInquiry::class;

    public function definition(): array
    {
        $service = $this->faker->randomElement(['Wallbox installieren', 'Steckdosen erneuern', 'E-Check', 'Smart Home einrichten', 'Störung beheben']);
        $timing = $this->faker->randomElement(['So schnell wie möglich', 'In den nächsten 2 Wochen', 'Flexibel']);
        $received = $this->faker->dateTimeBetween('-30 days');

        return [
            'company_id' => Company::factory(),
            'lead_uuid' => (string) Str::uuid(),
            'status' => CompanyInquiryStatus::NEW,
            'answers' => [
                ['key' => 'leistung', 'label' => 'Was sollen wir für dich erledigen?', 'value' => Str::slug($service), 'value_label' => $service],
                ['key' => 'objekt', 'label' => 'Um welches Objekt geht es?', 'value' => 'wohnung', 'value_label' => $this->faker->randomElement(['Wohnung', 'Einfamilienhaus', 'Gewerbe'])],
                ['key' => 'zeitraum', 'label' => 'Wann soll es losgehen?', 'value' => Str::slug($timing), 'value_label' => $timing],
                ['key' => 'plz', 'label' => 'Postleitzahl', 'value' => $this->faker->numerify('7####'), 'value_label' => null],
                ['key' => 'weitere', 'label' => 'Möchtest du noch etwas ergänzen?', 'value' => $this->faker->sentence(12), 'value_label' => null],
            ],
            'contact_name' => $this->faker->name(),
            'contact_email' => $this->faker->safeEmail(),
            'contact_phone' => '0711 '.$this->faker->numerify('#######'),
            'contact_visible' => true,
            'received_at' => $received,
            'created_at' => $received,
            'updated_at' => $received,
        ];
    }

    public function status(CompanyInquiryStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'read_at' => $status === CompanyInquiryStatus::NEW ? null : now(),
        ]);
    }
}
