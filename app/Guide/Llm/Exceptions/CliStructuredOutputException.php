<?php

declare(strict_types=1);

namespace App\Guide\Llm\Exceptions;

use RuntimeException;

/**
 * Die Claude-CLI hat nach ihren eigenen Wiederholungen keine schemakonforme
 * Ausgabe geliefert (subtype 'error_max_structured_output_retries').
 *
 * Kein toter Zugang und kein Fehler des Auftrags: LlmClient zaehlt das als
 * schemawidrigen Versuch und ruft erneut auf (#42, Lehre aus der alten
 * Pipeline).
 */
class CliStructuredOutputException extends RuntimeException {}
