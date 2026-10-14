<?php

/*
 * Prueft nach `php artisan sun:db:pull`, dass lokal keine echten
 * Personendaten mehr liegen (#5, Abnahmekriterium).
 *
 * Gesucht wird in allen lokalen Datenbanken des Pulls (`sun` und
 * `tenant_%`) in jeder Spalte, die nach Name, E-Mail oder Telefon aussieht,
 * nach Mustern, die eine Anonymisierung nicht uebrig lassen darf:
 * fremde Mail-Domains (alles ausser *.test/example.*) und Telefonnummern
 * in der Form +49... — ausser der Dummy-Nummer.
 *
 * Aufruf:
 *   php artisan tinker --execute='require "scripts/db-pull-anonymisierung-pruefen.php";'
 *
 * Ergebnis steht im Text ("OK" / "BEFUND"), Exit-Code bleibt tinker.
 */

$central = DB::connection('central');
// Unterstrich ist in LIKE ein Platzhalter: ohne Escape trifft `tenant_%`
// auch die `tenants_*`-Datenbanken fremder Projekte auf diesem Rechner.
$prefix = rtrim((string) config('db-pull.tenant_database_prefix'), '_').'\\_';

$databases = array_map(
    fn (object $row): string => (string) $row->datenbank,
    $central->select(
        // Alias in Kleinschreibung: MySQL liefert SCHEMA_NAME, MariaDB klein.
        'select schema_name as datenbank from information_schema.schemata '
        .'where schema_name = ? or schema_name like ? order by schema_name',
        [$central->getDatabaseName(), $prefix.'%']
    )
);

// Spalten, die Personendaten tragen koennen: Mail-, Telefon- und
// Namensspalten plus die Freitexte der Anfragen.
$spaltenMuster = ['%email%', '%mail%', '%phone%', '%tel%', '%name%', '%message%', '%comment%'];
$befunde = [];
$geprueft = 0;

foreach ($databases as $database) {
    config(['database.connections.pruefung' => array_merge(
        (array) config('database.connections.central'),
        ['database' => $database]
    )]);
    DB::purge('pruefung');
    $connection = DB::connection('pruefung');

    $columns = $connection->select(
        'select table_name as tabelle, column_name as spalte from information_schema.columns '
        .'where table_schema = ? and data_type in (?, ?, ?, ?, ?) and ('
        .implode(' or ', array_fill(0, count($spaltenMuster), 'column_name like ?')).')',
        array_merge([$database, 'varchar', 'char', 'text', 'mediumtext', 'longtext'], $spaltenMuster)
    );

    foreach ($columns as $column) {
        $tabelle = (string) $column->tabelle;
        $spalte = (string) $column->spalte;
        $geprueft++;

        $treffer = (int) $connection->selectOne(
            "select count(*) as anzahl from `{$tabelle}` where "
            // fremde Mail-Domain
            ."(`{$spalte}` like '%@%.%' and `{$spalte}` not like '%@example.test' "
            ."and `{$spalte}` not like '%.test' and `{$spalte}` not like '%@example.%') "
            // echte Telefonnummer
            ."or (`{$spalte}` like '+49%' and `{$spalte}` <> '+49 000 0000000')"
        )->anzahl;

        if ($treffer > 0) {
            $befunde[] = sprintf('%s.%s.%s: %d Zeilen', $database, $tabelle, $spalte, $treffer);
        }
    }
}

echo sprintf("%d Datenbanken, %d Spalten geprueft.\n", count($databases), $geprueft);

if ($befunde === []) {
    echo "OK — keine echten E-Mail-Adressen oder Telefonnummern gefunden.\n";
} else {
    echo "BEFUND — Anonymisierung unvollstaendig:\n - ".implode("\n - ", $befunde)."\n";
}
