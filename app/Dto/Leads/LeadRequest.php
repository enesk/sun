<?php

declare(strict_types=1);

namespace App\Dto\Leads;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Abgeschickte Anfrage aus dem Profil-Dialog (#9), geprueft gegen den
 * Funnel-Snapshot des Portals. Beim Oeffnen des Dialogs gibt es noch keine
 * Anfrage, dort entscheidet LeadRoutingService ohne LeadRequest.
 *
 * Die Schritte vor dem Kontaktschritt hat das Leadsystem bereits validiert
 * (PATCH answers); hier zaehlen nur Schluessel aus dem Snapshot, Pflichtfelder
 * prueft der Kontaktschritt selbst. Fehler kommen im Format des Leadsystems
 * (answers.<key>), damit lead-dialog.js sie unter die Felder schreibt.
 */
final readonly class LeadRequest
{
    private const MAX_TEXT = 2000;

    private const CHOICE_TYPES = ['single_choice', 'multi_choice', 'image_choice'];

    /**
     * @param  list<array{key: string, label: string, type: string, value: mixed, value_label: string|null}>  $answers
     */
    public function __construct(
        public array $answers,
        public ?string $contactName,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?int $funnelVersion = null,
    ) {}

    public function hasContact(): bool
    {
        return $this->contactEmail !== null || $this->contactPhone !== null;
    }

    /**
     * @param  array<string, mixed>  $input  Antworten je Frage-Schluessel aus allen besuchten Schritten
     *
     * @throws ValidationException
     */
    public static function fromFunnel(FunnelDefinition $funnel, array $input): self
    {
        $skipKeys = (array) config('leads.exclusive.marketplace_only_keys', []);
        $contactStep = $funnel->contactStepPosition ?? $funnel->steps[array_key_last($funnel->steps)]->position;
        $answers = [];
        $names = [];
        $email = null;
        $phone = null;
        $errors = [];

        foreach ($funnel->steps as $step) {
            foreach ($step->visibleQuestions() as $question) {
                if ($question->type === 'info' || in_array($question->key, $skipKeys, true)) {
                    continue;
                }

                $value = self::normalize($input[$question->key] ?? null);
                $field = "answers.{$question->key}";

                if (self::isBlank($value)) {
                    if ($question->required && $step->position === $contactStep) {
                        $errors[$field] = [__('validation.required', ['attribute' => $question->label])];
                    }

                    continue;
                }

                if ($question->type === 'email') {
                    if (! is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                        $errors[$field] = [__('validation.email', ['attribute' => $question->label])];

                        continue;
                    }
                    $email ??= Str::limit($value, 255, '');

                    continue;
                }

                if ($question->type === 'phone') {
                    if (! is_string($value) || preg_match('/^\+?[0-9 ()\/.\-]{6,32}$/', $value) !== 1) {
                        $errors[$field] = [__('validation.regex', ['attribute' => $question->label])];

                        continue;
                    }
                    $phone ??= $value;

                    continue;
                }

                if ($step->position === $contactStep && $question->type === 'text' && str_contains($question->key, 'name')) {
                    $names[] = is_string($value) ? Str::limit($value, 120, '') : '';

                    continue;
                }

                $valueLabel = self::valueLabel($question, $value);

                if ($valueLabel === false) {
                    $errors[$field] = [__('validation.in', ['attribute' => $question->label])];

                    continue;
                }

                $answers[] = [
                    'key' => $question->key,
                    'label' => $question->label,
                    'type' => $question->type,
                    'value' => $value,
                    'value_label' => $valueLabel,
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $name = trim(implode(' ', array_filter($names)));

        return new self(
            answers: $answers,
            contactName: $name !== '' ? $name : null,
            contactEmail: $email,
            contactPhone: $phone,
            funnelVersion: $funnel->version,
        );
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            return Str::limit(trim($value), self::MAX_TEXT, '');
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn ($item) => is_scalar($item) ? Str::limit(trim((string) $item), 255, '') : null,
                $value,
            ), static fn ($item) => $item !== null && $item !== ''));
        }

        return is_bool($value) || is_int($value) || is_float($value) ? $value : null;
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === false;
    }

    /**
     * Anzeigetext der Antwort; false, wenn eine Auswahl nicht im Snapshot steht.
     */
    private static function valueLabel(FunnelQuestion $question, mixed $value): string|false|null
    {
        if ($question->type === 'consent') {
            return $value ? __('portal.owner.inquiries.show.yes') : __('portal.owner.inquiries.show.no');
        }

        if (! in_array($question->type, self::CHOICE_TYPES, true)) {
            return null;
        }

        $labels = [];

        foreach ($question->options as $option) {
            $labels[$option->value] = $option->label;
        }

        $labelsOf = [];

        foreach ((array) $value as $item) {
            if (! is_scalar($item) || ! array_key_exists((string) $item, $labels)) {
                return false;
            }
            $labelsOf[] = $labels[(string) $item];
        }

        return implode(', ', $labelsOf);
    }
}
