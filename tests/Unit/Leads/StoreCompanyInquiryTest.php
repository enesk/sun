<?php

namespace Tests\Unit\Leads;

use App\Constants\CompanyInquiryStatus;
use App\Events\Company\CompanyInquiryReceived;
use App\Jobs\StoreCompanyInquiry;
use App\Models\Portal\CompanyInquiry;
use App\Models\User;
use App\Policies\CompanyInquiryPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * #32: Die Tenant-Verbindung laeuft hier auf SQLite im Speicher, damit weder
 * die zentrale noch eine Tenant-Datenbank angefasst wird.
 */
class StoreCompanyInquiryTest extends TestCase
{
    private const LEAD_UUID = '9b1e4c1a-6f7d-4b7e-9d53-2f0a6c1e8a10';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.default' => 'tenant',
        ]);
        DB::purge('tenant');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });

        (require database_path('migrations/tenant/2026_09_17_000002_create_company_inquiries_table.php'))->up();

        DB::table('companies')->insert([
            ['id' => 10, 'user_id' => 5, 'name' => 'Elektro Meier', 'slug' => 'elektro-meier'],
            ['id' => 11, 'user_id' => null, 'name' => 'Elektro Ohne Inhaber', 'slug' => 'elektro-ohne-inhaber'],
            ['id' => 12, 'user_id' => 6, 'name' => 'Fremdbetrieb', 'slug' => 'fremdbetrieb'],
            ['id' => 13, 'user_id' => null, 'name' => '24 Stunden Elektro', 'slug' => '24-stunden-elektro'],
            ['id' => 14, 'user_id' => null, 'name' => 'Doppelt A', 'slug' => 'doppelt'],
            ['id' => 15, 'user_id' => null, 'name' => 'Doppelt B', 'slug' => 'doppelt'],
        ]);

        Event::fake([CompanyInquiryReceived::class]);
    }

    public function test_stores_inquiry_at_company_without_firmenprofil_answer(): void
    {
        (new StoreCompanyInquiry($this->lead(10, 'elektro-meier')))->handle();

        $inquiry = CompanyInquiry::sole();

        $this->assertSame(10, $inquiry->company_id);
        $this->assertSame(CompanyInquiryStatus::NEW, $inquiry->refresh()->status);
        $this->assertTrue($inquiry->contact_visible);
        $this->assertSame('Mara Lindqvist', $inquiry->contact_name);
        $this->assertSame('mara@example.com', $inquiry->contact_email);
        $this->assertSame(42, $inquiry->score);
        $this->assertSame('2026-09-17 08:30:00', $inquiry->received_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame([
            ['key' => 'leistung', 'label' => 'Was soll gemacht werden?', 'value' => 'wallbox', 'value_label' => 'Wallbox'],
        ], $inquiry->answers);

        Event::assertDispatchedTimes(CompanyInquiryReceived::class, 1);
    }

    public function test_stores_inquiry_for_company_without_owner(): void
    {
        (new StoreCompanyInquiry($this->lead(11, 'elektro-ohne-inhaber')))->handle();

        $this->assertSame(11, CompanyInquiry::sole()->company_id);
        Event::assertDispatched(CompanyInquiryReceived::class);
    }

    public function test_slug_mismatch_is_logged_without_contact_data_and_not_stored(): void
    {
        Log::spy();

        (new StoreCompanyInquiry($this->lead(10, 'anderer-betrieb')))->handle();

        $this->assertSame(0, CompanyInquiry::count());
        Event::assertNotDispatched(CompanyInquiryReceived::class);
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context);

            return $context['lead_uuid'] === self::LEAD_UUID
                && ! str_contains($encoded, 'mara')
                && ! str_contains($encoded, '0170');
        });
    }

    public function test_duplicate_delivery_is_stored_once(): void
    {
        (new StoreCompanyInquiry($this->lead(10, 'elektro-meier')))->handle();
        (new StoreCompanyInquiry($this->lead(10, 'elektro-meier')))->handle();

        $this->assertSame(1, CompanyInquiry::count());
        Event::assertDispatchedTimes(CompanyInquiryReceived::class, 1);
    }

    /**
     * #38: So kommt firmenprofil auf Produktion an — die Profil-URL als Text.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function textReferences(): array
    {
        return [
            'Profil-URL' => ['https://elektrikerportal.com/10-elektro-meier', 10],
            'Profil-URL mit Stadt, Slash und Query' => ['https://elektrikerportal.com/rastatt/11-elektro-ohne-inhaber/?utm_source=x', 11],
            'relativer Pfad' => ['/10-elektro-meier', 10],
            'Slug mit ID' => ['10-elektro-meier', 10],
            'Slug ohne ID' => ['elektro-meier', 10],
            'Slug ohne ID, beginnt mit Ziffern' => ['24-stunden-elektro', 13],
            'JSON als Text' => ['{"id":12,"slug":"fremdbetrieb"}', 12],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('textReferences')]
    public function test_resolves_company_from_text_reference(string $value, int $companyId): void
    {
        (new StoreCompanyInquiry($this->leadWithReference($value)))->handle();

        $inquiry = CompanyInquiry::sole();

        $this->assertSame($companyId, $inquiry->company_id);
        $this->assertSame(['leistung'], array_column($inquiry->answers, 'key'));
        Event::assertDispatchedTimes(CompanyInquiryReceived::class, 1);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unresolvableReferences(): array
    {
        return [
            'URL mit falschem Slug' => ['https://elektrikerportal.com/10-anderer-betrieb'],
            'URL ohne ID' => ['https://elektrikerportal.com/elektro-meier'],
            'mehrdeutiger Slug' => ['doppelt'],
            'leer' => [''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unresolvableReferences')]
    public function test_unresolvable_text_reference_is_logged_with_raw_value(string $value): void
    {
        Log::spy();

        (new StoreCompanyInquiry($this->leadWithReference($value)))->handle();

        $this->assertSame(0, CompanyInquiry::count());
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $context['firmenprofil_raw'] === $value
        );
    }

    public function test_raw_value_in_log_is_truncated(): void
    {
        Log::spy();

        (new StoreCompanyInquiry($this->leadWithReference('https://elektrikerportal.com/'.str_repeat('x', 400))))->handle();

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => mb_strlen($context['firmenprofil_raw']) <= 203
        );
    }

    public function test_policy_allows_only_owner_of_the_company(): void
    {
        (new StoreCompanyInquiry($this->lead(10, 'elektro-meier')))->handle();
        $inquiry = CompanyInquiry::sole();
        $policy = new CompanyInquiryPolicy;

        $owner = $this->user(5);
        $foreignOwner = $this->user(6);
        $withoutCompany = $this->user(7);

        $this->assertTrue($policy->view($owner, $inquiry));
        $this->assertTrue($policy->update($owner, $inquiry));
        $this->assertFalse($policy->view($foreignOwner, $inquiry));
        $this->assertFalse($policy->update($foreignOwner, $inquiry));
        $this->assertTrue($policy->viewAny($foreignOwner));
        $this->assertFalse($policy->viewAny($withoutCompany));
    }

    /**
     * @return array<string, mixed>
     */
    private function lead(int $companyId, string $slug): array
    {
        return [
            'id' => 881,
            'uuid' => self::LEAD_UUID,
            'state' => 'neu',
            'score' => 42,
            'result_key' => null,
            'created_at' => '2026-09-17T10:30:00+02:00',
            'contact' => [
                'name' => 'Mara Lindqvist',
                'first_name' => 'Mara',
                'last_name' => 'Lindqvist',
                'email' => 'mara@example.com',
                'phone' => '0170 1234567',
                'postal_code' => '76437',
                'masked' => false,
                'phone_masked' => false,
            ],
            'answers' => [
                ['field_key' => 'leistung', 'label' => 'Was soll gemacht werden?', 'value' => 'wallbox', 'value_label' => 'Wallbox'],
                ['field_key' => 'firmenprofil', 'label' => 'firmenprofil', 'value' => [
                    'id' => $companyId,
                    'slug' => $slug,
                    'name' => 'Elektro Meier',
                    'url' => 'https://elektriker.test/firma/10-elektro-meier',
                    'portal' => 'Elektriker',
                ], 'value_label' => null],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leadWithReference(string $value): array
    {
        $lead = $this->lead(0, '');
        $lead['answers'][1]['value'] = $value;

        return $lead;
    }

    private function user(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }
}
