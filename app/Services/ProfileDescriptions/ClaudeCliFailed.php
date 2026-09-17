<?php

declare(strict_types=1);

namespace App\Services\ProfileDescriptions;

use RuntimeException;

/**
 * Aufruf oder Antwort der Claude-CLI unbrauchbar. Betrifft den ganzen Batch.
 *
 * answerUnusable: die CLI lief, lieferte aber kein lesbares JSON-Array — das
 * zaehlt als Fehlversuch der Profile. Sonst (Binary, Exit-Code, Timeout) geht
 * der Batch zurueck auf 'pending' und der Lauf endet.
 */
final class ClaudeCliFailed extends RuntimeException
{
    public bool $answerUnusable = false;

    public static function unusableAnswer(string $message): self
    {
        $exception = new self($message);
        $exception->answerUnusable = true;

        return $exception;
    }
}
