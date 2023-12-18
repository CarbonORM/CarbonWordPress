<?php
/*
 * Plugin Name: CarbonWordPress
 * Plugin URI: https://www.carbonorm.dev/
 * Description: CarbonORM/CarbonPHP WordPress Plugin
 * Author: Richard Tyler Miles
 */

use CarbonPHP\Abstracts\Composer;
use CarbonWordPress\CarbonWordPress;


if (!defined('ABSPATH')) {

    print '<h1>This file (' . __FILE__ . ') was loaded before WordPress initialized. This file should not be called directly. Please see <a href="https://carbonorm.dev/">https://carbonorm.dev/</a> for documentation.</h1><pre>' . print_r(debug_backtrace(), true) . '</pre>';

    exit(1);

}

function findComposerAutoload(): string
{
    $currentLevel = 0;

    do {

        $currentDir = dirname(__DIR__, $currentLevel);

        // Check for standard vendor/autoload.php
        $standardAutoloadPath = $currentDir . '/vendor/autoload.php';

        if (file_exists($standardAutoloadPath)) {

            return $standardAutoloadPath;

        }

        // Check for composer.json and a custom vendor directory
        $composerJsonPath = $currentDir . '/composer.json';

        if (!file_exists($composerJsonPath)) {

            continue;

        }

        try {

            $composerConfig = json_decode(file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);

        } catch (JsonException $e) {

            print "<h1>Failed to parse your composer.json ($composerJsonPath). Please run <b>composer install</b>.</h1><pre>" . print_r($e, true) . '</pre>';

            exit(2);

        }
        if (isset($composerConfig['config']['vendor-dir'])) {

            $vendorDir = $composerConfig['config']['vendor-dir'];

            $customAutoloadPath = $currentDir . '/' . $vendorDir . '/autoload.php';

            if (file_exists($customAutoloadPath)) {

                return $customAutoloadPath;

            }

        }


    } while ('/' !== $currentDir);

    print '<h1>Composer Failed. Please run <b>composer install</b>.</h1><pre>' . print_r(debug_backtrace(), true) . '</pre>';

    exit(3);
}

$autoloadPath = findComposerAutoload();

// Composer autoload
if (false === ($loader = include $autoloadPath)) {

    print "<h1>Composer autoload ($autoloadPath) failed to load. Please remove your vendor directory and rerun <b>composer install</b>.</h1><pre>" . print_r(debug_backtrace(), true) . "</pre>";

    exit(4);

}

Composer::$loader = $loader;

CarbonWordpress::make();





