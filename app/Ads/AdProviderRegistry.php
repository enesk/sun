<?php

declare(strict_types=1);

namespace App\Ads;

use App\Ads\Contracts\AdProvider;
use App\Ads\Models\AdUnit;
use Illuminate\Contracts\Container\Container;

/**
 * Nachschlagewerk der Provider (docs/ad-slots.md 5, Ticket #4).
 *
 * Die Liste steht in `config('ad-slots.providers')`: Schluessel = Wert in
 * `ad_units.provider`, Wert = Klasse. Ein neuer Provider ist eine Klasse plus
 * eine Zeile dort — Models, Resolver, Komponente und Editor bleiben
 * unberuehrt, weil sie ausschliesslich hier fragen.
 *
 * Aufgeloeste Provider werden gehalten; sie sind zustandslos (alles
 * Veraenderliche steht im AdRenderContext), ein zweiter Aufbau waere nur
 * Arbeit. Deshalb ist die Registry als Singleton gebunden.
 */
final class AdProviderRegistry
{
    /** @var array<string, AdProvider> */
    private array $resolved = [];

    public function __construct(private readonly Container $app) {}

    /**
     * Registrierte Schluessel, in Konfigurationsreihenfolge.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->map());
    }

    public function has(string $key): bool
    {
        return isset($this->map()[$key]);
    }

    public function get(string $key): ?AdProvider
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $class = $this->map()[$key] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $provider = $this->app->make($class);

        if (! $provider instanceof AdProvider) {
            return null;
        }

        // Ein Provider, der sich anders nennt als sein Eintrag, wuerde beim
        // Speichern von ad_units.provider stillschweigend daneben greifen.
        if ($provider->key() !== $key) {
            throw new \LogicException(
                "Der Provider {$class} meldet den Schluessel '{$provider->key()}', ".
                "steht in config/ad-slots.php aber unter '{$key}'."
            );
        }

        return $this->resolved[$key] = $provider;
    }

    /**
     * Provider des Werbemittels; null, wenn der Eintrag in der Spalte nicht
     * (mehr) registriert ist — dann wird das Werbemittel nicht ausgespielt.
     */
    public function for(AdUnit $unit): ?AdProvider
    {
        return $this->get((string) $unit->provider);
    }

    /**
     * @return array<string, AdProvider>
     */
    public function all(): array
    {
        $providers = [];

        foreach ($this->keys() as $key) {
            $provider = $this->get($key);

            if ($provider !== null) {
                $providers[$key] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Auswahlliste fuer Filament (#8/#10) — ersetzt AdProviderType::options().
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return array_map(fn (AdProvider $provider) => $provider->label(), $this->all());
    }

    /**
     * @return array<string, class-string>
     */
    private function map(): array
    {
        $map = [];

        foreach ((array) config('ad-slots.providers', []) as $key => $class) {
            if (is_string($key) && is_string($class)) {
                $map[$key] = $class;
            }
        }

        return $map;
    }
}
