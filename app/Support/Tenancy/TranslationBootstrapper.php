<?php

namespace App\Support\Tenancy;

use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Leert die im Speicher gehaltenen Sprachgruppen bei jedem Tenant-Wechsel (#6).
 *
 * Der Translator merkt sich einmal geladene Gruppen samt der Tenant-Overrides
 * aus dem TenantOverrideLoader. In langlebigen Prozessen (Queue-Worker, Octane)
 * saehe Tenant B sonst die Texte von Tenant A. Die Branchenbegriffe liest der
 * TenantTranslator ohnehin je Aufruf frisch.
 */
class TranslationBootstrapper implements TenancyBootstrapper
{
    public function bootstrap(Tenant $tenant)
    {
        $this->flush();
    }

    public function revert()
    {
        $this->flush();
    }

    protected function flush(): void
    {
        app('translator')->setLoaded([]);
    }
}
