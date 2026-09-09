<?php

declare(strict_types=1);

namespace App\Content\Llm\Exceptions;

use RuntimeException;

/**
 * Eine der Budgetgrenzen aus config('content.budget') ist erreicht (#6).
 *
 * Jobs fangen die Ausnahme ab und stellen sich um
 * content.budget.release_delay_seconds zurueck, statt zu scheitern. Der
 * betroffene Provider steht dann auf ProviderState::STATUS_PAUSED und wird
 * um 00:00 automatisch wieder freigegeben.
 */
class BudgetExceededException extends RuntimeException
{
    public const SCOPE_DAILY = 'daily';

    public const SCOPE_PROVIDER = 'provider';

    public const SCOPE_TENANT = 'tenant';

    public const SCOPE_ARTICLE = 'article';

    public function __construct(
        string $message,
        public readonly string $scope,
        public readonly string $provider,
        public readonly float $spentUsd,
        public readonly float $limitUsd,
        public readonly ?int $tenantId = null,
    ) {
        parent::__construct($message);
    }

    public static function forScope(
        string $scope,
        string $provider,
        float $spent,
        float $limit,
        ?int $tenantId = null,
    ): self {
        $label = match ($scope) {
            self::SCOPE_DAILY => 'Tagesbudget',
            self::SCOPE_PROVIDER => "Tagesanteil des Providers '{$provider}'",
            self::SCOPE_TENANT => "Tagesbudget des Mandanten {$tenantId}",
            self::SCOPE_ARTICLE => 'Budget dieses Artikels',
            default => $scope,
        };

        return new self(
            sprintf('%s erschoepft: %.4f von %.4f USD verbraucht.', $label, $spent, $limit),
            $scope,
            $provider,
            $spent,
            $limit,
            $tenantId,
        );
    }

    /**
     * Wie lange der Aufrufer den Job zurueckstellen soll.
     */
    public function releaseDelaySeconds(): int
    {
        return (int) config('content.budget.release_delay_seconds', 1800);
    }
}
