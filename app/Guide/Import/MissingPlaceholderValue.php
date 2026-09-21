<?php

declare(strict_types=1);

namespace App\Guide\Import;

use RuntimeException;

/**
 * Ein Platzhalter kann fuer ein Portal nicht ersetzt werden, weil der Wert in
 * tenant_guide_settings fehlt.
 */
class MissingPlaceholderValue extends RuntimeException {}
