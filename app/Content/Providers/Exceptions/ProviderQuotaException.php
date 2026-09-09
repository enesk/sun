<?php

declare(strict_types=1);

namespace App\Content\Providers\Exceptions;

use RuntimeException;

/**
 * Das Tageskontingent an Anfragen eines kostenpflichtigen Providers ist
 * aufgebraucht (#8).
 *
 * Abgrenzung zur BudgetExceededException (#6): dort geht es um USD aus
 * llm_usage_logs, hier um die reine Anzahl Anfragen aus
 * provider_states.requests_today. Beide enden gleich — der Provider steht bis
 * 00:00 auf ProviderState::STATUS_PAUSED.
 *
 * Connectoren fangen die Ausnahme ab und liefern zurueck, was sie bis dahin
 * gesammelt haben, statt den Lauf als Provider-Fehler zu melden. Ein
 * erschoepftes Kontingent ist kein Ausfall.
 */
class ProviderQuotaException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly string $scope,
        public readonly int $used,
        public readonly int $limit,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  string  $scope  'provider' fuer die Gesamtgrenze, sonst der
     *                         Name des Endpunkts (z. B. 'trends_explore')
     */
    public static function forScope(string $provider, string $scope, int $used, int $limit): self
    {
        $label = $scope === 'provider'
            ? "Tageskontingent von '{$provider}'"
            : "Tageskontingent von '{$provider}' fuer '{$scope}'";

        return new self(
            "{$label} erschoepft: {$used} von {$limit} Anfragen verbraucht.",
            $provider,
            $scope,
            $used,
            $limit,
        );
    }

    /**
     * Wie lange ein Aufrufer den Job zurueckstellen soll, wenn er ihn nicht
     * einfach mit weniger Ergebnissen beendet.
     */
    public function releaseDelaySeconds(): int
    {
        return (int) config('content.budget.release_delay_seconds', 1800);
    }
}
