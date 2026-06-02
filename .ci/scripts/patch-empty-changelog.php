<?php

/**
 * Patches empty changelog sections with a "No notable changes." message.
 *
 * conventional-changelog generates empty sections like:
 *   ## [0.1.1](url) (date)
 *
 *   ---
 *
 * This script fills them with:
 *   ## [0.1.1](url) (date)
 *
 *   No notable changes.
 *
 *   ---
 */

$file = getcwd() . '/CHANGELOG.md';

if (!file_exists($file)) {
    exit(0);
}

$content = file_get_contents($file);

// Match a version header followed by only whitespace before the --- separator
$patched = preg_replace(
    '/(## \[.+\]\(.+\) \(\d{4}-\d{2}-\d{2}\))\s*\n\n---/',
    "$1\n\nNo notable changes.\n\n---",
    $content
);

if ($patched !== $content) {
    file_put_contents($file, $patched);
    echo "Patched empty changelog section(s) with 'No notable changes.'\n";
}
