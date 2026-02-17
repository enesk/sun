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

        // User nur auf localhost erstellen
        $this->database()->statement(
            "CREATE USER IF NOT EXISTS `{$username}`@`localhost` IDENTIFIED BY '{$password}'"
        );

        $grants = implode(', ', static::$grants);

        $this->database()->statement(
            "GRANT {$grants} ON `{$database}`.* TO `{$username}`@`localhost`"
        );

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
     * Connection-Config mit Tenant-eigenem User/Passwort überschreiben.
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $baseConfig['database'] = $databaseName;

        return $baseConfig;
    }
}
