<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Import\TopicListAssigner;
use App\Guide\Models\Central\TopicList;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Weist eine Themenliste Portalen zu und legt die Themen an (#6).
 * Wiederholbar: bereits angelegte Themen werden nur fortgeschrieben.
 *
 *   php artisan guide:topics:assign 3 --tenant=7 --tenant=sanitaerfinder.com
 *   php artisan guide:topics:assign "Sanitaer Grundliste" --tenant='*'
 */
class GuideTopicsAssign extends Command
{
    protected $signature = 'guide:topics:assign
        {list : ID oder Name der Themenliste}
        {--tenant=* : Portale (ID, UUID oder Domain); "*" fuer alle}';

    protected $description = 'Weist eine Themenliste des Ratgebersystems Portalen zu und legt die Themen an';

    public function handle(TopicListAssigner $assigner): int
    {
        $list = $this->topicList((string) $this->argument('list'));

        if ($list === null) {
            $this->error("Themenliste nicht gefunden: {$this->argument('list')}");

            return self::FAILURE;
        }

        $tenants = $this->tenants();

        if ($tenants === null) {
            return self::FAILURE;
        }

        $rows = [];
        $failed = false;

        foreach ($tenants as $tenant) {
            $report = $assigner->assign($list, $tenant);
            $failed = $failed || $report->hasErrors();

            $rows[] = [
                (int) $tenant->getKey(),
                (string) $tenant->name,
                $report->imported,
                $report->updated,
                $report->skippedDuplicates,
                count($report->errors),
            ];

            foreach ($report->errors as $error) {
                $this->warn(sprintf('[%s] Position %d: %s', (string) $tenant->name, $error['line'], $error['reason']));
            }
        }

        $this->table(['ID', 'Portal', 'Angelegt', 'Aktualisiert', 'Übersprungen', 'Fehler'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function topicList(string $needle): ?TopicList
    {
        if (ctype_digit($needle)) {
            return TopicList::query()->find((int) $needle);
        }

        return TopicList::query()->where('name', $needle)->first();
    }

    /**
     * @return Collection<int, Tenant>|null null bei fehlender oder unbekannter Angabe
     */
    private function tenants(): ?Collection
    {
        $needles = array_values(array_filter(array_map('trim', (array) $this->option('tenant'))));

        if ($needles === []) {
            $this->error('Mindestens ein --tenant angeben (ID, UUID, Domain oder "*" für alle).');

            return null;
        }

        if (in_array('*', $needles, true)) {
            /** @var Collection<int, Tenant> $all */
            $all = Tenant::query()->orderBy('id')->get()->toBase();

            return $all;
        }

        $tenants = collect();

        foreach ($needles as $needle) {
            $tenant = $this->resolve($needle);

            if ($tenant === null) {
                $this->error("Portal nicht gefunden: {$needle}");

                return null;
            }

            $tenants->put($tenant->getKey(), $tenant);
        }

        return $tenants->values();
    }

    private function resolve(string $needle): ?Tenant
    {
        if (ctype_digit($needle)) {
            return Tenant::query()->find((int) $needle);
        }

        return Tenant::query()
            ->where('uuid', $needle)
            ->orWhere('domain', $needle)
            ->first();
    }
}
