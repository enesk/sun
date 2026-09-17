<?php

declare(strict_types=1);

namespace App\Services\ProfileDescriptions;

use RuntimeException;

/**
 * Ein erzeugter Text verletzt eine Regel; die Meldung ist der Grund.
 */
final class InvalidDescription extends RuntimeException {}
