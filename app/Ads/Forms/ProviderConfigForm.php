<?php

declare(strict_types=1);

namespace App\Ads\Forms;

use App\Ads\AdProviderRegistry;
use App\Ads\Contracts\AdProvider;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Filament-Felder aus dem Schema eines Providers
 * ({@see AdProvider::configSchema()}, docs/ad-slots.md 5).
 *
 * Die einzige Stelle, die aus einer Felderbeschreibung Filament-Komponenten
 * baut. Sie wird an zwei Orten gebraucht — in der Werbemittel-Resource (#10)
 * und beim Inline-Anlegen im Konfigurationspanel (#8) —, und zwei
 * Uebersetzungen derselben Beschreibung liefen zwangslaeufig auseinander.
 *
 * Damit bleibt ein neuer Provider, was docs/ad-slots.md 5.3 verspricht: eine
 * Klasse plus eine Zeile in `config/ad-slots.php`, ohne Filament-Aenderung.
 *
 * `$prefix` ist der Statuspfad, unter dem `config_json` im Formular haengt —
 * in der Resource `config_json`, im Panel `unit.config_json`.
 */
final class ProviderConfigForm
{
    /**
     * @return list<Select|Textarea|TextInput>
     */
    public static function fields(mixed $provider, string $prefix = 'config_json'): array
    {
        $schema = self::provider($provider)?->configSchema() ?? [];
        $fields = [];

        foreach ($schema as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $path = "{$prefix}.{$name}";

            $component = match ((string) ($field['type'] ?? 'text')) {
                'textarea' => Textarea::make($path)->rows(10)->autosize(false),
                'number' => TextInput::make($path)->numeric(),
                'select' => Select::make($path)
                    ->options((array) ($field['options'] ?? []))
                    ->native(false),
                default => TextInput::make($path),
            };

            $fields[] = $component
                ->label((string) ($field['label'] ?? $name))
                ->required((bool) ($field['required'] ?? false))
                ->helperText($field['help'] ?? null)
                ->placeholder($field['placeholder'] ?? null)
                ->live(onBlur: true)
                ->rules(self::rulesOf($field))
                ->validationMessages(self::messagesOf($field));
        }

        return $fields;
    }

    /**
     * Regeln des Providers in Filament-Form. `required` bleibt draussen, das
     * setzt schon die Komponente; eine Closure-Regel muss von einer Closure
     * zurueckgegeben werden, sonst wertet Filament sie selbst aus.
     *
     * @param  array<string, mixed>  $field
     * @return list<string|Closure>
     */
    public static function rulesOf(array $field): array
    {
        $rules = [];

        foreach ((array) ($field['rules'] ?? []) as $rule) {
            if ($rule instanceof Closure) {
                $rules[] = fn (): Closure => $rule;

                continue;
            }

            if (is_string($rule) && ! in_array($rule, ['required', 'sometimes', 'nullable'], true)) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, string>
     */
    public static function messagesOf(array $field): array
    {
        $messages = [];

        foreach ((array) ($field['messages'] ?? []) as $rule => $message) {
            $messages[(string) $rule] = (string) $message;
        }

        return $messages;
    }

    /**
     * Satz ueber die gewaehlte Art, der im Formular ueber den Feldern steht.
     * Was nicht aus dem Schema hervorgeht: wo die Publisher-ID steht, dass
     * eigenes Markup abgeschottet laeuft — und die Auslieferungsregel aus
     * Ticket #29.
     *
     * Eigenes Markup darf Skripte mitbringen (`HtmlProvider::SANDBOX` traegt
     * `allow-scripts`); die Sandbox haelt es vom Portal-Origin fern, nicht vom
     * Netz. Ein solches Werbemittel kann also Requests an beliebige Dritte
     * stellen, waehrend die Datenschutzerklaerung bisher nur Google AdSense
     * nennt (docs/ad-slots.md 9.4). Darum steht die Regel dort, wo die Art
     * gewaehlt wird, und nicht nur in der Doku.
     */
    public static function hint(mixed $provider): string
    {
        return match ($provider) {
            'adsense' => __('Die Publisher-ID (data-ad-client) steht nicht hier, sondern in den Werbe-Einstellungen des Portals.'),
            'html' => __('Das Markup läuft in einem abgeschotteten Rahmen ohne Zugriff auf die Portalseite. Höchstens 20 kB.')
                .' '.self::htmlLegalRule(),
            default => '',
        };
    }

    /**
     * Auslieferungsregel fuer eigenes HTML (#29). Steht als eigene Methode da,
     * damit derselbe Satz an weiteren Stellen stehen kann, ohne abzuweichen.
     */
    public static function htmlLegalRule(): string
    {
        return __('Auslieferungsregel: Ein Werbemittel, das Skripte oder Inhalte Dritter nachlädt, darf erst live gehen, wenn die Datenschutzerklärung den Drittanbieter nennt — sie beschreibt bisher nur Google AdSense (Ticket #29).');
    }

    private static function provider(mixed $provider): ?AdProvider
    {
        if (! is_string($provider) || $provider === '') {
            return null;
        }

        return app(AdProviderRegistry::class)->get($provider);
    }
}
