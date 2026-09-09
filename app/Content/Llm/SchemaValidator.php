<?php

declare(strict_types=1);

namespace App\Content\Llm;

/**
 * Minimaler JSON-Schema-Pruefer fuer die Antworten des LlmClient (#6).
 *
 * Die Anthropic-API validiert Tool-Eingaben selbst, aber nicht jede Regel
 * (Laengen, enum-Werte, verschachtelte required-Listen) wird garantiert
 * durchgesetzt. Diese Pruefung ist die zweite Instanz: Verstoesse gehen als
 * Klartext in den Retry-Prompt zurueck.
 *
 * Unterstuetzt die Teilmenge, die in unseren Templates vorkommt: type,
 * required, properties, items, enum, minItems/maxItems, minLength/maxLength,
 * minimum/maximum, additionalProperties=false.
 */
final class SchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string> leer = gueltig
     */
    public function validate(mixed $value, array $schema, string $path = '$'): array
    {
        if ($schema === []) {
            return [];
        }

        $errors = $this->checkType($value, $schema, $path);

        if ($errors !== []) {
            return $errors;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $allowed = implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : '?', $schema['enum']));

            return ["{$path}: '".(is_scalar($value) ? (string) $value : '?')."' ist kein erlaubter Wert ({$allowed})."];
        }

        return match (true) {
            is_array($value) && array_is_list($value) => $this->validateList($value, $schema, $path),
            is_array($value) => $this->validateObject($value, $schema, $path),
            is_string($value) => $this->validateString($value, $schema, $path),
            is_int($value) || is_float($value) => $this->validateNumber($value, $schema, $path),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function checkType(mixed $value, array $schema, string $path): array
    {
        $types = $schema['type'] ?? null;

        if ($types === null) {
            return [];
        }

        foreach ((array) $types as $type) {
            if ($this->matchesType($value, (string) $type)) {
                return [];
            }
        }

        $expected = implode('|', (array) $types);

        return ["{$path}: erwartet {$expected}, geliefert ".$this->typeOf($value).'.'];
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_array($value) && ! array_is_list($value) || $value === [],
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => true,
        };
    }

    private function typeOf(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) => array_is_list($value) ? 'array' : 'object',
            default => get_debug_type($value),
        };
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function validateObject(array $value, array $schema, string $path): array
    {
        $errors = [];
        $properties = (array) ($schema['properties'] ?? []);

        foreach ((array) ($schema['required'] ?? []) as $required) {
            if (! array_key_exists((string) $required, $value)) {
                $errors[] = "{$path}: Pflichtfeld '{$required}' fehlt.";
            }
        }

        foreach ($value as $key => $item) {
            if (! isset($properties[$key])) {
                if (($schema['additionalProperties'] ?? true) === false) {
                    $errors[] = "{$path}: unerwartetes Feld '{$key}'.";
                }

                continue;
            }

            $errors = array_merge($errors, $this->validate($item, (array) $properties[$key], "{$path}.{$key}"));
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $value
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function validateList(array $value, array $schema, string $path): array
    {
        $errors = [];
        $count = count($value);

        if (isset($schema['minItems']) && $count < (int) $schema['minItems']) {
            $errors[] = "{$path}: mindestens {$schema['minItems']} Eintraege erwartet, geliefert {$count}.";
        }

        if (isset($schema['maxItems']) && $count > (int) $schema['maxItems']) {
            $errors[] = "{$path}: hoechstens {$schema['maxItems']} Eintraege erlaubt, geliefert {$count}.";
        }

        if (! isset($schema['items']) || ! is_array($schema['items'])) {
            return $errors;
        }

        foreach ($value as $index => $item) {
            $errors = array_merge($errors, $this->validate($item, $schema['items'], "{$path}[{$index}]"));
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function validateString(string $value, array $schema, string $path): array
    {
        $errors = [];
        $length = mb_strlen($value);

        if (isset($schema['minLength']) && $length < (int) $schema['minLength']) {
            $errors[] = "{$path}: mindestens {$schema['minLength']} Zeichen erwartet, geliefert {$length}.";
        }

        if (isset($schema['maxLength']) && $length > (int) $schema['maxLength']) {
            $errors[] = "{$path}: hoechstens {$schema['maxLength']} Zeichen erlaubt, geliefert {$length}.";
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function validateNumber(int|float $value, array $schema, string $path): array
    {
        $errors = [];

        if (isset($schema['minimum']) && $value < $schema['minimum']) {
            $errors[] = "{$path}: Wert {$value} unterschreitet Minimum {$schema['minimum']}.";
        }

        if (isset($schema['maximum']) && $value > $schema['maximum']) {
            $errors[] = "{$path}: Wert {$value} ueberschreitet Maximum {$schema['maximum']}.";
        }

        return $errors;
    }
}
