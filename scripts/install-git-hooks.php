<?php

/**
 * Richtet die Git-Hooks aus .githooks/ ein (Secret-Scan vor dem Commit).
 * Läuft nach jedem composer install/update und ist bewusst still, wenn kein
 * Git-Repository vorhanden ist — etwa in einem entpackten Release.
 */
$root = dirname(__DIR__);

if (! is_dir($root.'/.git') && ! is_file($root.'/.git')) {
    exit(0);
}

$out = [];
$code = 0;
exec('git -C '.escapeshellarg($root).' config core.hooksPath .githooks 2>&1', $out, $code);

if ($code !== 0) {
    fwrite(STDERR, 'Hinweis: Git-Hooks konnten nicht gesetzt werden ('.implode(' ', $out).").\n");
    exit(0);
}

fwrite(STDOUT, "Git-Hooks aktiv: .githooks (Secret-Scan vor dem Commit).\n");
