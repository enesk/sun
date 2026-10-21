<?php

declare(strict_types=1);

namespace App\Companies\Deletion;

/**
 * Rueckmeldung eines Schritts (#8). `payload` ist das, was im Protokoll
 * nachvollziehbar machen soll, was passiert ist — Mengen, Pfade, HTTP-Status.
 * Personenbezogenes gehoert nicht hinein: das Protokoll bleibt dauerhaft.
 */
final class StepResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public readonly StepStatus $status,
        public readonly ?string $message = null,
        public readonly array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function ok(?string $message = null, array $payload = []): self
    {
        return new self(StepStatus::OK, $message, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function skipped(string $message, array $payload = []): self
    {
        return new self(StepStatus::SKIPPED, $message, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function failed(string $message, array $payload = []): self
    {
        return new self(StepStatus::FAILED, $message, $payload);
    }

    public function isFailed(): bool
    {
        return $this->status === StepStatus::FAILED;
    }
}
