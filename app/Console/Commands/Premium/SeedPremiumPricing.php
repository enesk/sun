<?php

declare(strict_types=1);

namespace App\Console\Commands\Premium;

use Database\Seeders\PremiumPricingSeeder;
use Illuminate\Console\Command;

/**
 * Optionen fuer den PremiumPricingSeeder (#38). Ohne Optionen entspricht der
 * Aufruf `db:seed --class=PremiumPricingSeeder`.
 */
class SeedPremiumPricing extends Command
{
    protected $signature = 'premium:pricing:seed
        {--tenant=* : Portale (ID, UUID oder Domain); ohne Angabe alle mit sun-v2}
        {--dry-run : Nur anzeigen, nichts schreiben und in Stripe nichts anlegen}
        {--live : Einen Stripe-Live-Schluessel zulassen (nur mit Freigabe)}';

    protected $description = 'Setzt die Premium-Preise je Portal und legt die Stripe-Preise an';

    public function handle(): int
    {
        $seeder = new PremiumPricingSeeder;
        $seeder->tenantFilter = array_map('strval', (array) $this->option('tenant'));
        $seeder->dryRun = (bool) $this->option('dry-run');
        $seeder->allowLive = (bool) $this->option('live');
        $seeder->setContainer($this->laravel)->setCommand($this);

        try {
            $seeder->__invoke();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
