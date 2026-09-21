<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Enums\GuideRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Legt ein Konto fuer das Ratgeber-Dashboard (Panel `content`) an oder gibt
 * einem bestehenden Konto eine Ratgeber-Rolle (#14).
 *
 * Angemeldet wird ueber den gewoehnlichen Login (Guard 'web'), deshalb ist
 * das Konto ein App\Models\User — ohne is_admin und ohne Tenant, es kommt
 * also nur ins Content-Panel. Ein bestehendes Konto behaelt sein Passwort,
 * solange --password nicht gesetzt ist.
 *
 *   php artisan guide:user:create inhaber@example.com
 *   php artisan guide:user:create redaktion@example.com --role=editor --name="Redaktion"
 */
class GuideUserCreate extends Command
{
    protected $signature = 'guide:user:create
        {email? : E-Mail-Adresse des Kontos}
        {--name= : Anzeigename, Standard: E-Mail-Adresse}
        {--role=owner : owner oder editor}
        {--password= : Passwort; ohne Angabe wird bei neuen Konten eines erzeugt}';

    protected $description = 'Legt ein Konto fuer das Ratgeber-Dashboard an oder setzt dessen Rolle (owner/editor)';

    public function handle(): int
    {
        $role = GuideRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error('Unbekannte Rolle. Erlaubt: '.implode(', ', array_column(GuideRole::cases(), 'value')));

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) ($this->argument('email') ?? $this->ask('E-Mail-Adresse'))));
        $password = $this->option('password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'max:255'], 'password' => ['nullable', 'string', 'min:12']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            return $this->updateExisting($user, $role, $password);
        }

        $generated = $password === null;
        $password ??= Str::password(20);

        $user = User::create([
            'name' => (string) ($this->option('name') ?: $email),
            'email' => $email,
            'password' => $password,
        ]);

        // guide_role und email_verified_at stehen bewusst nicht in $fillable.
        $user->forceFill([
            'guide_role' => $role->value,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Konto angelegt: {$email} ({$role->label()}).");

        if ($generated) {
            $this->line("Passwort: {$password}");
            $this->warn('Das Passwort wird nur jetzt angezeigt.');
        }

        $this->line('Anmeldung: '.route('content.login'));

        return self::SUCCESS;
    }

    private function updateExisting(User $user, GuideRole $role, ?string $password): int
    {
        if ($user->is_blocked) {
            $this->error("Das Konto {$user->email} ist gesperrt und kommt nicht ins Ratgeber-Dashboard.");

            return self::FAILURE;
        }

        $user->forceFill(['guide_role' => $role->value]);

        if ($password !== null) {
            $user->password = $password;
        }

        $user->save();

        $this->info("Bestehendes Konto {$user->email} hat jetzt die Rolle {$role->label()}.");

        if ($password === null) {
            $this->line('Das Passwort bleibt unverändert.');
        }

        return self::SUCCESS;
    }
}
