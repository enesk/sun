<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Models\Portal\Company;
use App\Support\TenantCache;
use App\Themes\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Anmeldeseite im Theme sun-v2 (Vorlage elektrikerportal-login.html):
 * Seitentext mit echter Betriebszahl. LoginController bleibt unveraendert.
 */
final class LoginViewComposer
{
    public function __construct(private readonly ThemeManager $themes) {}

    public function compose(View $view): void
    {
        if ($this->themes->active()?->slug !== HomeViewComposer::THEME) {
            return;
        }

        $config = config('themes.sun-v2.login');
        $companies = Cache::remember(TenantCache::key('sun-v2.login.companies'), 3600, fn (): int => Company::active()->count());

        $view->with('sunLogin', [
            ...$config,
            'text' => strtr($config['text'], [
                ':companies' => number_format($companies, 0, ',', '.'),
                ':portal' => (string) (tenant()?->name ?? config('app.name')),
            ]),
        ]);
    }
}
