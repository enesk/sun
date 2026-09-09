<?php

declare(strict_types=1);

namespace App\Content\Llm\Exceptions;

use RuntimeException;

/**
 * Das Modell hat auch nach allen Wiederholungen keine schemakonforme Antwort
 * geliefert (#6).
 *
 * Der Aufrufer (Artikel-Generator #14, Qualitaetsgate #15) behandelt das wie
 * einen fehlgeschlagenen Generierungsversuch — der Entwurf laeuft in seinen
 * naechsten Versuch bzw. nach content.pipeline.max_generation_attempts in
 * DraftStatus::FAILED.
 */
class LlmSchemaException extends RuntimeException
{
    /**
     * @param  array<int, string>  $errors  Verstoesse des letzten Versuchs
     */
    public function __construct(
        string $message,
        public readonly string $templateKey = '',
        public readonly int $attempts = 0,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<int, string>  $errors
     */
    public static function afterRetries(string $templateKey, int $attempts, array $errors): self
    {
        $detail = $errors === [] ? 'keine Begruendung' : implode('; ', $errors);

        return new self(
            "Template '{$templateKey}' liefert nach {$attempts} Versuchen keine schemakonforme Antwort: {$detail}.",
            $templateKey,
            $attempts,
            $errors,
        );
    }

    public static function noToolUse(string $templateKey, int $attempts): self
    {
        return new self(
            "Template '{$templateKey}' liefert nach {$attempts} Versuchen keinen Tool-Use-Block.",
            $templateKey,
            $attempts,
            ['Antwort enthaelt keinen Block vom Typ tool_use.'],
        );
    }
}
