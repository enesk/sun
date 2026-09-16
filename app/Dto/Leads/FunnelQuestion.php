<?php

declare(strict_types=1);

namespace App\Dto\Leads;

/**
 * Frage eines Funnel-Schritts (#25).
 *
 * Die Typen spiegeln App\Constants\QuestionType im Leadsystem. Kennt das
 * Portal einen Typ nicht, faellt die Frage beim Einlesen heraus (siehe
 * FunnelDefinition::fromArray()), statt den ganzen Dialog zu kippen.
 */
final readonly class FunnelQuestion
{
    public const KNOWN_TYPES = [
        'single_choice',
        'multi_choice',
        'text',
        'textarea',
        'number',
        'email',
        'phone',
        'date',
        'postal_code',
        'image_choice',
        'slider',
        'consent',
        'info',
    ];

    /**
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $meta
     * @param  list<FunnelOption>  $options
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $label,
        public ?string $helpText,
        public bool $required,
        public int $position,
        public array $validation,
        public array $meta,
        public array $options,
        public bool $systemProvided,
    ) {}

    public static function isKnownType(string $type): bool
    {
        return in_array($type, self::KNOWN_TYPES, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $systemFieldKeys
     */
    public static function fromArray(array $data, array $systemFieldKeys): self
    {
        $options = array_map(
            static fn (array $option): FunnelOption => FunnelOption::fromArray($option),
            array_values(array_filter((array) ($data['options'] ?? []), 'is_array')),
        );
        usort($options, static fn (FunnelOption $a, FunnelOption $b): int => $a->position <=> $b->position);

        $key = (string) ($data['field_key'] ?? '');

        return new self(
            key: $key,
            type: (string) ($data['type'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            helpText: isset($data['help_text']) ? (string) $data['help_text'] : null,
            required: (bool) ($data['required'] ?? false),
            position: (int) ($data['position'] ?? 0),
            validation: (array) ($data['validation'] ?? []),
            meta: (array) ($data['meta'] ?? []),
            options: $options,
            systemProvided: in_array($key, $systemFieldKeys, true),
        );
    }
}
