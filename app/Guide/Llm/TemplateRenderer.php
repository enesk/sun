<?php

declare(strict_types=1);

namespace App\Guide\Llm;

use App\Guide\Models\Central\PromptTemplate;
use Closure;
use InvalidArgumentException;

/**
 * Ersetzt {{var}}-Platzhalter in Prompt-Templates (#6).
 *
 * Bewusst kein Blade und keine Ausdruckssprache: Templatetext ist Redaktions-
 * inhalt aus prompt_templates (#13) und darf nichts ausfuehren koennen.
 * Werte werden roh eingesetzt, ohne HTML-Escaping — das Ziel ist ein Prompt,
 * kein Markup.
 */
final class TemplateRenderer
{
    /**
     * Erlaubt Buchstaben, Ziffern, Unterstrich und Punkt: {{tenant.name}}.
     */
    private const PLACEHOLDER = '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/';

    /**
     * Dieselbe Syntax fuer die Hervorhebung im Editor (#54): Quelltext ohne
     * Delimiter, direkt als JavaScript-RegExp verwendbar. Damit kann die
     * JS-Seite nicht vom Renderer abweichen.
     */
    public static function placeholderPattern(): string
    {
        return trim(self::PLACEHOLDER, '/');
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    public function render(string $text, array $vars): string
    {
        return $this->renderWith($text, $vars, static fn (string $value): string => $value);
    }

    /**
     * Wie render(), reicht aber jeden eingesetzten Wert durch $wrap.
     *
     * Fuer die Vorschau des Prompt-Editors (#43): sie bekommt bereits
     * escapten Text herein und laesst $wrap das Markup um den Wert legen.
     * Die Platzhalter-Syntax bleibt damit an einer Stelle.
     *
     * @param  array<string, mixed>  $vars
     * @param  Closure(string): string  $wrap
     */
    public function renderWith(string $text, array $vars, Closure $wrap): string
    {
        $flat = $this->flatten($vars);

        return (string) preg_replace_callback(
            self::PLACEHOLDER,
            function (array $match) use ($flat, $wrap): string {
                $key = $match[1];

                if (! array_key_exists($key, $flat)) {
                    throw new InvalidArgumentException("Prompt-Variable '{$key}' fehlt.");
                }

                return $wrap($flat[$key]);
            },
            $text,
        );
    }

    /**
     * System- und User-Prompt eines Templates in einem Durchgang.
     *
     * @param  array<string, mixed>  $vars
     * @return array{system: ?string, user: string}
     */
    public function renderTemplate(PromptTemplate $template, array $vars): array
    {
        $missing = $this->missingVariables($template, $vars);

        if ($missing !== []) {
            throw new InvalidArgumentException(
                "Template '{$template->key}' erwartet fehlende Variablen: ".implode(', ', $missing).'.',
            );
        }

        $system = $template->system_prompt;

        return [
            'system' => $system === null || trim($system) === '' ? null : $this->render($system, $vars),
            'user' => $this->render((string) $template->user_prompt, $vars),
        ];
    }

    /**
     * Alle im Templatetext verwendeten Platzhalter.
     *
     * @return array<int, string>
     */
    public function placeholders(string ...$texts): array
    {
        $names = [];

        foreach ($texts as $text) {
            preg_match_all(self::PLACEHOLDER, $text, $matches);
            $names = array_merge($names, $matches[1]);
        }

        return array_values(array_unique($names));
    }

    /**
     * Variablen, die das Template braucht, aber nicht bekommt.
     *
     * @param  array<string, mixed>  $vars
     * @return array<int, string>
     */
    public function missingVariables(PromptTemplate $template, array $vars): array
    {
        $flat = $this->flatten($vars);

        $required = $this->placeholders(
            (string) $template->system_prompt,
            (string) $template->user_prompt,
        );

        // variables_json dokumentiert die erwarteten Variablen (#13) und gilt
        // zusaetzlich, auch wenn ein Platzhalter im Text (noch) fehlt.
        foreach ($template->variables_json ?? [] as $key => $value) {
            $required[] = is_string($key) ? $key : (string) $value;
        }

        return array_values(array_filter(
            array_unique($required),
            fn (string $name) => ! array_key_exists($name, $flat),
        ));
    }

    /**
     * Verschachtelte Arrays werden mit Punkt-Notation flach gemacht; Listen und
     * Objekte ohne skalaren Wert landen als lesbares JSON im Prompt.
     *
     * @param  array<string, mixed>  $vars
     * @return array<string, string>
     */
    private function flatten(array $vars, string $prefix = ''): array
    {
        $flat = [];

        foreach ($vars as $key => $value) {
            $name = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat[$name] = $this->encode($value);

                if (! array_is_list($value)) {
                    $flat = array_merge($flat, $this->flatten($value, $name));
                }

                continue;
            }

            $flat[$name] = $this->stringify($value);
        }

        return $flat;
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'ja' : 'nein',
            is_scalar($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            $value instanceof \BackedEnum => (string) $value->value,
            default => $this->encode($value),
        };
    }

    private function encode(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
