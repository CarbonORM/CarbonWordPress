<?php
/*
 * Plugin Name: CarbonPHP WordPress Plugin
 * Plugin URI: https://www.carbonorm.dev/
 * Description: CarbonORM/CarbonPHP WordPress Plugin
 * Author: Richard Tyler Miles
 */

use CarbonPHP\Abstracts\Composer;
use CarbonWordPress\CarbonWordPress;

/**
 * The majority of this plugin is just to find and load composer.
 * If it is not correctly loaded, we will attempt to fix it.
 * todo - finding composer in the root but no c6 dependency required
 */

if (!defined('ABSPATH')) {

    print '<h1>This file (' . __FILE__ . ') was loaded before WordPress initialized. This file should not be called directly. Please see <a href="https://carbonorm.dev/">https://carbonorm.dev/</a> for documentation.</h1><pre>' . print_r(debug_backtrace(), true) . '</pre>';

    exit(1);

}

function throwAlert(string $message): void
{

    /** @noinspection ForgottenDebugOutputInspection */
    error_log($message);

    add_action('admin_notices', static function () use ($message) {

        print $message;

    });

}

$status = include __DIR__ . '/functions/versionCompare.php';

if (false === $status) {

    return;

}

[$carbonPHPVersion, $carbonWordPressVersion] = $status;

// Until we can verify all the setup
[$lowestComposer, $autoloadPath] = (include __DIR__ . '/functions/findComposerAutoload.php') ?? [false, false];

$absPath = ABSPATH;

$composerExecPath = $absPath . 'composer.phar';

if (file_exists($composerExecPath) === false) {

    throwAlert("The composer executable was not found at ($composerExecPath). Attempting to install composer.");

    $composerExecPath = stripos(PHP_OS, 'WIN') === 0 ? shell_exec('which composer') : shell_exec('where composer');

}

if (false === $autoloadPath) {

    if (null === $composerExecPath) {

        $downloadOutput = shell_exec('cd ' . $absPath . ' && curl -sS https://getcomposer.org/installer | php');

        throwAlert($downloadOutput);

    }

    $installPath = dirname($lowestComposer);

    $composerInstallOutput = shell_exec('cd ' . $installPath . ' && php "' . $absPath . 'composer.phar" install');

    print 'cd ' . $installPath . ' && php composer.phar install' . '<br/>' . $composerInstallOutput;

    throwAlert($composerInstallOutput ?? null === $composerInstallOutput ? 'Composer install failed.' : 'Composer installed successfully.');

    $autoloadPath = $installPath . 'vendor/autoload.php';

    if (false === file_exists($autoloadPath)) {

        $autoloadPath = false;

        throwAlert('Failed to install composer. Please install composer manually and run <b>composer install</b> in the root of your '
            . ($absPath === $installPath ? 'WordPress (' . $absPath . ')' : 'plugin (' . __DIR__ . ')') . ' directory.');

    }

}


// this is what will load on our plugin page, and if setup is not complete, we will load the guided setup
add_action('admin_menu', static fn() => add_menu_page(
    "CarbonPHP",
    "CarbonPHP",
    'edit_posts',
    'CarbonPHP',
    static fn() => print <<<HTML
<div id="root" style="height: 100%;">
</div>
<script>
    window.C6WordPress = true;
    window.C6WordPressAbsPath = '$absPath';
    window.C6WordPressVersion = '$carbonWordPressVersion';
    window.C6PHPVersion = '$carbonPHPVersion';
    window.C6AutoLoadPath = '$autoloadPath';
    window.C6ComposerJsonPath = '$lowestComposer';
    window.C6ComposerExecutablePath = '$composerExecPath';
    
    const manifestURI = 'http:' === window.location.protocol ? 'http://127.0.0.1:3000/' : 'https://carbonorm.dev/';
    
    fetch(manifestURI + 'asset-manifest.json')
        .then(response => response.json())
        .then(data => {

            const entryPoints = data?.entrypoints || [];

            entryPoints.forEach(value => {
                if (value.endsWith('.js')) {
                    // Load JavaScript files dynamically
                    const script = document.createElement('script');
                    script.src = manifestURI + value;
                    document.head.appendChild(script);
                } else {
                    // Load stylesheets dynamically
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.type = 'text/css';
                    link.href = manifestURI + value;
                    document.head.appendChild(link);
                }
            });

        });

</script>
HTML,
    'dashicons-editor-customchar',
    '4.5'
));

if (false === $autoloadPath) {

    return;

}

// Composer autoload
if (false === ($loader = include $autoloadPath)) {

    $msg = "<h1>Composer autoload ($autoloadPath) failed to load. Please remove your vendor directory and rerun <b>composer install</b>.</h1><pre>" . print_r(debug_backtrace(), true) . "</pre>";

    throwAlert($msg);

    return;

}

Composer::$loader = $loader;

CarbonWordpress::make();

