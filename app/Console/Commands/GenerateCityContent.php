<?php

namespace App\Console\Commands;

use App\Models\Portal\City;
use App\Models\Portal\CityContent;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateCityContent extends Command implements Isolatable
{
    protected $signature = 'app:generate-city-content
        {--tenant= : Only for a specific tenant ID}
        {--city= : Only for a specific city ID}
        {--overwrite : Overwrite existing content}
        {--dry-run : Show what would be generated without saving}
        {--provider= : AI provider (anthropic|openai)}
        {--batch-size=50 : Number of cities per batch}
        {--skip-existing : Skip cities that already have content}';

    protected $description = 'Generate city intro texts using AI (Claude/OpenAI)';

    private int $generated = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private int $requestCount = 0;

    public function handle(): int
    {
        $provider = $this->option('provider') ?? config('ai.default', 'anthropic');
        $providerConfig = config("ai.providers.{$provider}");
        $isDryRun = $this->option('dry-run');

        if (! $isDryRun && (! $providerConfig || empty($providerConfig['api_key']))) {
            $this->error("API key for provider '{$provider}' not configured. Set " . strtoupper($provider === 'anthropic' ? 'ANTHROPIC_API_KEY' : 'OPENAI_API_KEY') . ' in .env');

            return self::FAILURE;
        }

        $tenants = $this->getTenants();

        if ($isDryRun) {
            $this->warn('DRY RUN — nothing will be saved.');
        }

        $this->info("Provider: {$provider} ({$providerConfig['model']})");
        $this->info('Tenants: ' . $tenants->count());

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $provider, $providerConfig, $isDryRun);
        }

        $this->newLine();
        $this->info("Done. Generated: {$this->generated}, Skipped: {$this->skipped}, Errors: {$this->errors}");

        return $this->errors > 0 && $this->generated === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function getTenants()
    {
        if ($tenantId = $this->option('tenant')) {
            return Tenant::where('id', $tenantId)->get();
        }

        return Tenant::all();
    }

    private function processTenant(Tenant $tenant, string $provider, array $config, bool $isDryRun): void
    {
        tenancy()->initialize($tenant);

        $portalName = $tenant->name ?? config('app.name', 'Branchenportal');

        $query = City::query()->orderBy('name');

        if ($cityId = $this->option('city')) {
            $query->where('id', $cityId);
        }

        if (! $this->option('overwrite')) {
            $query->whereDoesntHave('cityContent');
        }

        $totalCities = $query->count();
        $this->info("Tenant {$tenant->id} ({$portalName}): {$totalCities} cities to process");

        if ($totalCities === 0) {
            return;
        }

        $batchSize = (int) $this->option('batch-size');
        $bar = $this->output->createProgressBar($totalCities);
        $bar->start();

        $query->with('cityContent')
            ->withCount('companies')
            ->chunk($batchSize, function ($cities) use ($provider, $config, $isDryRun, $portalName, $bar) {
                foreach ($cities as $city) {
                    $this->processCity($city, $provider, $config, $isDryRun, $portalName);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();

        tenancy()->end();
    }

    private function processCity(City $city, string $provider, array $config, bool $isDryRun, string $portalName): void
    {
        // Skip if content exists and not overwriting
        if (! $this->option('overwrite') && $city->cityContent) {
            $this->skipped++;

            return;
        }

        $prompt = $this->buildPrompt($city, $portalName);

        if ($isDryRun) {
            $this->newLine();
            $this->line("  [DRY] {$city->name} ({$city->administrative_area_level_1}) — {$city->companies_count} firms");
            $this->skipped++;

            return;
        }

        // Rate limiting
        $this->rateLimit();

        try {
            $text = $provider === 'anthropic'
                ? $this->callAnthropic($prompt, $config)
                : $this->callOpenAI($prompt, $config);

            if (empty($text)) {
                $this->errors++;
                Log::warning("GenerateCityContent: Empty response for city {$city->id} ({$city->name})");

                return;
            }

            // Generate meta fields
            $metaTitle = $this->generateMetaTitle($city, $portalName);
            $metaDescription = $this->generateMetaDescription($city, $portalName);

            CityContent::updateOrCreate(
                ['city_id' => $city->id],
                [
                    'intro_text' => $text,
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    'is_generated' => true,
                    'generated_at' => now(),
                ]
            );

            $this->generated++;
        } catch (\Exception $e) {
            $this->errors++;
            Log::error("GenerateCityContent: Error for city {$city->id} ({$city->name}): {$e->getMessage()}");
            $this->newLine();
            $this->error("  Error: {$city->name} — {$e->getMessage()}");

            // Backoff on error
            usleep(5_000_000); // 5 seconds
        }
    }

    private function buildPrompt(City $city, string $portalName): string
    {
        $state = $city->administrative_area_level_1 ?: 'Deutschland';
        $companyCount = $city->companies_count ?? 0;
        $community = $city->community ? " ({$city->community})" : '';

        return <<<PROMPT
Schreibe einen informativen Introtext für die Stadt {$city->name}{$community} in {$state} im Kontext des Branchenportals "{$portalName}".

Anforderungen:
- 150-250 Wörter, auf Deutsch, professioneller aber zugänglicher Ton
- Erwähne die Stadt, ihre Lage und regionale Bedeutung
- Stelle einen Bezug zu lokalen Unternehmen und Dienstleistern her
- Aktuell sind {$companyCount} Unternehmen aus {$city->name} auf {$portalName} gelistet
- SEO-optimiert: Verwende natürlich Keywords wie "Unternehmen in {$city->name}", "Dienstleister {$city->name}", "Firmen {$city->name}"
- Format: Reines HTML mit <p>-Tags (2-3 Absätze). Optional eine <h3>-Zwischenüberschrift
- Keine erfundenen Fakten, keine konkreten Einwohnerzahlen wenn unsicher
- Kein Markdown, kein Intro wie "Hier ist der Text:", direkt mit dem HTML-Content beginnen
PROMPT;
    }

    private function callAnthropic(string $prompt, array $config): string
    {
        $this->requestCount++;

        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(30)
            ->retry(2, 3000)
            ->post($config['base_url'] . '/messages', [
                'model' => $config['model'],
                'max_tokens' => $config['max_tokens'],
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Anthropic API error: {$response->status()} — {$response->body()}");
        }

        $data = $response->json();

        return $data['content'][0]['text'] ?? '';
    }

    private function callOpenAI(string $prompt, array $config): string
    {
        $this->requestCount++;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ])
            ->timeout(30)
            ->retry(2, 3000)
            ->post($config['base_url'] . '/chat/completions', [
                'model' => $config['model'],
                'max_tokens' => $config['max_tokens'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Du bist ein professioneller SEO-Texter für deutsche Branchenportale.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("OpenAI API error: {$response->status()} — {$response->body()}");
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? '';
    }

    private function rateLimit(): void
    {
        $delayMs = config('ai.rate_limit.delay_ms', 2000);
        usleep($delayMs * 1000);
    }

    private function generateMetaTitle(City $city, string $portalName): string
    {
        $name = $city->name;

        return "Unternehmen in {$name} — {$portalName}";
    }

    private function generateMetaDescription(City $city, string $portalName): string
    {
        $name = $city->name;
        $state = $city->administrative_area_level_1 ?: 'Deutschland';
        $count = $city->companies_count ?? 0;

        if ($count > 0) {
            return "Finden Sie {$count} Unternehmen und Dienstleister in {$name} ({$state}). Lokale Firmen entdecken, Bewertungen lesen und Kontakt aufnehmen — auf {$portalName}.";
        }

        return "Entdecken Sie lokale Unternehmen und Dienstleister in {$name} ({$state}) — auf {$portalName}.";
    }
}
