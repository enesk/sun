<?php

declare(strict_types=1);

namespace App\Guide\Import;

use InvalidArgumentException;

/**
 * Eine Zeile der Themenliste ist unbrauchbar; die Meldung landet als Grund im
 * ImportReport, der Import laeuft mit der naechsten Zeile weiter.
 */
class InvalidTopicRow extends InvalidArgumentException {}
