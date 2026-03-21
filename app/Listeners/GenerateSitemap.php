<?php

namespace App\Listeners;

use App\Events\SitemapChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;

class GenerateSitemap implements ShouldQueue
{
    public int $timeout = 300;

    public function handle(SitemapChanged $event): void
    {
        set_time_limit(300);
        Artisan::call('app:generate-sitemap');
    }
}
