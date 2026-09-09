<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Enums\ContentRole;
use App\Content\Models\Central\ContentUser;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Legt einen Redaktions-Account fuer das Content-Panel an (#4).
 *
 * Es gibt bewusst keine Selbstregistrierung unter /content: die Accounts
 * werden auf dem Server angelegt. Ohne diesen Befehl gibt es nach der
 * Migration keinen Weg in das Panel.
 */
class ContentUserCreate extends Command
{
    protected $signature = 'content:user:create
        {--name= : Anzeigename}
        {--email= : E-Mail-Adresse, dient als Login}
        {--password= : Passwort; ohne Angabe wird verdeckt abgefragt}
        {--role=owner : owner oder editor}
        {--tenants= : Kommaliste von Tenant-IDs; leer = alle Portale}
        {--force : Vorhandenen Account mit dieser E-Mail aktualisieren}';

    protected $description = 'Legt einen Redaktions-Account (content_users) fuer das Content-Panel an';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('E-Mail');
        $password = $this->option('password') ?: $this->secret('Passwort');

        $role = ContentRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error('Unbekannte Rolle. Erlaubt: '.implode(', ', array_column(ContentRole::cases(), 'value')));

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $tenantIds = $this->tenantIds();

        if ($tenantIds === false) {
            return self::FAILURE;
        }

        $existing = ContentUser::query()->where('email', $email)->first();

        if ($existing !== null && ! $this->option('force')) {
            $this->error("Es gibt bereits einen Account mit der E-Mail {$email}. Mit --force aktualisieren.");

            return self::FAILURE;
        }

        $attributes = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role->value,
            'is_active' => true,
            'tenant_ids_json' => $tenantIds,
        ];

        if ($existing !== null) {
            $existing->fill($attributes)->save();
            $user = $existing;
        } else {
            $user = ContentUser::query()->create($attributes);
        }

        $this->info(sprintf(
            '%s: %s (%s) als %s, Portale: %s',
            $existing !== null ? 'Aktualisiert' : 'Angelegt',
            $user->name,
            $user->email,
            $role->value,
            $tenantIds === null ? 'alle' : implode(', ', $tenantIds),
        ));

        $this->line('Login: '.url(config('content.panel.path', 'content').'/login'));

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>|null|false false = ungueltige Eingabe
     */
    private function tenantIds(): array|null|false
    {
        $raw = trim((string) $this->option('tenants'));

        if ($raw === '') {
            return null;
        }

        $ids = array_values(array_unique(array_map(
            'intval',
            array_filter(array_map('trim', explode(',', $raw)), fn (string $value) => $value !== ''),
        )));

        $existing = Tenant::query()->whereIn('id', $ids)->pluck('id')->map('intval')->all();
        $missing = array_diff($ids, $existing);

        if ($missing !== []) {
            $this->error('Unbekannte Tenant-IDs: '.implode(', ', $missing));

            return false;
        }

        return $ids;
    }
}
