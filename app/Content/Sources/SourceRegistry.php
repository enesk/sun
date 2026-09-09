<?php

declare(strict_types=1);

namespace App\Content\Sources;

use App\Content\Enums\SourceFrequency;
use App\Content\Models\Central\SourceSetting;
use App\Content\Sources\Contracts\SourceConnector;
use App\Content\Sources\Exceptions\UnknownConnectorException;
use LogicException;

/**
 * Verzeichnis aller registrierten Quell-Connectoren (#7).
 *
 * Befuellt wird sie im ContentServiceProvider aus dem Container-Tag
 * 'content.source_connectors'. Ein neuer Connector braucht damit nur eine
 * Zeile im Provider, keine Aenderung an Runner, Job oder Command.
 */
final class SourceRegistry
{
    /** @var array<string, SourceConnector> */
    private array $connectors = [];

    /**
     * Konfiguration je Connector (#55). Wird beim ersten Bedarf geladen, nie
     * beim Registrieren: die Registry entsteht im ServiceProvider, dort darf
     * noch keine Abfrage laufen.
     *
     * @var array<string, SourceSetting>|null
     */
    private ?array $settings = null;

    /**
     * @param  iterable<SourceConnector>  $connectors
     */
    public function __construct(iterable $connectors = [])
    {
        foreach ($connectors as $connector) {
            $this->add($connector);
        }
    }

    public function add(SourceConnector $connector): void
    {
        $key = $connector->key();

        if (isset($this->connectors[$key]) && $connector::class !== $this->connectors[$key]::class) {
            throw new LogicException("Der Connector-Schluessel '{$key}' ist bereits vergeben.");
        }

        // Frequenz frueh validieren, damit ein Tippfehler nicht erst im
        // Scheduler auffaellt. Bewusst die Deklaration, nicht die wirksame
        // Frequenz: hier gibt es noch keine Datenbankverbindung.
        $this->declaredFrequencyOf($connector);

        $this->connectors[$key] = $connector;
    }

    public function has(string $key): bool
    {
        return isset($this->connectors[$key]);
    }

    public function get(string $key): SourceConnector
    {
        return $this->connectors[$key] ?? throw UnknownConnectorException::forKey($key, $this->keys());
    }

    /**
     * @return array<string, SourceConnector>
     */
    public function all(): array
    {
        return $this->connectors;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->connectors);
    }

    /**
     * Alle Connectoren, die in diesem Rhythmus laufen.
     *
     * @return array<string, SourceConnector>
     */
    public function dueFor(SourceFrequency $frequency): array
    {
        return array_filter(
            $this->connectors,
            fn (SourceConnector $connector) => $this->frequencyOf($connector) === $frequency,
        );
    }

    /**
     * Die wirksame Frequenz: Abweichung aus den Einstellungen, sonst die
     * Deklaration des Connectors (design/content-dashboard.md, §7a).
     *
     * dueFor() und damit die vier Scheduler-Sammellaeufe folgen automatisch.
     */
    public function frequencyOf(SourceConnector $connector): SourceFrequency
    {
        $declared = $this->declaredFrequencyOf($connector);
        $override = $this->settingFor($connector->key())?->frequencyOverride();

        // Die Deklaration ist die Obergrenze — sie bildet Quellentakt,
        // Ratenlimit und Kosten ab. Ein haeufigerer Wert in der Datenbank
        // (Altbestand, Konsole) wird still verworfen, nicht befolgt.
        if ($override !== null && $override->isAtLeastAsRareAs($declared)) {
            return $override;
        }

        return $declared;
    }

    /**
     * Der vom Connector im Code deklarierte Rhythmus, ohne Einstellungen.
     */
    public function declaredFrequencyOf(SourceConnector $connector): SourceFrequency
    {
        $declared = $connector->schedule();

        return SourceFrequency::tryFrom($declared) ?? throw new LogicException(
            "Connector '{$connector->key()}' deklariert die unbekannte Frequenz '{$declared}'."
        );
    }

    /**
     * Ist die Quelle eingeschaltet? Ohne Datensatz gilt "ja".
     */
    public function isEnabled(string $key): bool
    {
        return $this->settingFor($key)?->is_enabled ?? true;
    }

    public function settingFor(string $key): ?SourceSetting
    {
        return $this->settings()[$key] ?? null;
    }

    /**
     * @return array<string, SourceSetting>
     */
    public function settings(): array
    {
        return $this->settings ??= SourceSetting::allKeyed();
    }

    /**
     * Nach dem Speichern einer Einstellung: die Registry ist ein Singleton
     * und wuerde sonst im selben Request noch die alten Werte zeigen.
     */
    public function flushSettings(): void
    {
        $this->settings = null;
    }
}
