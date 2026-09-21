<?php

declare(strict_types=1);

namespace App\Guide\Llm;

/**
 * Summen ueber alle HTTP-Anfragen eines LlmClient-Aufrufs (#5). Jede Anfrage
 * hat ihre eigene Zeile in llm_usage_logs; das hier ist nur die Anzeige fuer
 * den Aufrufer.
 */
final class UsageTotals
{
    public int $requests = 0;

    public int $inputTokens = 0;

    public int $outputTokens = 0;

    public int $searches = 0;

    public float $costUsd = 0.0;

    public function add(int $inputTokens, int $outputTokens, int $searches, float $costUsd): void
    {
        $this->requests++;
        $this->inputTokens += $inputTokens;
        $this->outputTokens += $outputTokens;
        $this->searches += $searches;
        $this->costUsd = round($this->costUsd + $costUsd, 6);
    }
}
