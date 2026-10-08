<?php

/*
 * Prueft, dass Blade keinen JSON-LD-Schluessel zu einer Direktive kompiliert (#30).
 *
 * '@context' ist in Laravel eine Blade-Direktive. Steht '@context' in einem
 * Blade-Ausdruck ausserhalb eines @php-Blocks — etwa in {!! json_encode([...]) !!} —,
 * ersetzt Blade den Schluessel durch PHP-Code und das JSON-LD ist unbrauchbar.
 * Richtig ist der zusammengesetzte Schluessel '@'.'context'.
 *
 * Aufruf (Blade-Cache wird vorher geleert, weil kompilierte Views liegen bleiben):
 *   php artisan view:clear && php artisan tinker --execute='require "scripts/jsonld-context-pruefen.php";'
 *
 * Exit-Code laesst tinker unberuehrt; der Befund steht im Text ("BEFUND"/"OK").
 */

$finder = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(resource_path('views'), RecursiveDirectoryIterator::SKIP_DOTS)
);

$compiler = app('blade.compiler');
$befunde = [];
$geprueft = 0;
$nurQuelle = 0;

foreach ($finder as $file) {
    if (! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $geprueft++;
    $quelle = file_get_contents($file->getPathname());

    try {
        $compiled = $compiler->compileString($quelle);
    } catch (Throwable $e) {
        // Theme-gebundene x-Komponenten lassen sich ohne aktives Theme nicht
        // aufloesen. Damit keine Blindstelle entsteht, pruefen wir dann die
        // Quelle: ein wortwoertlicher Direktiven-Schluessel ist immer verdaechtig.
        if (preg_match('/[\'"]@'.'context[\'"]/', $quelle)) {
            $befunde[] = $file->getPathname().' (Quelltextbefund, nicht kompilierbar: '.$e->getMessage().')';
        }

        $nurQuelle++;

        continue;
    }

    // Spur der kompilierten Direktive im Ergebnis.
    if (str_contains($compiled, '__contextArgs')) {
        $befunde[] = $file->getPathname();
    }
}

echo PHP_EOL, "Geprueft: {$geprueft} Blade-Dateien, davon {$nurQuelle} nur im Quelltext (nicht kompilierbar ohne aktives Theme).", PHP_EOL;

if ($befunde === []) {
    echo 'OK — keine View kompiliert einen JSON-LD-Schluessel zur Blade-Direktive.', PHP_EOL;
} else {
    echo 'BEFUND — ', count($befunde), " View(s) mit kompiliertem '@context'; Schluessel als '@'.'context' schreiben:", PHP_EOL;
    foreach ($befunde as $pfad) {
        echo '  - ', $pfad, PHP_EOL;
    }
}
