<?php

declare(strict_types=1);

namespace App\Companies\Deletion;

/**
 * Ein Schritt der Loeschpipeline (#8).
 *
 * Regeln fuer jede Umsetzung:
 *  * `key()` ist stabil — er steht in `config('profile-requests.deletion.steps')`
 *    und als `step` im Protokoll. Umbenennen heisst Protokollbruch.
 *  * `plan()` beschreibt in einem Satz, was der Schritt **tun wuerde**, und
 *    darf nichts aendern — das ist die Ausgabe von `--dry-run`.
 *  * `run()` muss wiederholbar sein. Ein zweiter Lauf auf demselben Profil
 *    darf nicht fehlschlagen, nur weniger zu tun haben.
 *  * `run()` wirft nicht; eine Exception fangt der Action ab und
 *    protokolliert sie, die restlichen Schritte laufen weiter.
 *
 * Neue Datenart = neuer Schritt. Nichts wird an einen bestehenden angehaengt.
 */
interface DeletionStep
{
    public function key(): string;

    public function label(): string;

    public function plan(DeletionContext $context): string;

    public function run(DeletionContext $context): StepResult;
}
