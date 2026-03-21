<?php

namespace App\Jobs;

use App\Models\Portal\Category;
use App\Models\Portal\Company;
use App\Models\Portal\FAQ;
use App\Models\Portal\Job as PortalJob;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

class GenerateTenantSitemapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    private const MAX_URLS_PER_SITEMAP = 45000;

    public function __construct(
        public string $tenantId,
    ) {}

    public static function cacheKey(string $tenantId): string
    {
        return "sitemap_progress_{$tenantId}";
    }

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $domain = $tenant->domain;
        $cacheKey = self::cacheKey($this->tenantId);

        if (empty($domain)) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'percent' => 0,
                'message' => 'Keine Domain konfiguriert.',
            ], now()->addMinutes(5));
            return;
        }

        $this->updateProgress($cacheKey, 0, 'Starte Sitemap-Generierung...');

        $scheme = app()->environment('production') ? 'https' : 'http';
        $baseUrl = "{$scheme}://{$domain}";

        $tenant->run(function () use ($baseUrl, $cacheKey) {
            $sitemapDir = storage_path('app/public');
            if (!is_dir($sitemapDir)) {
                mkdir($sitemapDir, 0755, true);
            }

            // Remove old sitemap files
            foreach (glob("{$sitemapDir}/sitemap*.xml") as $oldFile) {
                unlink($oldFile);
            }

            $sitemapFiles = [];

            // Step 1: Static pages + categories + jobs + blog (10%)
            $this->updateProgress($cacheKey, 5, 'Statische Seiten & Kategorien...');
            $miscSitemap = Sitemap::create();
            $this->addStaticPages($miscSitemap, $baseUrl);

            // Categories
            $categories = Category::select(['slug', 'updated_at'])->get();
            foreach ($categories as $category) {
                $miscSitemap->add(
                    Url::create("{$baseUrl}/kategorien/{$category->slug}")
                        ->setPriority(0.7)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setLastModificationDate($category->updated_at)
                );
            }

            // Jobs
            $miscSitemap->add(
                Url::create("{$baseUrl}/jobs")
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            );
            $jobs = PortalJob::active()
                ->published()
                ->select(['slug', 'published_at', 'company_id'])
                ->with(['company:id,is_premium'])
                ->get();
            foreach ($jobs as $job) {
                $miscSitemap->add(
                    Url::create("{$baseUrl}/jobs/{$job->slug}")
                        ->setPriority($job->company?->is_premium ? 0.7 : 0.6)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setLastModificationDate($job->published_at)
                );
            }

            // Blog posts
            $blogPostCount = Post::published()->count();
            if ($blogPostCount > 0) {
                $miscSitemap->add(
                    Url::create("{$baseUrl}/ratgeber")
                        ->setPriority(0.8)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                );
                Post::published()
                    ->select(['slug', 'published_at', 'updated_at'])
                    ->orderByDesc('published_at')
                    ->chunk(200, function ($posts) use ($miscSitemap, $baseUrl) {
                        foreach ($posts as $post) {
                            $miscSitemap->add(
                                Url::create("{$baseUrl}/ratgeber/{$post->slug}")
                                    ->setPriority(0.7)
                                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                                    ->setLastModificationDate($post->updated_at ?? $post->published_at)
                            );
                        }
                    });
            }

            $miscSitemap->writeToFile("{$sitemapDir}/sitemap-misc.xml");
            $sitemapFiles[] = 'sitemap-misc.xml';
            $this->updateProgress($cacheKey, 15, 'Statische Seiten, Kategorien, Jobs & Blog fertig');

            // Step 2: Companies in chunks of MAX_URLS_PER_SITEMAP (15% → 90%)
            $this->updateProgress($cacheKey, 15, 'Firmen werden geladen...');
            $companyCount = Company::active()->count();
            $processed = 0;
            $currentSitemap = Sitemap::create();
            $currentSitemapUrlCount = 0;
            $companySitemapIndex = 1;

            Company::active()
                ->select(['id', 'slug', 'updated_at', 'is_premium'])
                ->orderBy('id')
                ->chunk(500, function ($companies) use (
                    $sitemapDir, $baseUrl, $cacheKey, $companyCount,
                    &$processed, &$currentSitemap, &$currentSitemapUrlCount,
                    &$companySitemapIndex, &$sitemapFiles
                ) {
                    foreach ($companies as $company) {
                        if ($currentSitemapUrlCount >= self::MAX_URLS_PER_SITEMAP) {
                            $filename = "sitemap-companies-{$companySitemapIndex}.xml";
                            $currentSitemap->writeToFile("{$sitemapDir}/{$filename}");
                            $sitemapFiles[] = $filename;
                            $companySitemapIndex++;
                            $currentSitemap = Sitemap::create();
                            $currentSitemapUrlCount = 0;
                        }

                        $currentSitemap->add(
                            Url::create("{$baseUrl}/{$company->url_slug}")
                                ->setPriority($company->is_premium ? 0.8 : 0.7)
                                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                                ->setLastModificationDate($company->updated_at)
                        );
                        $currentSitemapUrlCount++;
                    }

                    $processed += $companies->count();
                    $companyPercent = $companyCount > 0 ? (int) (($processed / $companyCount) * 75) : 75;
                    $this->updateProgress($cacheKey, 15 + $companyPercent, "Firmen: {$processed}/{$companyCount}");
                });

            // Write remaining companies
            if ($currentSitemapUrlCount > 0) {
                $filename = "sitemap-companies-{$companySitemapIndex}.xml";
                $currentSitemap->writeToFile("{$sitemapDir}/{$filename}");
                $sitemapFiles[] = $filename;
            }

            $this->updateProgress($cacheKey, 92, 'Sitemap-Index wird erstellt...');

            // Step 3: Write sitemap index
            $sitemapIndex = SitemapIndex::create();
            foreach ($sitemapFiles as $file) {
                $sitemapIndex->add("{$baseUrl}/{$file}");
            }
            $sitemapIndex->writeToFile("{$sitemapDir}/sitemap.xml");

            $this->updateProgress($cacheKey, 95, 'robots.txt wird geschrieben...');

            // Write robots.txt
            $robotsContent = "User-agent: *\nAllow: /\nDisallow: /firmenprofil/\nDisallow: /verwaltung/\nDisallow: /login\nDisallow: /register\n\nSitemap: {$baseUrl}/sitemap.xml\n";
            file_put_contents("{$sitemapDir}/robots.txt", $robotsContent);
        });

        // Count totals for summary
        $companyCount = $tenant->run(fn () => Company::active()->count());
        $categoryCount = $tenant->run(fn () => Category::count());
        $sitemapFileCount = count(glob(storage_path('app/public/sitemap*.xml')));

        Cache::put($cacheKey, [
            'status' => 'completed',
            'percent' => 100,
            'message' => "Fertig! {$companyCount} Firmen, {$categoryCount} Kategorien in {$sitemapFileCount} Sitemap-Dateien.",
        ], now()->addMinutes(5));
    }

    public function failed(\Throwable $exception): void
    {
        Cache::put(self::cacheKey($this->tenantId), [
            'status' => 'failed',
            'percent' => 0,
            'message' => 'Fehler: ' . $exception->getMessage(),
        ], now()->addMinutes(5));
    }

    private function updateProgress(string $cacheKey, int $percent, string $message): void
    {
        Cache::put($cacheKey, [
            'status' => 'running',
            'percent' => $percent,
            'message' => $message,
        ], now()->addMinutes(10));
    }

    private function addStaticPages(Sitemap $sitemap, string $baseUrl): void
    {
        $sitemap->add(Url::create("{$baseUrl}/")->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
        $sitemap->add(Url::create("{$baseUrl}/firmen")->setPriority(0.9)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
        $sitemap->add(Url::create("{$baseUrl}/kategorien")->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
        $sitemap->add(Url::create("{$baseUrl}/eintragen")->setPriority(0.6)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));
        $sitemap->add(Url::create("{$baseUrl}/impressum")->setPriority(0.3)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));
        $sitemap->add(Url::create("{$baseUrl}/datenschutz")->setPriority(0.3)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));

        if (FAQ::active()->exists()) {
            $sitemap->add(Url::create("{$baseUrl}/faq")->setPriority(0.7)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
        }
    }
}
