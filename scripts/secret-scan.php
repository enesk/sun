<?php

/**
 * Secret-Scan für dieses Repository.
 *
 * Sucht nach Zugangsschlüsseln, die versehentlich in den Code geraten sind —
 * Google-API-Schlüssel, Anthropic-/OpenAI-Schlüssel, private Schlüsselblöcke,
 * AWS-Zugangsdaten und env()-Aufrufe mit einem Schlüssel als Vorgabewert.
 *
 * Bewusst ohne Laravel-Bootstrap und ohne Composer-Abhängigkeit: der Scan läuft
 * im Pre-Commit-Hook und in der CI, auch wenn vendor/ oder .env fehlen.
 *
 * Aufruf:
 *   php scripts/secret-scan.php            alle versionierten Dateien
 *   php scripts/secret-scan.php --staged   nur die vorgemerkten Änderungen
 *   php scripts/secret-scan.php <pfad> ... einzelne Dateien
 *
 * Exit-Code 0 = sauber, 1 = Fund, 2 = Aufrufproblem.
 */
const ALLOWLIST_FILE = '.secret-scan-allow';

const SKIP_DIRS = [
    'vendor/', 'node_modules/', 'public/build/', 'public/js/', 'public/css/',
    'storage/', 'bootstrap/cache/', '.git/', 'resources/js/dist/',
];

const SKIP_FILES = [
    'composer.lock', 'package-lock.json', 'yarn.lock', 'scripts/secret-scan.php',
];

const SKIP_EXTENSIONS = [
    'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'pdf', 'zip', 'gz',
    'woff', 'woff2', 'ttf', 'eot', 'otf', 'mp4', 'webm', 'mp3', 'lock', 'map',
];

/**
 * Muster mit fester Kennung. 'name' erscheint im Bericht und in der Allowlist.
 */
const PATTERNS = [
    [
        'name' => 'google-api-key',
        'regex' => '/\bAIza[0-9A-Za-z\-_]{35}\b/',
        'label' => 'Google-API-Schlüssel (AIzaSy…)',
    ],
    [
        'name' => 'anthropic-key',
        'regex' => '/\bsk-ant-[0-9A-Za-z\-_]{16,}/',
        'label' => 'Anthropic-Schlüssel (sk-ant-…)',
    ],
    [
        'name' => 'openai-key',
        'regex' => '/\bsk-(?:proj-|svcacct-|admin-)?[A-Za-z0-9\-_]{24,}/',
        'label' => 'OpenAI-Schlüssel (sk-…)',
    ],
    [
        'name' => 'stripe-live-key',
        'regex' => '/\b(?:sk|rk)_live_[0-9A-Za-z]{10,}/',
        'label' => 'Stripe-Live-Schlüssel',
    ],
    [
        'name' => 'private-key-block',
        'regex' => '/-----BEGIN (?:RSA |EC |DSA |OPENSSH |PGP )?PRIVATE KEY(?: BLOCK)?-----/',
        'label' => 'privater Schlüsselblock',
    ],
    [
        'name' => 'aws-access-key',
        'regex' => '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
        'label' => 'AWS-Zugangsschlüssel',
    ],
    [
        'name' => 'google-service-account',
        'regex' => '/"type"\s*:\s*"service_account"/',
        'label' => 'Google-Dienstkonto-Datei',
    ],
    [
        'name' => 'slack-token',
        'regex' => '/\bxox[abprs]-[0-9A-Za-z\-]{10,}/',
        'label' => 'Slack-Token',
    ],
    [
        'name' => 'github-token',
        'regex' => '/\bgh[pousr]_[0-9A-Za-z]{30,}/',
        'label' => 'GitHub-Token',
    ],
    [
        'name' => 'app-key',
        'regex' => '/APP_KEY\s*=\s*"?base64:[A-Za-z0-9+\/]{40,}={0,2}/',
        'label' => 'Laravel APP_KEY',
    ],
];

/** Variablennamen, bei denen ein Vorgabewert im Code ein Schlüssel sein kann. */
const SECRET_NAME_REGEX = '/(KEY|SECRET|TOKEN|PASSWORD|PASSWD|CREDENTIAL|PRIVATE|SALT|CERT|SIGNATURE|AUTH_CODE|DSN)/';

/** Vorgabewerte, die harmlos sind: leer, Schalter, Zahlen, URLs, Pfade, Platzhalter. */
const HARMLESS_DEFAULT_REGEX = '/^$|^(null|true|false|none|local|array|sync|file|redis|database)$|^-?\d+(\.\d+)?$|^https?:\/\/|^\/|^[a-z_\-]+\.(php|json|txt|pem)$|^(your|dummy|example|changeme|placeholder|test|xxx)/i';

exit(main($argv));

function main(array $argv): int
{
    $args = array_slice($argv, 1);
    $staged = in_array('--staged', $args, true);
    $quiet = in_array('--quiet', $args, true);
    $paths = array_values(array_filter($args, fn ($a) => ! str_starts_with($a, '--')));

    $root = repoRoot();
    if ($root === null && $paths === []) {
        fwrite(STDERR, "secret-scan: kein Git-Repository gefunden.\n");

        return 2;
    }
    if ($root !== null && $paths === []) {
        chdir($root);
    }

    $files = $paths !== []
        ? $paths
        : ($staged ? stagedFiles() : trackedFiles());

    $allow = $root !== null ? loadAllowlist($root) : [];
    $findings = [];
    $scanned = 0;

    foreach ($files as $file) {
        if (shouldSkip($file)) {
            continue;
        }

        $content = $staged && $paths === [] ? stagedContent($file) : @file_get_contents($file);
        if ($content === false || $content === null || $content === '') {
            continue;
        }
        if (str_contains(substr($content, 0, 8000), "\0")) {
            continue; // Binärdatei
        }

        $scanned++;
        foreach (scanContent($file, $content) as $finding) {
            if (isAllowed($finding, $allow)) {
                continue;
            }
            $findings[] = $finding;
        }
    }

    if ($findings === []) {
        if (! $quiet) {
            fwrite(STDOUT, sprintf("secret-scan: sauber (%d Dateien geprüft).\n", $scanned));
        }

        return 0;
    }

    fwrite(STDERR, sprintf("\nsecret-scan: %d Fund(e) in %d geprüften Dateien.\n\n", count($findings), $scanned));
    foreach ($findings as $f) {
        fwrite(STDERR, sprintf(
            "  %s:%d  [%s] %s\n      %s\n",
            $f['file'], $f['line'], $f['rule'], $f['label'], $f['excerpt']
        ));
    }
    fwrite(STDERR, <<<'TXT'

  Was jetzt zu tun ist:
    1. Den Wert aus dem Code nehmen und in die lokale .env schreiben.
       Im Code nur env('NAME') ohne Vorgabewert lesen.
    2. Ist der Wert schon einmal committet worden, gilt er als kompromittiert
       und muss beim Anbieter gelöscht und neu ausgestellt werden.
    3. Ein Fehlalarm gehört mit Begründung in .secret-scan-allow
       (Format: pfad:regelname).

TXT);

    return 1;
}

/** @return list<array{file:string,line:int,rule:string,label:string,excerpt:string}> */
function scanContent(string $file, string $content): array
{
    $findings = [];
    $lines = preg_split('/\R/', $content) ?: [];

    foreach ($lines as $i => $line) {
        if (strlen($line) > 4000) {
            $line = substr($line, 0, 4000);
        }
        if (isIgnoredLine($line)) {
            continue;
        }

        foreach (PATTERNS as $pattern) {
            if (preg_match($pattern['regex'], $line, $m)) {
                $findings[] = [
                    'file' => $file,
                    'line' => $i + 1,
                    'rule' => $pattern['name'],
                    'label' => $pattern['label'],
                    'excerpt' => mask($line, $m[0]),
                ];
            }
        }

        foreach (envDefaultFindings($line) as $hit) {
            $findings[] = [
                'file' => $file,
                'line' => $i + 1,
                'rule' => 'env-default-secret',
                'label' => sprintf('env(\'%s\') mit Vorgabewert im Code', $hit['var']),
                'excerpt' => mask(trim($line), $hit['value']),
            ];
        }
    }

    return $findings;
}

/** env('NAME', 'wert') mit nichtleerem Vorgabewert bei schlüsselartigem Namen. */
function envDefaultFindings(string $line): array
{
    $hits = [];
    if (! preg_match_all('/env\(\s*[\'"]([A-Z0-9_]+)[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*\)/', $line, $matches, PREG_SET_ORDER)) {
        return $hits;
    }

    foreach ($matches as $m) {
        if (! preg_match(SECRET_NAME_REGEX, $m[1])) {
            continue;
        }
        if (preg_match(HARMLESS_DEFAULT_REGEX, $m[2])) {
            continue;
        }
        $hits[] = ['var' => $m[1], 'value' => $m[2]];
    }

    return $hits;
}

function isIgnoredLine(string $line): bool
{
    return str_contains($line, 'secret-scan:ignore');
}

function mask(string $line, string $match): string
{
    $keep = min(6, max(0, strlen($match) - 4));
    $masked = substr($match, 0, $keep).str_repeat('*', max(4, min(12, strlen($match) - $keep)));
    $out = str_replace($match, $masked, $line);
    $out = trim($out);

    return strlen($out) > 160 ? substr($out, 0, 160).'…' : $out;
}

function shouldSkip(string $file): bool
{
    foreach (SKIP_DIRS as $dir) {
        if (str_starts_with($file, $dir)) {
            return true;
        }
    }
    if (in_array($file, SKIP_FILES, true)) {
        return true;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    return in_array($ext, SKIP_EXTENSIONS, true) || ! is_readable($file) && ! isStagedPath($file);
}

function isStagedPath(string $file): bool
{
    static $staged = null;
    $staged ??= array_flip(stagedFiles());

    return isset($staged[$file]);
}

function repoRoot(): ?string
{
    $out = shellLines('git rev-parse --show-toplevel 2>/dev/null');

    return $out[0] ?? null;
}

/** @return list<string> */
function trackedFiles(): array
{
    return shellLines('git ls-files -z', true);
}

/** @return list<string> */
function stagedFiles(): array
{
    return shellLines('git diff --cached --name-only --diff-filter=ACM -z', true);
}

function stagedContent(string $file): string|false
{
    $out = [];
    $code = 0;
    exec('git show :'.escapeshellarg($file).' 2>/dev/null', $out, $code);

    return $code === 0 ? implode("\n", $out) : false;
}

/** @return list<string> */
function shellLines(string $command, bool $nullSeparated = false): array
{
    $raw = shell_exec($command);
    if ($raw === null || $raw === false) {
        return [];
    }
    $parts = $nullSeparated ? explode("\0", $raw) : preg_split('/\R/', trim($raw));

    return array_values(array_filter(array_map('trim', $parts ?: []), fn ($p) => $p !== ''));
}

/**
 * .secret-scan-allow: eine Zeile je Ausnahme, "pfad:regelname".
 * Der Pfad darf mit * enden. Kommentare beginnen mit #.
 */
function loadAllowlist(string $root): array
{
    $path = $root.'/'.ALLOWLIST_FILE;
    if (! is_file($path)) {
        return [];
    }
    $rules = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $pos = strrpos($line, ':');
        if ($pos === false) {
            continue;
        }
        $rules[] = ['path' => trim(substr($line, 0, $pos)), 'rule' => trim(substr($line, $pos + 1))];
    }

    return $rules;
}

function isAllowed(array $finding, array $allow): bool
{
    foreach ($allow as $rule) {
        if ($rule['rule'] !== $finding['rule'] && $rule['rule'] !== '*') {
            continue;
        }
        $path = $rule['path'];
        if ($path === $finding['file']
            || (str_ends_with($path, '*') && str_starts_with($finding['file'], rtrim($path, '*')))) {
            return true;
        }
    }

    return false;
}
