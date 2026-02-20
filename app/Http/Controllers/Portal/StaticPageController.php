<?php

namespace App\Http\Controllers\Portal;

use App\Constants\TenantConfigConstants;
use App\Http\Controllers\Controller;
use App\Services\TenantBrandingService;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    public function __construct(
        private TenantBrandingService $branding,
    ) {}

    public function impressum(): View
    {
        $content = $this->resolveContent(TenantConfigConstants::IMPRESSUM);

        return view('pages.impressum', compact('content'));
    }

    public function datenschutz(): View
    {
        $content = $this->resolveContent(TenantConfigConstants::DATENSCHUTZ);

        return view('pages.datenschutz', compact('content'));
    }

    private function resolveContent(string $key): ?string
    {
        $tenant = tenant();
        $raw = $this->branding->get($tenant, $key);

        if (!$raw) {
            return null;
        }

        return $this->branding->resolveLegalPlaceholders($raw, $tenant);
    }
}
