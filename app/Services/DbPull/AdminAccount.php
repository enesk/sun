<?php

declare(strict_types=1);

namespace App\Services\DbPull;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Fester Administrator nach der Anonymisierung (#5).
 *
 * Nach dem Pull ist jedes echte Konto ueberschrieben; ohne diesen Schritt
 * gibt es lokal keinen Weg mehr in Filament. Name, E-Mail und Passwort
 * stehen in config/db-pull.php (`anonymize_admin`), das Konto wird angelegt
 * oder — wenn es die E-Mail schon gibt — zurueckgesetzt.
 *
 * Geschrieben wird mit reinem SQL auf der frisch importierten zentralen
 * Datenbank: Eloquent wuerde Observer und Events feuern (Willkommensmail,
 * Tenant-Anlage), und die Modelle haengen an der Verbindung `central`, nicht
 * an der Zielverbindung des Pulls.
 */
class AdminAccount
{
    public function __construct(private readonly LocalMariaDb $local) {}

    /**
     * @return array{ok: bool, email: string, password: string, created: bool, role: bool, note: string}
     */
    public function ensure(string $database): array
    {
        /** @var array<string, string|null> $settings */
        $settings = (array) config('db-pull.anonymize_admin', []);

        $email = (string) ($settings['email'] ?? '');
        $name = (string) ($settings['name'] ?? 'Admin');
        $password = (string) ($settings['password'] ?: config('db-pull.anonymize_password'));
        $role = (string) ($settings['role'] ?? 'admin');

        $result = [
            'ok' => false,
            'email' => $email,
            'password' => $password,
            'created' => false,
            'role' => false,
            'note' => '',
        ];

        if ($email === '') {
            $result['note'] = 'anonymize_admin.email ist leer';

            return $result;
        }

        $connection = $this->local->connection($database);

        try {
            $columns = $this->columns($connection, $database, 'users');

            if ($columns === []) {
                $result['note'] = 'Tabelle users fehlt';

                return $result;
            }

            $existing = $connection->selectOne('select `id` from `users` where `email` = ? limit 1', [$email]);
            $values = $this->values($columns, $name, $email, $password);

            if ($existing === null) {
                $connection->table('users')->insert($values);
                $result['created'] = true;
                $userId = (int) $connection->getPdo()->lastInsertId();
            } else {
                $userId = (int) $existing->id;
                unset($values['created_at']);
                $connection->table('users')->where('id', $userId)->update($values);
            }

            $result['role'] = $this->assignRole($connection, $database, $userId, $role);
            $result['ok'] = true;
        } catch (Throwable $exception) {
            $result['note'] = $exception->getMessage();
        }

        return $result;
    }

    /**
     * Nur Spalten setzen, die es in dieser Datenbank auch gibt — der
     * Spaltensatz von `users` ist in #5 aelter als mancher lokale Stand.
     *
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function values(array $columns, string $name, string $email, string $password): array
    {
        $now = now()->toDateTimeString();

        $candidates = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => 1,
            'is_blocked' => 0,
            'email_verified_at' => $now,
            'remember_token' => null,
            'public_name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return array_filter(
            $candidates,
            fn (string $column): bool => in_array($column, $columns, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Spatie-Rolle ueber die Pivot-Tabelle, ohne das Paket zu laden: es
     * arbeitet auf der Standardverbindung, nicht auf der Zielverbindung des
     * Pulls. `teams` ist in config/permission.php aus, die Pivot-Zeile hat
     * also nur role_id, model_type und model_id.
     */
    private function assignRole(Connection $connection, string $database, int $userId, string $role): bool
    {
        $rolesTable = (string) config('permission.table_names.roles', 'roles');
        $pivotTable = (string) config('permission.table_names.model_has_roles', 'model_has_roles');

        if ($role === '' || $this->columns($connection, $database, $rolesTable) === [] || $this->columns($connection, $database, $pivotTable) === []) {
            return false;
        }

        $row = $connection->selectOne("select `id` from `{$rolesTable}` where `name` = ? limit 1", [$role]);

        if ($row === null) {
            return false;
        }

        $keys = [
            'role_id' => (int) $row->id,
            'model_type' => User::class,
            'model_id' => $userId,
        ];

        if ($connection->table($pivotTable)->where($keys)->exists()) {
            return true;
        }

        $connection->table($pivotTable)->insert($keys);

        return true;
    }

    /** @return array<int, string> */
    private function columns(Connection $connection, string $database, string $table): array
    {
        $rows = $connection->select(
            'select column_name as spalte from information_schema.columns '
            .'where table_schema = ? and table_name = ?',
            [$database, $table]
        );

        return array_map(fn (object $row): string => (string) $row->spalte, $rows);
    }
}
