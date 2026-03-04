<?php

/**
 * Syncs the version from the CLI argument into all source files,
 * then commits and tags.
 *
 * Usage:  php scripts/bump-version.php <patch|minor|major|X.Y.Z>
 *
 * Mirrors the workflow from querri-embed/scripts/bump-version.js.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// 1. Parse argument
// ---------------------------------------------------------------------------

$arg = $argv[1] ?? null;

if ($arg === null || in_array($arg, ['-h', '--help'], true)) {
    fwrite(STDERR, <<<'USAGE'
    Usage: php scripts/bump-version.php <patch|minor|major|X.Y.Z>

    Examples:
      php scripts/bump-version.php patch   # 0.1.0 → 0.1.1
      php scripts/bump-version.php minor   # 0.1.0 → 0.2.0
      php scripts/bump-version.php major   # 0.1.0 → 1.0.0
      php scripts/bump-version.php 2.0.0   # explicit version

    USAGE);
    exit(1);
}

// ---------------------------------------------------------------------------
// 2. Read current version from Config.php
// ---------------------------------------------------------------------------

$configPath = __DIR__ . '/../src/Config.php';
$config = file_get_contents($configPath);

if (!preg_match("/userAgent:\s*'querri-php\/(\d+\.\d+\.\d+)'/", $config, $m)) {
    fwrite(STDERR, "ERROR: Could not find version in src/Config.php\n");
    exit(1);
}

$current = $m[1];
[$major, $minor, $patch] = array_map('intval', explode('.', $current));

echo "Current version: {$current}\n";

// ---------------------------------------------------------------------------
// 3. Calculate new version
// ---------------------------------------------------------------------------

$newVersion = match ($arg) {
    'patch' => sprintf('%d.%d.%d', $major, $minor, $patch + 1),
    'minor' => sprintf('%d.%d.%d', $major, $minor + 1, 0),
    'major' => sprintf('%d.%d.%d', $major + 1, 0, 0),
    default => $arg,
};

if (!preg_match('/^\d+\.\d+\.\d+$/', $newVersion)) {
    fwrite(STDERR, "ERROR: Invalid version '{$newVersion}'. Expected semver (X.Y.Z).\n");
    exit(1);
}

if ($newVersion === $current) {
    fwrite(STDERR, "ERROR: New version is the same as current ({$current}).\n");
    exit(1);
}

echo "New version:     {$newVersion}\n\n";

// ---------------------------------------------------------------------------
// 4. Replace version in target files
// ---------------------------------------------------------------------------

$targets = [
    [
        'file'        => 'src/Config.php',
        'pattern'     => "/userAgent:\s*'querri-php\/\d+\.\d+\.\d+'/",
        'replacement' => "userAgent: 'querri-php/{$newVersion}'",
    ],
    // Add test files here as they are created, e.g.:
    // [
    //     'file'        => 'tests/ConfigTest.php',
    //     'pattern'     => "/querri-php\/\d+\.\d+\.\d+/",
    //     'replacement' => "querri-php/{$newVersion}",
    // ],
];

$root = dirname(__DIR__);

foreach ($targets as $target) {
    $path = "{$root}/{$target['file']}";
    $content = file_get_contents($path);
    $updated = preg_replace($target['pattern'], $target['replacement'], $content, 1, $count);

    if ($count === 0) {
        fwrite(STDERR, "  WARN: no change in {$target['file']}\n");
    }

    file_put_contents($path, $updated);
    echo "  {$target['file']}\n";
}

echo "\nVersion synced to {$newVersion} across " . count($targets) . " file(s).\n\n";

// ---------------------------------------------------------------------------
// 5. Git commit and tag
// ---------------------------------------------------------------------------

chdir($root);

$tag = "v{$newVersion}";

run("git add -A");
run("git commit -m \"{$newVersion}\"");
run("git tag -a {$tag} -m \"{$tag}\"");

echo "\nDone! Run 'git push && git push --tags' to publish.\n";

// ---------------------------------------------------------------------------

function run(string $cmd): void
{
    echo "$ {$cmd}\n";
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "ERROR: command failed with exit code {$exitCode}\n");
        exit(1);
    }
}
