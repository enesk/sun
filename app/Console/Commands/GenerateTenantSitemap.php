<?php

namespace App\Console\Commands;

use App\Models\Portal\Category;
use App\Models\Portal\Company;
use App\Models\Portal\FAQ;
use App\Models\Portal\Job;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;

class GenerateTenantSitemap extends Command
{
    protected $signature = 'tenants:generate-sitemap {--tenant= : Specific tenant ID (optional, runs for all if omitted)}';

    protected $description = 'Generate sitemap index with split sitemaps (max 45k URLs each) for each tenant portal';

    private const MAX_URLS_PER_SITEMAP = 45000;

    public function handle(): void
    {
        set_time_limit(600);

        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->generateForTenant($tenant);
        }
    }

    private function generateForTenant(Tenant $tenant): void
    {
        $domain = $tenant->domain;
        if (empty($domain)) {
            $this->warn("Tenant '{$tenant->name}' has no domain, skipping.");
            return;
        }

        $this->info("Generating sitemap for {$tenant->name} ({$domain})...");

        $scheme = app()->environment('production') ? 'https' : 'http';
        $baseUrl = "{$scheme}://{$domain}";

        $tenant->run(function () use ($baseUrl) {
            $sitemapDir = storage_path('app/public');
            if (!is_dir($sitemapDir)) {
                mkdir($sitemapDir, 0755, true);
            }

            // Remove old sitemap files
            foreach (glob("{$sitemapDir}/sitemap*.xml") as $oldFile) {
                unlink($oldFile);
            }

            $sitemapFiles = [];

            // Sitemap 1: Static pages, categories, jobs, blog posts
            $miscSitemap = Sitemap::create();

            // Static pages
            $miscSitemap->add(Url::create("{$baseUrl}/")->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
            $miscSitemap->add(Url::create("{$baseUrl}/firmen")->setPriority(0.9)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
            $miscSitemap->add(Url::create("{$baseUrl}/kategorien")->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
            $miscSitemap->add(Url::create("{$baseUrl}/eintragen")->setPriority(0.6)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));
            $miscSitemap->add(Url::create("{$baseUrl}/impressum")->setPriority(0.3)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));
            $miscSitemap->add(Url::create("{$baseUrl}/datenschutz")->setPriority(0.3)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));

            if (FAQ::active()->exists()) {
                $miscSitemap->add(Url::create("{$baseUrl}/faq")->setPriority(0.7)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
            }

            // Categories
            Category::select(['slug', 'updated_at'])->each(function ($category) use ($miscSitemap, $baseUrl) {
                $miscSitemap->add(
                    Url::create("{$baseUrl}/kategorien/{$category->slug}")
                        ->setPriority(0.7)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setLastModificationDate($category->updated_at)
                );
            });

            // Jobs
            $miscSitemap->add(Url::create("{$baseUrl}/jobs")->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
            Job::active()->published()
                ->select(['slug', 'published_at', 'company_id'])
                ->with(['company:id,is_premium'])
                ->each(function ($job) use ($miscSitemap, $baseUrl) {
                    $miscSitemap->add(
                        Url::create("{$baseUrl}/jobs/{$job->slug}")
                            ->setPriority($job->company?->is_premium ? 0.7 : 0.6)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setLastModificationDate($job->published_at)
                    );
                });

            // Blog posts
            $blogPostCount = Post::published()->count();
            if ($blogPostCount > 0) {
                $miscSitemap->add(Url::create("{$baseUrl}/ratgeber")->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
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

            // Sitemap 2+: Companies split into chunks of MAX_URLS_PER_SITEMAP
            $companyCount = Company::active()->count();
            $this->output?->write("  Companies: {$companyCount}");

            $currentSitemap = Sitemap::create();
            $currentUrlCount = 0;
            $sitemapIndex = 1;
            $processed = 0;

            Company::active()
                ->select(['id', 'slug', 'updated_at', 'is_premium'])
                ->orderBy('id')
                ->chunk(500, function ($companies) use (
                    $sitemapDir, $baseUrl, $companyCount,
                    &$currentSitemap, &$currentUrlCount,
                    &$sitemapIndex, &$sitemapFiles, &$processed
                ) {
                    foreach ($companies as $company) {
                        if ($currentUrlCount >= self::MAX_URLS_PER_SITEMAP) {
                            $filename = "sitemap-companies-{$sitemapIndex}.xml";
                            $currentSitemap->writeToFile("{$sitemapDir}/{$filename}");
                            $sitemapFiles[] = $filename;
                            $sitemapIndex++;
                            $currentSitemap = Sitemap::create();
                            $currentUrlCount = 0;
                        }

                        $currentSitemap->add(
                            Url::create("{$baseUrl}/{$company->url_slug}")
                                ->setPriority($company->is_premium ? 0.8 : 0.7)
                                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                                ->setLastModificationDate($company->updated_at)
                        );
                        $currentUrlCount++;
                    }
                    $processed += $companies->count();
                });

            if ($currentUrlCount > 0) {
                $filename = "sitemap-companies-{$sitemapIndex}.xml";
                $currentSitemap->writeToFile("{$sitemapDir}/{$filename}");
                $sitemapFiles[] = $filename;
            }

            // Write sitemap index
            $sitemapIndexFile = SitemapIndex::create();
            foreach ($sitemapFiles as $file) {
                $sitemapIndexFile->add("{$baseUrl}/{$file}");
            }
            $sitemapIndexFile->writeToFile("{$sitemapDir}/sitemap.xml");

            // Write robots.txt
            $robotsContent = "User-agent: *\nAllow: /\nDisallow: /firmenprofil/\nDisallow: /verwaltung/\nDisallow: /login\nDisallow: /register\n\nSitemap: {$baseUrl}/sitemap.xml\n";
            file_put_contents("{$sitemapDir}/robots.txt", $robotsContent);
        });

        $companyCount = $tenant->run(fn () => Company::active()->count());
        $categoryCount = $tenant->run(fn () => Category::count());
        $jobCount = $tenant->run(fn () => Job::active()->published()->count());
        $blogPostCount = $tenant->run(fn () => Post::published()->count());
        $sitemapFileCount = count(glob($tenant->run(fn () => storage_path('app/public/sitemap*.xml'))));

        $this->info("  → {$companyCount} companies + {$categoryCount} categories + {$jobCount} jobs + {$blogPostCount} blog posts → {$sitemapFileCount} sitemap files");
    }
}
