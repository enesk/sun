<?php

declare(strict_types=1);

namespace App\Content\Sources\Exceptions;

use RuntimeException;

class UnknownConnectorException extends RuntimeException
{
    /**
     * @param  array<int, string>  $known
     */
    public static function forKey(string $key, array $known = []): self
    {
        $hint = $known === [] ? '' : ' Registriert sind: '.implode(', ', $known).'.';

        return new self("Unbekannter Quell-Connector '{$key}'.".$hint);
    }
}
