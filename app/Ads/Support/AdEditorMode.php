<?php

declare(strict_types=1);

namespace App\Ads\Support;

/**
 * Laeuft diese Anfrage im Editor-Modus? (SUN-AD-005, Ticket #6)
 *
 * Die Komponente (#5) muss den Editor-Modus kennen, bevor es ihn gibt: im
 * Editor rendert jeder Anker seinen Rahmen, auch ohne Belegung, und der
 * Resolver umgeht den Cache. Damit #6 nur noch den signierten Token pruefen
 * und {@see enable()} rufen muss, steht der Schalter hier und nicht in der
 * Komponente.
 *
 * Bewusst am Container und nicht an der Session: der Modus gilt genau fuer
 * eine Anfrage und darf nicht ueber den Request hinaus kleben — eine im
 * Editor gerenderte Seite ist noindex und ohne Tracking.
 */
final class AdEditorMode
{
    private const KEY = 'ads.editor_mode';

    /*
     * `scoped` und nicht `instance` (#6): eine gebundene Instanz bleibt im
     * Container, bis der Prozess endet. Unter FPM ist das pro Anfrage, in
     * einem Langlaeufer (Octane, Queue-Worker, Smoketest-Skript) aber nicht
     * — dort wuerde der Editor-Modus der ersten Anfrage in jeder folgenden
     * weiterlaufen, und eine ausgelieferte Seite zeigte Anker-Rahmen statt
     * Werbung. `scoped` wird nach jeder Anfrage mit abgeraeumt.
     */
    public static function enable(): void
    {
        app()->scoped(self::KEY, fn (): bool => true);
    }

    public static function disable(): void
    {
        app()->scoped(self::KEY, fn (): bool => false);
    }

    public static function active(): bool
    {
        return app()->bound(self::KEY) && app(self::KEY) === true;
    }
}
