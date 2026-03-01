<?php

declare(strict_types=1);

namespace App\TenantDatabaseManagers;

use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\TenantDatabaseManagers\PermissionControlledMySQLDatabaseManager as BaseManager;

/**
 * Custom DB-User-Isolation Manager.
 *
 * Jeder Tenant bekommt einen eigenen MySQL-User, der NUR auf seine
 * eigene Datenbank zugreifen kann. Der User wird auf localhost
 * beschränkt und hat keine GRANT OPTION.
 *
 * Grants sind bewusst restriktiv: kein DROP, kein CREATE, kein ALTER
 * auf Schema-Ebene — nur DML + Stored Procedures.
 */
class PermissionControlledMySQLDatabaseManager extends BaseManager
{
    /**
     * Restriktive Grants — nur was die App im Betrieb braucht.
     * Keine DDL-Privilegien (ALTER, CREATE, DROP) — Migrationen
     * laufen über die zentrale root-Connection.
     */
    public static $grants = [
        'SELECT',
        'INSERT',
        'UPDATE',
        'DELETE',
        'EXECUTE',
        'CREATE TEMPORARY TABLES',
        'LOCK TABLES',
        'INDEX',
    ];

    /**
     * User auf localhost beschränken statt '%' (Wildcard).
     * Verhindert Remote-Zugriff mit Tenant-Credentials.
     */
    public function createUser(DatabaseConfig $databaseConfig): bool
    {
        $database = $databaseConfig->getName();
        $username = $databaseConfig->getUsername();
        $password = $databaseConfig->getPassword();

        // Tenant-User nur auf localhost erstellen (restriktiv)
        $this->database()->statement(
            "CREATE USER IF NOT EXISTS `{$username}`@`localhost` IDENTIFIED BY '{$password}'"
        );

        $grants = implode(', ', static::$grants);

        $this->database()->statement(
            "GRANT {$grants} ON `{$database}`.* TO `{$username}`@`localhost`"
        );

        // Zentralen DB-User (z.B. 'sunny') ebenfalls auf die neue Tenant-DB berechtigen.
        // In Dev ist das root (hat sowieso Zugriff), auf Live ist es ein eingeschränkter User
        // der explizite Grants braucht.
        $centralConnection = config('tenancy.database.central_connection', 'central');
        $centralUsername = config("database.connections.{$centralConnection}.username");

        if ($centralUsername && $centralUsername !== 'root') {
            $this->database()->statement(
                "GRANT ALL PRIVILEGES ON `{$database}`.* TO `{$centralUsername}`@`%`"
            );
            $this->database()->statement(
                "GRANT ALL PRIVILEGES ON `{$database}`.* TO `{$centralUsername}`@`localhost`"
            );
        }

        $this->database()->statement('FLUSH PRIVILEGES');

        return true;
    }

    public function deleteUser(DatabaseConfig $databaseConfig): bool
    {
        $username = $databaseConfig->getUsername();

        return $this->database()->statement(
            "DROP USER IF EXISTS `{$username}`@`localhost`"
        );
    }

    public function userExists(string $username): bool
    {
        return (bool) $this->database()->select(
            "SELECT 1 FROM mysql.user WHERE user = ? AND host = 'localhost'",
            [$username]
        );
    }

    /**
     * Connection-Config: Immer zentrale (root) Credentials nutzen.
     *
     * Stancl's DatabaseConfig::tenantConfig() injiziert die gespeicherten
     * Tenant-Credentials (db_username, db_password) in $baseConfig BEVOR
     * diese Methode aufgerufen wird. Da unser Tenant-User keine DDL-Rechte
     * hat (kein CREATE, ALTER, DROP), schlagen Migrations fehl.
     *
     * Fix: Wir überschreiben username/password explizit mit den zentralen
     * Credentials. Schema-Operationen (Migrations) und Runtime-Queries
     * laufen beide über den Root-User. Die DB-User-Isolation (tn_xxx) dient
     * als zusätzliche Sicherheitsschicht für direkten MySQL-Zugriff,
     * nicht für die Laravel-Application.
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $centralConnection = config('tenancy.database.central_connection', 'central');

        $baseConfig['database'] = $databaseName;
        $baseConfig['username'] = config("database.connections.{$centralConnection}.username");
        $baseConfig['password'] = config("database.connections.{$centralConnection}.password");

        return $baseConfig;
    }
}
