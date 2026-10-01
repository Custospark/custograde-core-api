<?php

/**
 * Vera Logic - Custograde backend repo rules & contracts (not php -l).
 * Usage: php scripts/vera-logic.php
 * Also runs from vera-fast.php after syntax checks.
 *
 * Domain rules (teacher approval, separation of duties) are verified against
 * docs/requirements/requirements.md traceability until the marking entities
 * land; code-level contract checks get added with those entities.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

const VERA_MAX_LINES = 500;

/**
 * @return list<string>
 */
function veraLogicChangedPhpFiles(string $root): array
{
    $commands = [
        'git diff --name-only --diff-filter=ACMRTUXB HEAD',
        'git diff --cached --name-only --diff-filter=ACMRTUXB',
        'git ls-files --others --exclude-standard',
    ];

    $files = [];

    foreach ($commands as $command) {
        $output = shell_exec($command . ' 2>nul') ?? shell_exec($command . ' 2>/dev/null') ?? '';
        foreach (preg_split('/\R/', trim($output)) as $path) {
            if ($path === '' || !str_ends_with($path, '.php')) {
                continue;
            }
            $normalized = str_replace('\\', '/', $path);
            if (!str_starts_with($normalized, 'app/') && !str_starts_with($normalized, 'tests/')) {
                continue;
            }
            $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if (is_file($full)) {
                $files[$normalized] = $normalized;
            }
        }
    }

    return array_values($files);
}

function veraLogicRead(string $root, string $rel): ?string
{
    $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    if (!is_file($full)) {
        return null;
    }

    return file_get_contents($full) ?: '';
}

function veraLogicLineCount(string $root, string $rel): int
{
    $text = veraLogicRead($root, $rel);
    if ($text === null) {
        return 0;
    }

    return substr_count($text, "\n") + (str_ends_with($text, "\n") ? 0 : 1);
}

/**
 * @return list<array{id: string, ok: bool, detail: string}>
 */
function veraLogicCheckFileSize(string $root, array $changed): array
{
    $results = [];
    foreach ($changed as $file) {
        $lines = veraLogicLineCount($root, $file);
        if ($lines > VERA_MAX_LINES) {
            $results[] = [
                'id' => 'file-size-500',
                'ok' => false,
                'detail' => "{$file} has {$lines} lines (max " . VERA_MAX_LINES . ')',
            ];
        }
    }

    if ($results === []) {
        $results[] = [
            'id' => 'file-size-500',
            'ok' => true,
            'detail' => $changed === []
                ? 'No changed app/tests PHP - size check skipped'
                : 'Changed app/tests PHP ≤ ' . VERA_MAX_LINES . ' lines (' . count($changed) . ' checked)',
        ];
    }

    return $results;
}

/**
 * @return array{id: string, ok: bool, detail: string}
 */
function veraLogicPhpImports(string $root, array $changed): array
{
    if ($changed === []) {
        return [
            'id' => 'php-imports',
            'ok' => true,
            'detail' => 'No changed app/tests PHP - import check skipped',
        ];
    }

    $broken = [];

    foreach ($changed as $file) {
        $text = veraLogicRead($root, $file);
        if ($text === null) {
            continue;
        }

        // use App\Foo\Bar; | use App\Foo\Bar as Alias;
        if (!preg_match_all('/^\s*use\s+(App\\\\[A-Za-z0-9_\\\\]+|Tests\\\\[A-Za-z0-9_\\\\]+)\s*(?:as\s+\w+)?\s*;/m', $text, $matches)) {
            continue;
        }

        foreach ($matches[1] as $fqcn) {
            $resolved = veraLogicResolvePsr4($root, $fqcn);
            if ($resolved === null || !is_file($resolved)) {
                $broken[] = "{$file} → {$fqcn}";
            }
        }
    }

    if ($broken !== []) {
        $shown = array_slice($broken, 0, 8);
        $extra = count($broken) > 8 ? ' (+' . (count($broken) - 8) . ' more)' : '';

        return [
            'id' => 'php-imports',
            'ok' => false,
            'detail' => 'Unresolved App/Tests use import(s): ' . implode('; ', $shown) . $extra,
        ];
    }

    return [
        'id' => 'php-imports',
        'ok' => true,
        'detail' => 'App/Tests use imports resolve for ' . count($changed) . ' changed file(s)',
    ];
}

/**
 * Map App\ / Tests\ FQCN to a filesystem path (PSR-4).
 */
function veraLogicResolvePsr4(string $root, string $fqcn): ?string
{
    $map = [
        'App\\' => 'app/',
        'Tests\\' => 'tests/',
    ];

    foreach ($map as $prefix => $base) {
        if (!str_starts_with($fqcn, $prefix)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($fqcn, strlen($prefix))) . '.php';

        return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base . $relative);
    }

    return null;
}

/**
 * API contract: versioned routes stay grouped under routes/api/v1/.
 *
 * @return array{id: string, ok: bool, detail: string}
 */
function veraLogicApiVersioning(string $root): array
{
    $api = veraLogicRead($root, 'routes/api.php') ?? '';
    $ok = str_contains($api, "prefix('v1')") || str_contains($api, 'prefix("v1")');

    return [
        'id' => 'api-versioning',
        'ok' => $ok,
        'detail' => $ok
            ? 'routes/api.php groups endpoints under v1 prefix'
            : 'routes/api.php must group endpoints under a v1 prefix',
    ];
}

/**
 * Collect all changed/untracked text files in the repo (any extension),
 * skipping binaries, vendored deps, build output, and dotfiles.
 *
 * @return list<string>
 */
function veraLogicChangedFiles(string $root): array
{
    $commands = [
        'git diff --name-only --diff-filter=ACMRTUXB HEAD',
        'git diff --cached --name-only --diff-filter=ACMRTUXB',
        'git ls-files --others --exclude-standard',
    ];

    $binaryExt = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.ico', '.woff', '.woff2', '.ttf', '.eot', '.mp3', '.mp4', '.pdf', '.zip', '.gz', '.wasm'];
    $skipPaths = ['/node_modules/', '/vendor/', '/dist/', '/.git/'];

    $files = [];
    foreach ($commands as $command) {
        $output = shell_exec($command . ' 2>nul') ?? shell_exec($command . ' 2>/dev/null') ?? '';
        foreach (preg_split('/\R/', trim($output)) as $path) {
            if ($path === '') {
                continue;
            }
            $normalized = str_replace('\\', '/', $path);
            $lower = strtolower($normalized);
            if (str_ends_with($lower, '.lock')) {
                continue;
            }
            foreach ($skipPaths as $skip) {
                if (str_contains($lower, $skip)) {
                    continue 2;
                }
            }
            $ext = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
            if (in_array('.' . $ext, $binaryExt, true)) {
                continue;
            }
            $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if (is_file($full)) {
                $files[$normalized] = $normalized;
            }
        }
    }

    return array_values($files);
}

/**
 * @return array{id: string, ok: bool, detail: string}
 */
function veraLogicNoLongDashes(string $root): array
{
    $offenders = [];
    foreach (veraLogicChangedFiles($root) as $file) {
        $text = veraLogicRead($root, $file);
        if ($text === null) {
            continue;
        }
        if (preg_match('/[\x{2014}\x{2013}]/u', $text)) {
            $offenders[] = $file;
        }
    }

    if ($offenders !== []) {
        $shown = array_slice($offenders, 0, 8);
        $extra = count($offenders) > 8 ? ' (+' . (count($offenders) - 8) . ' more)' : '';

        return [
            'id' => 'no-long-dashes',
            'ok' => false,
            'detail' => 'Long dash (em/en) found in changed file(s): ' . implode(', ', $shown) . $extra . ' - use a plain hyphen instead',
        ];
    }

    return [
        'id' => 'no-long-dashes',
        'ok' => true,
        'detail' => 'No em/en dashes in changed files',
    ];
}

$changed = veraLogicChangedPhpFiles($root);
$results = array_merge(
    veraLogicCheckFileSize($root, $changed),
    [
        veraLogicPhpImports($root, $changed),
        veraLogicApiVersioning($root),
        veraLogicNoLongDashes($root),
    ],
);

$failed = array_values(array_filter($results, static fn (array $r): bool => !$r['ok']));

echo '🧪 Vera logic: ' . count($results) . " rule(s)\n";
foreach ($results as $r) {
    echo '  ' . ($r['ok'] ? '✅' : '❌') . " [{$r['id']}] {$r['detail']}\n";
}

if ($failed !== []) {
    echo '❌ Vera logic: failed (' . count($failed) . ")\n";
    exit(1);
}

echo "✅ Vera logic: passed\n";
exit(0);
