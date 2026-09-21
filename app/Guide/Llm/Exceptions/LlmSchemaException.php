<?php

declare(strict_types=1);

namespace App\Guide\Llm\Exceptions;

use RuntimeException;

/**
 * Das Modell hat auch nach allen Wiederholungen (1 + guide.anthropic.schema_retries)
 * keine schemakonforme Antwort ueber 'emit' geliefert (#5). Der Aufrufer
 * behandelt das wie einen gescheiterten Schritt des Laufs.
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
}
