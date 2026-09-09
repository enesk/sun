<?php

declare(strict_types=1);

namespace App\Content\Generation\Exceptions;

use RuntimeException;

/**
 * Ein regionales Thema ohne einen einzigen regionalen Faktenschnipsel (#14).
 *
 * Der Generator schreibt daraus keinen Regionalblock. Der Kandidat faellt auf
 * 'national' zurueck und wird bundesweit erzeugt — ein regionaler Artikel ohne
 * regionalen Inhalt waere eine Doorway-Seite und faellt im Qualitaetsgate (#15)
 * ohnehin durch.
 */
class MissingRegionalFactsException extends RuntimeException
{
    public const REASON = 'missing_regional_facts';

    public function __construct(
        string $message,
        public readonly string $regionScope,
        public readonly ?string $regionCode,
    ) {
        parent::__construct($message);
    }

    public static function for(string $scope, ?string $code): self
    {
        return new self(
            "Kein regionaler Faktenschnipsel fuer {$scope} ".($code ?? '?').'.',
            $scope,
            $code,
        );
    }

    public function reason(): string
    {
        return self::REASON;
    }
}
