<?php

declare(strict_types=1);

namespace App\Ads\Providers;

use App\Ads\Contracts\AdProvider;
use App\Ads\Dto\AdRenderContext;
use App\Ads\Models\AdUnit;
use App\Ads\Support\AdSlotCatalog;
use Illuminate\Support\Facades\Validator;

/**
 * Gemeinsame Mechanik aller Provider (Ticket #4): Pruefung und Normalisierung
 * werden aus {@see AdProvider::configSchema()} abgeleitet, damit ein neuer
 * Provider nur noch Felder und Markup beschreibt.
 *
 * Wer etwas anderes braucht, ueberschreibt die Methode — das Interface ist
 * die Grenze, nicht diese Klasse.
 */
abstract class AbstractAdProvider implements AdProvider
{
    /**
     * Das `format` kennt jeder Provider: es kommt aus
     * `config('ad-slots.formats')` und der Anker schraenkt die Auswahl
     * spaeter weiter ein (AdSlotCatalog::formatsOf()).
     *
     * @return array<string, mixed>
     */
    protected function formatField(): array
    {
        return [
            'name' => 'format',
            'label' => __('Anzeigenformat'),
            'type' => 'select',
            'required' => false,
            'options' => collect((array) config('ad-slots.formats', []))
                ->map(fn (array $format, string $key) => (string) ($format['label'] ?? $key))
                ->all(),
            'rules' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', AdSlotCatalog::formatKeys())],
            'messages' => ['in' => __('Unbekanntes Anzeigenformat.')],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function validateConfig(array $config): array
    {
        $rules = [];
        $messages = [];

        foreach ($this->configSchema() as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $rules[$name] = $field['rules'] ?? ($field['required'] ?? false ? ['required'] : ['sometimes', 'nullable']);

            foreach ((array) ($field['messages'] ?? []) as $rule => $message) {
                $messages["{$name}.{$rule}"] = $message;
            }
        }

        return array_values(Validator::make($config, $rules, $messages)->errors()->all());
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeConfig(array $config): array
    {
        $clean = [];

        foreach ($this->configSchema() as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name === '' || ! array_key_exists($name, $config)) {
                continue;
            }

            $value = $config[$name];

            if ($value === null || $value === '') {
                continue;
            }

            $clean[$name] = ($field['type'] ?? null) === 'number' ? (int) $value : $value;
        }

        // Ohne Angabe gilt `responsive` — der Anker laesst es immer zu.
        if ($this->hasField('format')) {
            $clean['format'] = (string) ($clean['format'] ?? 'responsive');
        }

        return $clean;
    }

    public function requiresConsent(AdUnit $unit): bool
    {
        return true;
    }

    public function supports(AdUnit $unit, AdRenderContext $context): bool
    {
        return $this->validateConfig((array) $unit->config_json) === [];
    }

    protected function hasField(string $name): bool
    {
        foreach ($this->configSchema() as $field) {
            if (($field['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attribut-Liste zu HTML — eine Stelle, damit kein Provider das Maskieren
     * vergisst. `true` wird zum alleinstehenden Attribut, `false`/null
     * entfaellt.
     *
     * @param  array<string, string|int|bool|null>  $attributes
     */
    protected function attributes(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $parts[] = e($name);

                continue;
            }

            $parts[] = e($name).'="'.e((string) $value).'"';
        }

        return implode(' ', $parts);
    }
}
