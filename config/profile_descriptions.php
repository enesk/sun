<?php

/*
|--------------------------------------------------------------------------
| Neue Profilbeschreibungen ueber die Claude-CLI
|--------------------------------------------------------------------------
|
| profiles:rewrite-descriptions, profiles:apply-descriptions und
| profiles:restore-descriptions. Die Texte entstehen ueber 'claude -p' (Abo
| mit Nutzungslimit), der Lauf verteilt sich deshalb ueber Tage und setzt
| nach einem Limit an derselben Stelle fort.
|
*/

return [

    // Pfad zur Claude-CLI auf der Maschine, die den Lauf startet.
    'cli_binary' => env('CLAUDE_CLI_BINARY'),

    'model' => 'claude-sonnet-5',

    // Profile je CLI-Aufruf und Zeitlimit je Aufruf in Sekunden.
    'batch_size' => 25,
    'timeout' => 600,

    // Pause zwischen zwei Aufrufen, wenn --sleep fehlt.
    'sleep' => 2,

    // Jede inhaltliche Aenderung an resources/prompts/profile-description.md
    // bekommt eine neue Version; sie steht an jedem erzeugten Text.
    'prompt_version' => 1,
    'prompt_path' => resource_path('prompts/profile-description.md'),

    // Versuche je Profil, danach Status 'skipped'.
    'max_attempts' => 3,

    // Harte Grenzen der Pruefung (der Prompt verlangt 40-80 bzw. 80-160 Woerter;
    // Profile nur mit Name und Anschrift kommen ohne Fuellstoff auf ~30).
    'length' => [
        'min_words' => 25,
        'max_words' => 180,
        'max_paragraphs' => 2,
    ],

    // Wert in companies.description_source nach der Uebernahme.
    'applied_source' => 'ai_generated',

    // Profile mit Inhaber (companies.user_id) behalten ihren Text, ausser --include-owned.
    'skip_owned' => true,

    // Ganzer Ausdruck, ohne Gross-/Kleinschreibung. Dieselbe Liste steht im Prompt.
    'forbidden_phrases' => [
        'Ihr zuverlässiger Partner',
        'kompetent und zuverlässig',
        'rund um',
        'Wir freuen uns auf Ihren Anruf',
        'Qualität steht an erster Stelle',
        'zögern Sie nicht',
    ],

];
