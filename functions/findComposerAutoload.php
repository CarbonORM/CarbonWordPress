<?php

$currentLevel = 0;

$lowestComposer = '';

do {

    $currentDir = dirname(__DIR__, ++$currentLevel);

    // Check for composer.json and if a custom vendor directory is defined
    $composerJsonPath = $currentDir . '/composer.json';

    if (!file_exists($composerJsonPath)) {

        continue;

    }

    $lowestComposer = $composerJsonPath;

    // Check for standard vendor/autoload.php
    $standardAutoloadPath = $currentDir . '/vendor/autoload.php';

    if (file_exists($standardAutoloadPath)) {

        return [$lowestComposer, $standardAutoloadPath];

    }

    try {

        $composerConfig = json_decode(file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);

    } catch (Throwable $e) {

        throwAlert( "<h1>Failed to parse your composer.json ($composerJsonPath). CarbonPHP not loaded.</h1><pre>" . print_r(debug_backtrace(), true) . "</pre>");

        return false;

    }

    if (isset($composerConfig['config']['vendor-dir'])) {

        $vendorDir = $composerConfig['config']['vendor-dir'];

        $customAutoloadPath = $currentDir . '/' . $vendorDir . '/autoload.php';

        if (file_exists($customAutoloadPath)) {

            return [$lowestComposer, $standardAutoloadPath];

        }

    }
    
} while (ABSPATH !== $currentDir && '/' !== $currentDir);

if ('' !== $lowestComposer) {

    return [$lowestComposer, false];

}

return false;



