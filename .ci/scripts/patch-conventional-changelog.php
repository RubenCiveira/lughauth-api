<?php

/**
 * Patches marcocesarato/php-conventional-changelog for Symfony Console 8.x compatibility.
 *
 * - DefaultCommand::configure() is missing `: void` return type
 * - Executable uses Application::add() which was renamed to addCommand()
 */

$basePath = __DIR__ . '/../vendor/marcocesarato/php-conventional-changelog';

$patches = [
    // 1. Add :void return type to configure()
    [
        'file' => $basePath . '/src/DefaultCommand.php',
        'search' => 'protected function configure()',
        'replace' => 'protected function configure(): void',
    ],
    // 2. Rename add() to addCommand() in the executable
    [
        'file' => $basePath . '/conventional-changelog',
        'search' => '$application->add($command)',
        'replace' => '$application->addCommand($command)',
    ],
];

foreach ($patches as $patch) {
    if (!file_exists($patch['file'])) {
        fwrite(STDERR, "File not found: {$patch['file']}\nRun 'composer install' first.\n");
        exit(1);
    }

    $content = file_get_contents($patch['file']);

    if (str_contains($content, $patch['replace'])) {
        continue; // already patched
    }

    $patched = str_replace($patch['search'], $patch['replace'], $content);

    if ($patched === $content) {
        fwrite(STDERR, "Could not apply patch to {$patch['file']}: search string not found.\n");
        exit(1);
    }

    file_put_contents($patch['file'], $patched);
    echo "Patched: {$patch['search']} → {$patch['replace']}\n";
}
