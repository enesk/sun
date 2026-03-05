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
use Spatie\Sitemap\Tags\Url;

class GenerateTenantSitemap extends Command
{
    protected $signature = 'tenants:generate-sitemap {--tenant= : Specific tenant ID (optional, runs for all if omitted)}';

    protected $description = 'Generate sitemap.xml and robots.txt for each tenant portal';

    public function handle(): void
    {
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

        $tenant->run(function () use ($baseUrl, $domain) {
            $sitemap = Sitemap::create();

            // Static pages
            $sitemap->add(
                Url::create("{$baseUrl}/")
                    ->setPriority(1.0)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            );

            $sitemap->add(
                Url::create("{$baseUrl}/firmen")
                    ->setPriority(0.9)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            );

            $sitemap->add(
                Url::create("{$baseUrl}/kategorien")
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            );

            $sitemap->add(
                Url::create("{$baseUrl}/eintragen")
                    ->setPriority(0.6)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            );

            $sitemap->add(
                Url::create("{$baseUrl}/impressum")
                    ->setPriority(0.3)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            );

            $sitemap->add(
                Url::create("{$baseUrl}/datenschutz")
                    ->setPriority(0.3)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            );

            // FAQ page (only if FAQs exist)
            $faqCount = FAQ::active()->count();
            if ($faqCount > 0) {
                $sitemap->add(
                    Url::create("{$baseUrl}/faq")
                        ->setPriority(0.7)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                );
            }

            // Company pages (chunked for memory efficiency with 12.000+ entries)
            Company::active()
                ->select(['id', 'slug', 'updated_at', 'is_premium'])
                ->orderBy('id')
                ->chunk(500, function ($companies) use ($sitemap, $baseUrl) {
                    foreach ($companies as $company) {
                        $sitemap->add(
                            Url::create("{$baseUrl}/{$company->url_slug}")
                                ->setPriority($company->is_premium ? 0.8 : 0.7)
                                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                                ->setLastModificationDate($company->updated_at)
                        );
                    }
                });

            // Jobs index page
            $sitemap->add(
                Url::create("{$baseUrl}/jobs")
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            );

            // Individual job pages (active + published only)
            $jobs = Job::active()
                ->published()
                ->select(['slug', 'published_at', 'company_id'])
                ->with(['company:id,is_premium'])
                ->get();

            foreach ($jobs as $job) {
                $sitemap->add(
                    Url::create("{$baseUrl}/jobs/{$job->slug}")
                        ->setPriority($job->company?->is_premium ? 0.7 : 0.6)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setLastModificationDate($job->published_at)
                );
            }

            // Blog index page
            $blogPostCount = Post::published()->count();
            if ($blogPostCount > 0) {
                $sitemap->add(
                    Url::create("{$baseUrl}/ratgeber")
                        ->setPriority(0.8)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                );

                // Individual blog posts
                Post::published()
                    ->select(['slug', 'published_at', 'updated_at'])
                    ->orderByDesc('published_at')
                    ->chunk(200, function ($posts) use ($sitemap, $baseUrl) {
                        foreach ($posts as $post) {
                            $sitemap->add(
                                Url::create("{$baseUrl}/ratgeber/{$post->slug}")
                                    ->setPriority(0.7)
                                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                                    ->setLastModificationDate($post->updated_at ?? $post->published_at)
                            );
                        }
                    });
            }

            // Category pages
            $categories = Category::select(['slug', 'updated_at'])->get();
            foreach ($categories as $category) {
                $sitemap->add(
                    Url::create("{$baseUrl}/kategorien/{$category->slug}")
                        ->setPriority(0.7)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setLastModificationDate($category->updated_at)
                );
            }

            // Write sitemap to tenant-isolated storage
            $sitemapDir = storage_path('app/public');
            if (!is_dir($sitemapDir)) {
                mkdir($sitemapDir, 0755, true);
            }
            $sitemap->writeToFile("{$sitemapDir}/sitemap.xml");

            // Write robots.txt to tenant storage
            $robotsContent = "User-agent: *\nAllow: /\nDisallow: /firmenprofil/\nDisallow: /verwaltung/\nDisallow: /login\nDisallow: /register\n\nSitemap: {$baseUrl}/sitemap.xml\n";
            file_put_contents("{$sitemapDir}/robots.txt", $robotsContent);
        });

        $companyCount = $tenant->run(fn () => Company::active()->count());
        $categoryCount = $tenant->run(fn () => Category::count());
        $jobCount = $tenant->run(fn () => Job::active()->published()->count());
        $blogPostCount = $tenant->run(fn () => Post::published()->count());
        $faqExists = $tenant->run(fn () => FAQ::active()->exists());

        $staticPages = 7 + ($faqExists ? 1 : 0);
        $this->info("  → {$companyCount} companies + {$categoryCount} categories + {$jobCount} jobs + {$blogPostCount} blog posts + {$staticPages} static pages");
    }
}
