<?php

declare(strict_types=1);

namespace App\Content\Llm\Exceptions;

use RuntimeException;

/**
 * Die Claude-CLI hat nach ihren eigenen Wiederholungen keine schemakonforme
 * Ausgabe geliefert (subtype 'error_max_structured_output_retries', #42).
 *
 * Kein toter Zugang und kein Fehler des Auftrags: LlmClient behandelt das wie
 * eine schemawidrige Antwort und startet einen neuen Versuch.
 */
class CliStructuredOutputException extends RuntimeException {}
