<?php

declare(strict_types=1);

namespace App\Services\ProfileDescriptions;

use RuntimeException;

/**
 * Die Claude-CLI meldet ein Nutzungslimit: Lauf beenden, spaeter fortsetzen.
 */
final class UsageLimitReached extends RuntimeException {}
