<?php
/*
 * Plugin Name: CarbonWordPress
 * Plugin URI: https://www.carbonorm.dev/
 * Description: CarbonORM/CarbonPHP WordPress Plugin
 * Author: Richard Tyler Miles
 */

use CarbonPHP\Abstracts\Composer;
use CarbonPHP\CarbonPHP;
use CarbonPHP\Programs\Migrate;
use CarbonWordPress\CarbonWordPress;
use CarbonWordPress\WordPressApplication;
use CarbonWordPress\WordPressMigration;
use CarbonWordPress\WordPressWebSocket;

/**
 * The majority of this plugin is just to find and load composer.
 * If it is not correctly loaded, we will attempt to fix it.
 * todo - finding composer in the root but no c6 dependency required
 * todo - add verify composer.phar checksum
 */

if (!defined('ABSPATH')) {

    print '<h1>This file (' . __FILE__ . ') was loaded before WordPress initialized. This file should not be called directly. Please see <a href="https://carbonorm.dev/">https://carbonorm.dev/</a> for documentation.</h1><pre>' . print_r(debug_backtrace(), true) . '</pre>';

    exit(1);

}

add_action('init', static function () {

    $consentFile = __DIR__ . '/licenseAccepted';

    // @link https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#7-plugins-may-not-track-users-without-their-consent
    $consentGiven = file_exists($consentFile);

    if (!$consentGiven
        && ($_GET['page'] ?? '') === 'CarbonORM'
        && ($_GET['action'] ?? '') === 'acceptLicense'
    ) {

        file_put_contents($consentFile, date('Y-m-d H:i:s'));

        $consentGiven = true;

    }

    if (!$consentGiven) {

        // todo - add disable plugin button
        add_action('admin_notices', static function () {

            // Generate the deactivate URL with nonce.
// Generate the deactivate URL with nonce.
            $deactivate_url = wp_nonce_url(
                admin_url('plugins.php?action=deactivate&plugin=carbonwordpress%2Fcarbonwordpress.php'),
                'deactivate-plugin_carbonwordpress/carbonwordpress.php'
            );

            $accept_url = esc_url(admin_url('admin.php?action=acceptLicense&page=CarbonORM'));

            print <<<HTML
            <div class="notice notice-warning is-dismissible">
                <p>CarbonORM is a powerful set of tools that can help you build and maintain your WordPress site. By using CarbonORM, you agree to the <a href="https://github.com/CarbonORM/CarbonWordPress/blob/main/LICENSE">CarbonWordPress License</a> and <a href="https://github.com/CarbonORM/CarbonWordPress/blob/main/README.md">Terms of Service</a>.</p>
                <p>
                    <a href="$accept_url" class="button button-primary">Accept License</a>
                    <a href="$deactivate_url" class="button button-danger">Deactivate Plugin</a>
                </p>
            </div>
            HTML;

        });

        return;

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

        // If composer isn't setup we will attempt to install it, and then load the autoload
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

// Retest this condition as it may have changed in the if statement above
    if (false !== $autoloadPath) {

        // Composer autoload
        if (false === ($loader = include $autoloadPath)) {

            $msg = "<h1>Composer autoload ($autoloadPath) failed to load. Please remove your vendor directory and rerun <b>composer install</b>.</h1><pre>" . print_r(debug_backtrace(), true) . "</pre>";

            throwAlert($msg);

        } else {

            Composer::$loader = $loader;

            WordPressApplication::$composerExecutable = $composerExecPath;

            CarbonWordpress::make();

            [$cmd, $isWebsocketRunning] = WordPressWebSocket::getPid();

            (new Migrate)->getLicense();

            $migrateLicense = Migrate::$license;

            $setupComplete = CarbonPHP::$setupComplete;

            $isMigrationRunning = CarbonWordPress::isProgramRunning(WordPressMigration::MIGRATE);

            $isMigrationRunning = $isMigrationRunning !== false ? "Migration is running under PID ($isMigrationRunning)" : 'Migration is not running';

            $pastMigrations = WordPressMigration::getPastMigrations();

            $carbonWordPressLicense = CarbonWordPress::getCarbonWordPressLicense();

            $wordPressUser = CarbonWordPress::getCurrentUser();

            $wordPressUserString = $wordPressUser->data->user_email;

            add_action( 'wp_enqueue_scripts', function() {
                wp_register_script( 'CarbonWordPress', '',);
                wp_enqueue_script( 'CarbonWordPress' );
                wp_add_inline_script( 'CarbonWordPress', /** @lang JavaScript */ "window.c6websocket = new WebSocket('ws' + (window.location.protocol === 'https:' ? 's' : '') + '://' + window.location.host + '/carbonorm/websocket');");
            } );

        }

    }

    $wordPressUserString ??= '';

    $carbonWordPressLicense ??= '';

    $pastMigrations ??= '';

    $isMigrationRunning ??= 'Migration is not running';

    $isWebsocketRunning ??= false;

    $migrateLicense ??= '';

    $cmd ??= '';

    $phpVersion = PHP_VERSION;

    $whoami = get_current_user();

    $setupComplete ??= false;

// all groups get merged into a comma delimited string formatted for a javascript array
    $groups = '';

    $groupIds = posix_getgroups();

    if (count($groupIds) > 1) {

        // Initialize an array to hold the group names
        $groupNames = [];

        // For each group ID, get the group information and extract the name
        foreach ($groupIds as $groupId) {

            $groupInfo = posix_getgrgid($groupId);

            if ($groupInfo) {

                $groupNames[] = $groupInfo['name'];

            }

        }

        $groups = "'" . implode("', '", $groupNames) . "'";

    }




    // this is what will load on our plugin page, and if setup is not complete, we will load the guided setup
    add_action('admin_menu', static fn() => add_menu_page(
        "CarbonORM",
        "CarbonORM",
        'edit_posts',
        'CarbonORM',
        static fn() => print <<<HTML
                    <div id="root" style="height: 100%;">
                    Loading CarbonORM...
                    </div>
                    <!--suppress UnnecessaryLabelJS -->
                    <script>
                    window.C6WordPress = {
                        C6MigrationRunning: '$isMigrationRunning',
                        C6WordPressAbsPath: '$absPath',
                        C6PastMigrations: `$pastMigrations`,
                        C6WebsocketRunning: `$isWebsocketRunning`,
                        C6WebsocketRunningCommand: `$cmd`,
                        C6WordPressLicense: `$carbonWordPressLicense`,
                        C6MigrateLicense: `$migrateLicense`,
                        C6WordPressVersion: '$carbonWordPressVersion',
                        C6CarbonPHPVersion: '$carbonPHPVersion',
                        C6AutoLoadPath: '$autoloadPath',
                        C6ComposerJsonPath: '$lowestComposer',
                        C6ComposerExecutablePath: '$composerExecPath',
                        C6PHPVersion: '$phpVersion',
                        C6WhoAmI: '$whoami',
                        C6Groups: [ $groups ],
                        C6WordPressUser: '$wordPressUserString',
                        C6SetupComplete: $setupComplete,
                    };
                        
                    // Define your URIs as constants for easy maintenance
                    const localManifestURI = 'http://127.0.0.1:3000/';
                    const liveManifestURI = 'https://carbonorm.dev/';
                    
                    // Function to load assets based on the manifest file
                    function loadAssetsFromManifest(manifestURI) {
                        fetch(manifestURI + 'asset-manifest.json')
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(data => {
                                const entryPoints = data?.entrypoints || [];
                                entryPoints.forEach(value => {
                                    if (value.endsWith('.js')) {
                                        // Load JavaScript files dynamically
                                        const script = document.createElement('script');
                                        script.src = manifestURI + value;
                                        document.head.appendChild(script);
                                    } else if (value.endsWith('.css')) { // Ensuring it's a CSS file for clarity
                                        // Load stylesheets dynamically
                                        const link = document.createElement('link');
                                        link.rel = 'stylesheet';
                                        link.type = 'text/css';
                                        link.href = manifestURI + value;
                                        document.head.appendChild(link);
                                    }
                                });
                            })
                            .catch(error => {
                                console.error('Failed to load the manifest from', manifestURI, error);
                            });
                    }
                    
                    // Function to try loading from local first, then fallback to live if local fails
                    function tryLoadAssets() {
                        fetch(localManifestURI + 'asset-manifest.json')
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Local server not available');
                                }
                                loadAssetsFromManifest(localManifestURI);
                            })
                            .catch(error => {
                                console.warn("Local server not available, trying live site:", error.message);
                                loadAssetsFromManifest(liveManifestURI);
                            });
                    }
                    
                    // Initial call to try loading assets
                    tryLoadAssets();
                    
                    
                    </script>
                    HTML,
        'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB3aWR0aD0iNTE2IiBoZWlnaHQ9IjUxNSIgdmlld0JveD0iMCAwIDUxNiA1MTUiPgogIDxpbWFnZSB4PSIxIiB5PSIyIiB3aWR0aD0iNTE0IiBoZWlnaHQ9IjUxMiIgeGxpbms6aHJlZj0iZGF0YTppbWcvcG5nO2Jhc2U2NCxpVkJPUncwS0dnb0FBQUFOU1VoRVVnQUFBZ0lBQUFJQUNBWUFBQUR3alFUSEFBQWdBRWxFUVZSNG5PM2RXNUxqeUJLYzRheXgyZjhPOUtJWExiUDBVSWRUYkRZQjRwSVo0Ujd4ZjJiSEpKbW1pMEJlblpFZytmVi8vdS8vR3dCc2ZHZGZ3RUZmMlJjQTRKaC9zeThBd0JqRFo0TS82dWo5RUJpQVpBUUJJRTYxelg2R3ZUWWhKQUFCQ0FMQWZHejRjMnkxSXdFQm1JZ2dBRnpIaHAvalhic1REb0NMQ0FMQWNXejh1Z2dId0VVRUFlQTlObjEvaEFQZ0FJSUE4SU9OdjRmWGZpWVlvRDJDQUxwaTQ4Y1lmNDREUWdGYUlnaWdFelovN0tGYWdKWUlBcWlNalI5M1VDMUFDd1FCVk1QbWp4V29GcUFzZ2dBcVlQTkhOSUlCeWlBSXdCRWJQOVE4eGlTQkFIWUlBbkJDQVBpaHZ0bDA3aWNxQmJCREVJQ3lqaHRLaFkzanlEMTA2ZHZ2VWFOUFVSaEJBS3FxYmhSc0NqLzIycUZhMy9QcEEwZ2pDRUJKcFEyQUJmKzZyYmFyTUQ0SUJaQkRFSUNDQ2d2OEE0djdPdS9hMW5uczhJQWhKQkFFa01WNUFkL2pmbDl1bTlMamVwM2JuU29CVWhFRUVNMTV3ZTZBL3NsRmxRRGhDQUtJd09ZQ25FTWdRQmlDQUZZaUFBRDNFQWl3SEVFQUt4QUFnTGw0amdETEVBUXdFd0VBV0k4cUFhWWlDR0FHQWdBUWowQ0FLUWdDdUlNQUFPUWpFT0FXZ2dDdUlBQUFlZ2dFdUlRZ2dETUlBSUErQWdGT0lRamdDTlVBVU8wclorSEQ0UnNOQ1FRNGhDQ0FQY3FMM05iaWRuVFJVNzQzYU5rYlUxOURmeXdSQ0xDTElJQjMxQmUyR1FzYWdRR3ZybzRyaHpBd3hzODFFZ2J3RjRJQW5qa3NadEVMbVVNSkdPZXNHRU5PWVdBTUFnR2VFQVR3NExDSVpTMWVNOXJtenJVNzlJMnF5REhqRWdiR0lCRGdDVUVBTGd1WDY0SVZlWXpoSW5MTVJaZkRuY0xBR0FRQ0RJSkFaMDZMRll0VUxXZjcwMm1zanVFWEJzWWdFTFQyVC9ZRklJWFRJcFc5TU4xcHEreHJyOEt4SFIydmVReXZ0UUdURUFSNitSNCtFLzFyK0M2bTBKSTE1bDNIcjlNNmdRa0lBajI0VFd6WEJSUjQ1UnhvbmRZTTNFQVFxTTl0TWlzdG1od0w2SEJ2VDlmcmQzc1RnUXNJQW5VNVRtRFh4Ukw2Rk9hQzgvaDJYRTl3RUVHZ0pzY0pxN1pJT3JZaDlLbU44N09ZRndVUkJHcHhUZTN1aStPcmF2ZWpva3E3dXQrSDZ6cUREUVNCT2x3bnB2dWlDQjlLYzZUQ3VGZHFUOXhBRVBEbm5NNVZGME1lRWtTRUNtUEZlZjNCL3hBRXZEbFB3QXFMSU9KVkd6ZFY3c2Q1TFdxUElPREpQWVZYV2Z6Z1IzSGVySjRQVWZQTmZWMXFpeURnSjJxaXJWbzgxRU1BeHdMSXNQS0xoeDQvdkJRWkNHQ0VJT0FqS20wL0Zvd1ZyOFZHaVJrcWo2T1ZZV0RsMzk5NlBSZ2dDSGlJcmdKMERRRXNYajJvOTNORUdJaVlqeHdWbUNBSTZJdXNBcXg2UFljUWNGZUhlMFNjMVdIZzhScFJnUURDQ0FLNkl0TDA2MEpBQ0lDTER1TXFJZ3lzZkoyOTE0UVFnb0NtcUNyQXl0ZDArOVUxRnFwZVhQcDdaUmlJcmc1d1ZDQ0tJS0FudWdxdzRqV2RBc0FNM2U0WHNWYU9MNm9ESUFpSWlRZ0JyNjlIQ0lDclRtTXRPZ3hFVkFjZ2dpQ2dZWFhKTEtJSzhIZ2RSM3gzUUU5dW0xRmtHRmo5ZW8vWGRPdURrZ2dDK2FLckFLdGVrdzBSV0M4akRGQWRLSTRna0N1NkNyRHFOUWtCeU5KeDdLMytGc0t0MTF5Sk1KQ0lJSkJuZFFpSWVrMzNoWmhqZ2Q2Y042Q01NQkJka1VBQWdrQzhpT2NCdGw0MzZyVUF4SWdPQXl0Zjg5UHJZaEdDUUt5TW80QlZyMXNoQkxEbzFGQmhMTjZSRlFhaXZ1TUFpeEVFNG1SVUFWYTlidmVGZHd6YW9KSUttMDVHR0ZqNXVrZGVHNU1RQkdJUUFnQ3NsdlVPblRCZ2ppQ3czcXFCL0trMFJ3all4ME9DdGRBblA3STI1WXhQTW1BU2dzQmFLMFBBM21zU0FvQytNdCtoRXdZTUVRVFdpUTRCS3grd0lRU2d1bW9iemVvd2tIRlVVSzJQWlB5YmZRRUZaUVNBVmFvR0FJNEYwTUZqcks1YUl4NS85OTJjK0ZyMHV0OGJyNGNicUFqTUZSa0NzcjZQQUZERm1IMHY2emNEVmowM3dNY0xKeU1JekJNZEFsYXF2S0N5Z0dCTDViRVI5ZlBDa1Q5ZVZMbS9RaEVFNW9nS0FSRkp1SElJdUl1MmdiT284ZnR1blNJTUNDTUkzTGZxQ2YzbmlVTXBERGlHc0xZdnNuMWUxeTNDZ0NpQ3dEMnJQNmFYRVFBcVR5b2VFc1FubGNkL2x1ZDFiT1Z6QTdpSUlIRGR5aENRWFFGZ1VnRTFaYThyZTU4MG1QSDNjUUZCNEpwVklTQTdBQUFWVUwxNVQyVnRlYXh6aEFFUkJJSHpWb1lBSldyWGN4ZkhBamlxMnRoWHRmcjdEWEFRUWVDY2JnTlg5Ym9Bbk5OdExuZTczMXNJQXNjeHNIelJkLzFReGZuVmRmeDN2ZS9UQ0FMSGRCNVFuZTk5RERhVXJxcU0reXIzY1ZYMyt6K0VJUENaKzBDYXNaRzV0d0dBdmxpL1BpQUk3SE1lUU0rZjErMzhycGFIQlB2cTNuL082OWRzdE1VT2dzQTIxNEd6OVlVZGR4ZEYxL1lBcm5JZTh6T3ZmZFdYQUVWejdzK2xDQUx2T1E2WWlNbnEyQzVBTjdORHdQUC8zVDBRc0lhOVFSRDRtK05BT1RvNTNTZnhXUndMZ0g2OGJxdnQzQU9CNHhxL0ZFSGdUMjRENU1xRTVJZ0FPTTV0dkVkZXIzTWdjT3ZYcFFnQ25ySW5vTU1rY3JoR1lLWlZSd0pIL2x2WFFJQkJFSGptc0hITW1uQk1XblRTWWJ4bmhZRFhmK2ZVMWc1cmZnaUN3QS8xQWJGaWdsVStJcGh4YmZ3QUZCNFlCK2M0QlFMNmRoQUV4dEFmQ01vVFNySHRabDhUZ1FEcUZLb0JXMzlMZWYxNmFEKy91d2NCNVFFUU1Za3FmZXZnNmcyYlFPQnJWb1ZJa1dvSWVQMjc2b0ZBdFg5RGRBNENxaDBmUFduVUorZ1JrWDFKSVBBeHU2L1UrdDRoQkx5K2h2SjZvOVMzb2JvR0FjVU9WNThrZXpMYk0rdTExVFlGL1BnZU1kVWhYS2U4MXJYczI2NUJRSW5DcEhBOUlsQ1l0QVFDRGRIOWtOM25idFdBcmRmTlh2dmV5ZTdiY1A5bVgwQUNwVTVXbWdSZlE2dHRQbEc3MXNmMUtQVnBkZGxqSUt2UEs0U0FaNDlyeU83UHRycFZCRlFHbW1vU2R2bElvVW8vdmtPRllEMjFObGE2bGpQVTFpQ2xkZEcxVHkvcFZCRlE2RmlWUWI3UzkxaDNud3A5ZUJRVmd2bVUreitxdjVYYllCYVZDc0hLdFV4S2w0cEE5b0JTU3JxZnFGNW5kaDllcGZidTFVM0V3Mzh6dVR5a3FEclBueW1zbXk3ajdwYnFRU0I3QVZFWXlGZW9IUkZVbUl6Wlk5R05jM3V0dU81dUllQlo5anJxT2c0UHF4NEVNcmxOdHRsbVRaNFZrekI3VVNtL3NGems5dTUvaitwOU9LOUwyWUdnTElMQWZGVUdxOEk5ekY1SW4vc211NTlVTjRvTWxkdWk4cmNhWnNtWXU2WDdvSElRaU82NDdJMWxoYXdqZ2hVYnc5YTlaUGRiNVUxd1Q2VjMvNS9jdWNmT1J3S2ZSTS9kc21PMWFoQ0k3TERzalVUZDJiN0lPZ3JJN3NkT20yS0grM3gxNWI0SkFjZEV6dDJTWTdmVHh3ZG5xenl4bmtWKzBaREM4d0RaSDEycStMRkQ5Y1h6dWExWFgydkdSOUlxamFVOVVYTzMzTWNLSzFZRUloYWRVb1BnZ0lnakFvVVE4UHB2cVJEY28zNFA3L280b3MrUHRJdHl1Nm5MbnJ0MnFsVUVWazhlQnRkMWV5azY2bm1BTzMrTENzRXg2aHZZMFdPaWlQdlltaE1jQ2N5eHNoOUxWUVVxVlFSV1Rsd1NwcyszQlZiOVRYWDFkOWZxMTNlMi82TDYrN1hOQ0FFK2xNZjdLZFVxQXJNeGtmNTBOMkcvcG1pWEVQRHVOYWdRNkMrRU05ckk3Y2U0SGhUR1J3Y2xLZ05WS2dJclAyK091Wjc3YWtVSlAwcjJHTWw4QjE3dDNmK1J2eGRCdVUxUldJV0tnT083U21lejN5SE4rSHVaZmRhbFFxQytTYTIrLzlYOXpKR0FML3VxZ0h0RlFIMXhxa3JwdHdoVUptRFZDa0czZC85SFhtLzIzNnM0SDdwUm5pTWZWYWdJSU1mTTV3V3UvQzNWQmE5S2hVQjVZY3Z1KzFsOXZDSlVBS2M1VndTVUZ5b2NjL1Y1QVljRno3RkNvUDYxdjlsdCt1cnU5MVNNTWErdGxkcWxLOVY1ODVGckVMQnQ4R0l5M3RHNExYalptOWZSTDY5Um5WTmZJNzhOOTl5NUx0VTJyNFFmSnpxQW93SGNOZnNqaFo5ZXk1WGFrWUg2Z3VYVTEyZmJsT2NDSU1XeElxQytnT0c4STBjRVZSYTc3SGUzdlB0Zko2T2k1ZHBXbGFuT3IwMXVRY0N1Z1p0WXZiaFZYT3ljTjd6WktyWEZrZnZndVlENnJQWXF0eUFBWFN1ZVVxKzBRV3pwY0k5YnF0NzcxbjI1ZmtzaGluTjZSb0FKVk4vclJ3bzdjVG03djZ0VHY3N2U2OG92NHVyVXJpNXN2bWpJcFNKUWZYR3N3bUxRaSt2Mkxobm52V3RIMWtoTkZ2M2lWQkdBbm5mdlNDSS9SVkJaaFFvQi9maHI5bk1CNy82ZTBnOVN3WWhEUmNCNUlheHNyMThxZjZ0ZE5NZDMwbzdYdk5MczhYemtleUh3YThZWFA5MGgzeC9xUVVDK0FadmFlemVDTmRRM1YvZVAvams0VTIxalBzNVRQZ3lvQndIbzJSdlFNeDlla3A0NGlkUTJXN1hyVVpQNVVVSGw3NHh3MGFMOWxJTkFpdzR3Y21WUklReXNrN2tCOCs3L21PZ2pnZG4vRHI5S1Z3V1Vnd0IwbkJuQUtvdGZGNUViTXB2L2NTcy9LbmdGOCtpK3NtTmZOUWhrL2J4bjJZNis0VXBmOFBubWVLczJhZDc5NTVyNUpVUWNGZVNUYkgvRklLRDJHOStkM2YwWTRBTkhCSEZtYmRwcy90ZXBmNFV3OCttNmtrY0Vpa0VBR21ZUFZzS0FCd0xBUFM3amxPckFkZVhtaDFvUW9CcWdZV1lwRXVqQzhhZUZtYU01cE5wZExRamNRUWk0Yi9XN0JLb0N3R2ZSYTFtSGVjWFBQKzlRQ2dJZEJxT3lGZTIvOVF0c2R6Qk9vR2psY3dFUm13NUhCZkZrMmxzcENOeFJLcDBsaUFvQlFFVVJDenBIQlhtMjJxVE1nNE1xUVVDaU1acktDQUZVQlZCRjVITUJVUTl5TXIrT0svR0dSeVVJM0ZHaUk1S3MrR1FBNzF5QTg4N01HNDRLYWtsdlo0VWdjS2NSQ0FIWHJKamtaL3VDdm9PN3pBVThzanFRdmxHSnMxL0wvazErZlFaWVBLWG5BZTUrYTlyM2pkY0c3bEQ1cU9ETWJ4N2NjL1UxbUovSHBLNWwyVUhnamhXTlJqQTVMM3VpRXdZUVRTVUVQUDhOMWJYcnluVTV6bWZsUHZnb013allOaHIrVTMwUkExYWF1ZUU5L2xhRnViU3ErckI2cmJHdGNMcFdCQndUWXpXekZ6SExDVlFRYmJsUGZhUHRIS3k3M3ZkdFdROEwwbUcrVkwrTG5qR0YxZFNPQkRMK2RsZEgrOTd5bzlFS254bzRpMEdlUjMzeElneGdGWmNROFB3YXJKVTRKQ01JUkh4Y2tBMWh2cWpGQzZnc2Vvd3pwK0xaVlFVY0t3S0lGN21ZMkUwaWxPYytwcWdPWUZkMEVPRExnL3c0dHJ2N3dnMGRia2NDeXEvZmlkVWJHaW9DMkpMNUxvSUY2ejdhOEw1S0llQkI1VG9nSkRJSVJGVURlRGRZZzFXaUJuYW9iYjVxMStQaTdKcGlzNFpSRVVCbGhBRmN4ZGhCRzFGQmdHb0FydUNkQ3pKVVBCSkFEb3VxQUJVQnFMT1lTQ2lqUXdoZ1RseFhzdTBpdm1MWTZaTUNxaFAzanBJRDk2UU9iY0NQY0NIQzh6aGpmQndqL3hYcXJyODE4STVUNEloMGRSQXFmZWQ4NSs5UFArclJQaXA5MWwySGZyaDZqOHhsTWNwQklHb2lkWml3RlJBRzRJSTFaZCtWOW5HZis5SlZnZFZCd0wzekFPQU1Rc0FhU3RVSHBXcnBGS29QQzU1dFpBSkhENlVtSDREbHZ0NzhML05hN2xpMno2ME1BZzZiYzVlTnBkSjlWcm9YMUZKOWJGYTV2K3hBSUVleElrQTFBSUFibDQyRjlYS09xKzBvT1U1V0JRR0h3U2JaSVdKVSs1RytneExHSTZJc1daTVZLd0lBQUt6R2o2cjl6NG9nRVBsNWZ0VjNyQUI2a0Z2VUVTWnIvNW4rdWwwckFreGVZQnNCK3pqYUNsZEk3VUZLUVVDcVlRcXExTDRzdmtDc1N1dkhzNnIzZGNyc0lCQzVRTE1aQU1CeHJKbnpaWDIxL2RTK1ZLa0lSS1l5RXVBNUxCNWU2QzhBcDh6OGltRVdJRVFvOWZsZGxGRHVLMmViY2YwZGsybmpUdmxIaC9hd0dRRHJkWm92amhzQk5OelprQ1ZDaU1MUlFLZkZCb0FtMWlHME5Tc0k4SkNnQi9mRmprclFPVjN2T3dQcmtqZlhIeU9hTXU2eUt3SThKQWdBMmxnN1A3TU9ndGxCQUI2c0J6bHdVT1VOanpsYzErMituUkVFT0JaQUJJNEZvSTcxeVp2cjhjQnRtUlVCamdVQUFGWFlCc0c3UWNEMnhnSGdEZDQwSUV2YVE0TlpGWUVyTjB6b21NTnhvZU5ZQUM1WXA3eTFYRE02UEN6WXNtTUJZQUxXendidUJBRWVFdXlsekc5dkF4OVUyL3lZUTNIdXRuWEs4VUJHUllDSEJCR0J2a2NXTmw1WTZYQTBBQURBVWUzZVJGd05BaHdMSUFKOWp5enROZ05NWTNjOEVGMFI0RmdBRWVoN1pDUEV3Z1pIQXoyeFVRTEF0bFpyNUpVZ3dMRUFJdEQzeUZaaE03Z3pqeXJjZnhhcjQ0SElpZ0RIQXY0Y05tZjZmZzZIdmxaSEc4SUNSd01BQVB5dHpac0s1U0RBMThyMnhUc3BxR0E5d1ZVMnh3Tm5nd0NiTTVReHpxQ0dVQXQ1cWhVQkpzOTZiSm9Bc0svRk9xa2FCSzVxMFduRkVRS2hwdU82MHZHZVY4ZzhIampzVEJEZ1dBQmo2RzdVakxQM2FKZDgvR0FYTWh6dWY4V0tBSU1YQUtDaWZKaFdEQUpYbGUrc0JnaUI4OTFwVS9yakYrc0xycEkvSGxnZEJKZzhpTUE0ZTIvR1JrNFltSU4yUklaRDQrNW9FSWdheEV5V1dHeWdkYzJjUzkrVC94N2dwdlJhV2VWb29IUW5OY0ZHTThmS1RidDdIM1ZaWjdyY1p5VHB1YU1VQktRYkNuOVE2aXNXclY4Ui9hTFU5OWhHUDlXeWRKMDdFZ1RVUHpiSVJvRHVva3YzSEJWY1E1dDVjOTFyUG80N3BZb0ErbUtCdkM2ejdlZzNvQUNWSU1DQ2dpdGNFL29zQ3ZPbVczV2crNWpEZGJJZkkxUUpBbGN4S2UrakRmMG9icjVxMTZPS2RvS2NUMEZBL2ZrQStHTmhQRWU1dlpTdkRaakJkVy9iblpzS0ZRRVdEMC9aL2VZNkllL0lidk1qRktzVnM2bVB2VHZ0cjM1djdpVG5oa0lRdUlvQml5NGNOMWUzNjQxRTIrQ3FKZnVlY3hDQVB4YkV6NXpieVBuYWdTM2wzb1N1Q0FKbkdvbG5FSEJGaC82UHFBSkV0S05qTmVPSURtTVFhOGpOaDcwZ0lIZXhXSVpGVFV2RTNQdDYraitqQWdGKzBSNkl0am5tTW84R21BaTkwZi92UlZRQjNtMzhoQUhndUZKdm5oeWZFU2pWQWVZeUZ2YXEvYTl3Rk1CUndUblZ4bUsxKzFFbTljbU8yVUdBZ1FTY0Yza1VjT1Mvb3pvUVkwVWIwSzQ0TGFzaXdHRHRqWWRFZjJVZEJSejVkNnV4RHNDWjQzcjBkczV0QlFIVkNlclk4TUE3Q2tjQnEvLzlFZTVIQmF4SnVFcG0zRHMrSTRBMVdORGlxRllCVnYrdFBUS0xZckN1OTQxN3BzN0pqQ0RBd0svbDdEdTZ6c2NDRGxXQTZMLzd6TDA2a0kyMmkxZGhYWm9hQkZZM1NJa0dMK3g3SEZ2SXUvYWowZ09CcW4vL3dXMUR5eHpUUitmZEoxM25aVGFKc2Y1djhPdEozRFNXZSs3blQ1OVo3ekFtWEtzQWU2KzErcDYrUjUvTjZleTlkcGd6Q1BRdUNDZ09zaTRMUXFaVi9YNDBGT3k5dm12L1Y2Z0M3TDF1UkJoNHZGWjNpdXN5ZmtUTWhabitDcDQ4TElneDRnYnhYZ256YThROW1CYWhjZ2g0Zm4wZUpQeXhvaDFtbGYyUHZoWnlwTGQ5NU5GQStzM2lyWXgrbVZFbFVOVWhBTHlLcWc2bzNmZGRyL2VUUGQ0cnRuRmwwK1pkOURNQ1Z6QXcxOGxlZU1iWUR3VnVmZDh4QkR4d1ZIQ00ydWIvaWpCd2pkdnh3QjltQlFFR2poL0ZRZXU4MEhjT0FRL2RIeVRjMmd6VU4zODBGMVVSWU9CclVlK1BUMGNIU2dnQWYrdDhWTEIxVGVwejdwbHEyMWFXMnVhdkR3dXFEVllHNDN4cWZmeEo1QU5UWnhFQ3R2RUZSTnBqOXhQSGE4WnhmL1F2bnhyb3hYMXlLeTJzaElEUE9uNnFRR21NM2xYaEhpSmx6TmNwcnhseE5NQmcwbEN0SDdLT0R3Z0E1MVUvS3FnMnQ1NXhUQkFucmEyVlB6WEE0SnVuOGtJMVJ0eERob1NBNnlwOXFxRDZmSHBGR0NodVJoRFlHeURkSm95aW1YMmcvdFhBcTZvRVVmZGFmYkYxL2xTQjRuaUhIc3VQRWFwV0JLb3ZpRkZXaFlEWC83Zml3SjhWQ3FnQ3pPZFNIVkFjMTFtb0NzUklhV2ZWaHdXWmdQZXREQUh2L3YrVkY0bXJEMjhSQXRaUi9TWERDZy83cmZxNmJ1YzJ3WTduaXNEc1RtYlE1SWtNQVZ2L3JXTC9INjBTY0JRUVErV29RSEdzbnJWMWY3T3JMMVFHUG5NNUh2aXZMMVdQQnNaZ3dGMlZGUUwyL3EzaXBOZ0tCVlFCNG1VY0ZTaU95Yk9PamlQQ2dKZnc5bDBWQkdZTk9nYmNPU29oWU85dktTN0FrZGZFZUg0dnNqcmc3T3I0SVF6VWRidHZWWjhSZU9ZK2NhT29oWUN0NjFGL25tQ2xydmQ5Qm0zMHQxbG4vand6NENPMGJWZFVCRmJjQU9sem4yb0kyRHVUVjY4U3pNVFlQY2ZsakhXRjFXT2xjOXRHc1d2ZnUwRWc0eHZkV0ZUL3BCWUN0dXoxWCtWUXdIaTlwdEpaL2lkN3YwNm9QbjU0ay9acnhRUDNJVzA3KzJnZ1l0SjJXQmlPY2drQnp6NTlOS3ZTMFVHVis4aFV0UTFmeS8yUkgxdmtpR0MrVlcwUTByYktueHJZUXdyVkRRRkhyK3RUaGNlNVN0QjliTTVXb1p6OWJreGszaE1QRDg3alBqYW5WZ1NpRzhPKzhXOVFEUUZYSEhrWHRPb0xVbFp3dUVaSEx2My96bXVvUGZQT2YrVTZSMlhndmhKVmNOZUt3RVBINXdZcWhZQXJWQ3NGam0zcHlLVTZzSGZ1cjRUS3dEVVpiM3lYdGV1akluRDNwcklIZWZiclIxRVBBZEg5b1BJdVVlRWFPbEZ0YjZmSzFUTzM2ODJXdGQrcytrU2V4ZmNJSEZVOURLaUhnRHRtZkQ0NmF3RldhOHN1RkRiY3J4RXo5dHdlSUt5OEZxdmUyNjMrbTNFMG9OUXdWY3RTbFVQQWJGRkhCOVhiMFVYMFVRSDlma3kxdFZobG4xdlNydGtWZ1FybDZkVmNRb0JpdTd1V2FuRk94SmZ3M0IxSERtT1Fod2ZmVTd1UDZkZHpOd2pjdWFDdmwvOXpwcWpQNDY0MCt4NVVGNktvNnlJUTRBeTFFT242aTVnVjF1SHlzaXNDRDZzbW5Hc256cjV1bGNWTWdkb0NqL3RtelJmR3hnL0NnUDZieWFuWGxoVUVqbnlKekN6S25mbU9Zd2h3YStNWk90NXpWVkdidjFQQTZCd0dYSzUxMm5XcVZBU2VkUTREamlIZ2p1enJ5MzU5NUhNYUF4a2Z6KzNHN1JNYVUyUUVnU09OMERFTWRBc0J3QXpxOHhxLzFQdHE5ZlU5VjU1bXJjOVRybG14SXZDd29seW5ldTR6KzZIQXlCQ2cySjZBS3JlQTN1R0lJR0pma1A3RVZuUVF1TklZMWFzREhUNFo4STdLdGQ2NURxVnhoUE5VeHVBWkdXT3VjaGpJUEFxUUdYL0tGWUZuVmNOQTF4QUF6S0F3aDd1b0dBWWlqd0wyL3BzWmJ0MUxaQkNZOFRXeXMyVU94Z29oUUdFeUEyNWNRM3VWTU9CK0ZEQ2RTMFhnb1VvWXFCQUM3bEM3Wm80SCtsRWJnMmRrampubmRodEQ4MU1CNlcwYUZRUm0zcWo3UTRUZFF3QXdBd0VzaitzUEZDa2NCZXo5MnpSdUZZRm5qdFdCU2lHQWhSaTRMbnYrS29sWWR4Mk9BdExHUkVRUVdIbHpUbUdnVWdpNHcvbmF0eENLdkZRWWc5bGp6dVY1QWNXakFEbk9GWUVIaHpCQUNOQkh1L3JJM2dUeFF6ME1PSWFBbEhWb2RSQ0kvR1c1MldhVmt5cUdBQlppNEQ2VitYeUhhaGhRZmg3Z3lOOE9WYUVpOEtENEM0WVZROEFkRmU1aEMrSElRK1V4bUVVcERMZzhEeUJsWlJESWFpeVZNRUFJOEVNNzZ5TncvVkpxQzRXNTQzZ1VrUDA2WTR4YUZZRm4yV0dnY2dpNGVtOXE5d0ZBMTlsMXh2a29ZTzgxUTZ3S0FncUxmbFlZcUJ3Q3NFL3BIUnIrcGppZkZLL3Bxb3dqQW80Q0pxaGFFWGlJZm9pUUVPQ1BkdGRGMFBxYldwdEVob0ZLUndHcHI3OGlDR1EzM0t1b2h3ZzdoQUNPQlFCOEVoRUdLaDRGYkZsK0hkVXJBczlXaG9FT0lRQndwanl2bEsvdHFsVmhnS09BQldZSEFmVUd6SDZJOEJQMTl1dUNIeUhTUTd0dVUyMGJwWThWSHFXNkJpKzlyazRWZ1llV0hUMEJ4d0lBem5LYS8rclh1dXo2WmdZQjlVWjhwblQrTTRiV3RRRFZPTXd2aDJ1c1NtMC9DTmV4SXZCTW9mTVZyZ0YvNDNoQUIrMzVtWEliS2E5eHl0ZjJ6cExqN1gvR25BSGsxcGpQTXEvZHBkMDRGZ0J3aCtKYW9IaE5SMHovb2FOL0Z2eFJSeGx0UUxzRDZ6bk5NNmRydlVMbC90b2ZCYnlhY1RSUXBVRWo3Nk5LbTFYSDhVQSsydkU0aDdiS1h2dXlYMytXcWZmUi9SbUJWeEZKMFcwZ2Npd0FZS1pLUDBoWHdvd2c0SkJDenlyek85TkFZNDd6emZHYTFYRVVzTytiaXNDMjZROWtUUDU3MEZjeEpFZWkvYzV6YWJPU1ArY2JhR28vendvQ0xvUHZyRm1EeUhVd2NpeFE2MTRBSlJ6RFhqTjd2LzJpSXZCWjFjRUVWT1k4YjUydi9TeU9ZUVhNREFKVnF3Smo5RHhqcXR5ZmtXakhhMmkzNjl6YWJ1YmFXbjJ0WHRLM1ZBUml1RTNNT3lwT3dvcjNCRlREUEwySUlIQU9BdzNRVjJHZVZyaUhTQjNhYTlrYnl0bEJvTk03Mzhyb1IyUmkvTjNuMUlaM3I1VVFjTk1qQ0hSb3lHeE9FL09xeXVPSWJ4a0U5RlJlY3lKOGpiSG1hS0Q2b3NmQUEzUlZtcCtWN2dYWExkOVRlVVlBcjZvSE9XaGovTTFEVytLUVZVR0FBZmhlNVhicDhPNkY0d0ZnTHViRnZwRDJvU0p3VFlkTkQzQlRjVjVXdktkWmFKdEpWZ1lCa3A0ZitneVpHSC96MGFhK3d2cU9pa0M4aWhPelV6TG5lQUNZZy9td0xiUnRWZ2VCeWgzZGFmTUQxRldlajVYdkRRS29DT0NoY21pRFBzWWZ6cWdjanNMbkFrSGducXVEc2RLaVYzbENybENwNytHQk1ZZGR6MEZnMVlMT0lFUTFoQjh0OUljZjlvWDNJdHZsdjNsRFJRQmpNQ21SaS9IM0dXSG5WOVcyU0pzSFVVR2c4a1R2ZkR4UWRVS3VWcUh2NFlVeGgwMVVCSHI3SGl3UVZ4R0NOTkFQcUNCMUhmNDM4TFcrQjVOV0FScy9sREFlai9zYTk5cnIrZDltcnNYMHVaaklJRkRaMVFrYUZZNlllQUNlcVlTQ3M1eXU5YWowOVRuNmFDRDloaHY1SHV0TC85MzdrMjhaekZWeFU4andQV0xXQzRoNnJRamNMVDNodkpsVmdZeSs0OGdIVjdIV25CUFZYcSt2dy94ZUoyc08vTkduR1VjRFZUZU9yQkNsc0pnK3JxRml2d0xac3VmNHpHT0U3SHRSSXRNV1BDUGdTV1lBdmFnYTh2YmNDWUFkMjJ1V0x1Mm1OdGN6cXdWZCtqeGNWaEJnQVR4UGJVSFlRblhnSEpkK1JTeVhjZUg2MEdFMnFmNmxJakRYMFhlSFJ5ZU0xR0E1aWJBSFhPTTY3M20yd0JSQklNNlJTZUc2QUd6cFVoM2dJZHRZVmNkVHRUSDBybHJ3dGZILzM4bU0rNTY2NXJ3TEFsR0xXb2QzakIwMy8zYzY5RFZ3Ui9WMTRHNG9xTEorS1BUelgyMUpSV0ErTnYvM3VsUUhnRE02cndVUFg0Tkt3Um5UMTlEc0lORHBuU0tEKzBmVlB1ZDRJRWFsc2NONCtmRmFMYWpVeDg5ayt6czdDRlFuMi9GdnZKdDhxNjZmNmdDdXFoQWtuZGFGYUh3S1lkK2pUYWFPSVlVZ1VHRmlQM09hNUovYWZmVzczRXA5NzlUdjdwekhEZVBrdUhkdFJiOHZvQkFFS3BEdTVCZG5KeEpoNERPbi9xL0NiZHhFajVHcVorNmRLd1l6N3ZmdDM5Z0tBdEhublc2VGVneXZ5WFczYlplVW81NXdWSURLSXRlS2QzT29ReWdZUTNmOW1QVnh3V1dvQ0p6ak5JbFdEQnlxQTM5ekdoUFZxSStYekNyQTBmK20wdmhWREFZVzdhc1VCRlFudFVWSC9rOUUrMUVkK09VME5xcGkzYmgzLzEyQ2dlSVlPV3A1SHlrRkFTVk9reUZyZ0ZNZGdBcWxzYUpZQmJqNjk1eld3VTh5cWdVMjdiY1hCTHA5THRycFhsVVd2YzdWQWFmeGdoZ3VWWUNycjFGcHpMdFVDMlplMitiZldsRVJjUHBaVnFlQnJUNVlPMVVIbk1aTkY1bGp4TDBLY09WMUs4MkJGZFVDK1FjRW42MDZHbEN1SnFoZTF5dWxqZStJenRXQks2cmN4MTB6eDB0R0dLaFdCVGlLYXNHeGZ6L2Jrcis5SWdqY25Zd3JKclBMSUZXYTZGZFZydzY0akNVWHM4ZEwxUGpvVWdVNGltckJYS0g5dmZKaHdleXFnTXRnVkovZ1YxU3REc3k2bjRwOWZvZGJHT2hhQlRpcWM3WEE4bDQvQllHc3pmenFSSGJwaERPL1VPaTRFRHhVcWc0UUF0YktmdU53QkFIZ0dxb0Y1Nno2RHBoTnF6OCtHREc1WFFaVzE1OG5ybG9kdU1MaEdxdVlHUkk1QnBpblM3WEE2dSt2Q2dJekp1SGUzM0FaUEYwMy8zZWNxd05kK2lpYjRoRUJWWURyM3JYZDZ6MVdyUlpjbFRJR0lyNVFhTmJrZGhra256cnk3SDFrUHh3M2syTjFnQ09CV0NwaGdDckFQVnZ0dDFkT0wzUG1mbEhhR0RnU0JETFA3cHdHQWUvK2ozT3VEbHloZEMwT3NzTUFWWUE0bjg3WlY3OTU2T0RqR1B0bjRZdTdmSFBUSFY5UC84TTVxOXVzVXhXcW9sL0NEc2dBQUJJS1NVUkJWSXc1OVQwSUFkbTIydjlyMUY1dloxZVNUK0czQnM2Nyt3TWZWenBVN1IzdUxNcEhCUndKMVBKcERoRUF2UEJzd1VRRWdXT1l1R3RWUFNwZzNOd1RjVVRBc3dEelpiZXBXekJZT1NZTy9lMmpSd016THRSdEFxaVZvZHdHOTFtcjIvcE0yYmQ2V3p0WitTMmowVlVBbGJWRTJheTlSbTM5M2lKeGZTdWZFUmpEYjBHTkdEd1NIUzhzKzlrQmpnVDB1SC9sT0dNaGwwc28yTEo4dkVZZkRTaCtlNWpyNEtnczY5a0JRb0F1eGJYams0N2pRTDJQbEk0UlpNYkhtWXFBekVWUDRKb1ExU2ZaYk5uVmdTdmN4bFJYcTU4YllSeDRjTjBMampoOFR4RVZnZGNKbDVYc2xUcmE4ZDFObHBYVkFaNDg5ckxpNGNIWmxOWVpOOWx0RjFrdE9IcXZJZXRTOVU4TlpBOHN6RE43RTFnUkFoaHY2eW1IYVBxL2xqWnZGTEtDd01ySlhIMHlWdjFPZ1NPVXYyV3NhNTlrVUFzRDlQMFBwVDZaVGVuWmdpTk9qY216bnhxNE91QlhONXJqT1kvVHRhcTUyM1p0a241aHMrZlAxYi9IUEo3RHJSMGpyamRzYlhJOUduQWJOSmp2YW5XQUl3RzhjMmNjQWRaV2Y0L0FDa3hBM3NVK3l4NFAyYS9mV1ZiYjArY281VW9RWUJMTVExdk9jZlJZaUNPQmVpTG5rTnZ4WXlUbTAzRkh4dENkOWp3OVJpTXJBcDkrYmhLNEsvb2pPWXhoRGF1ZkZ5QUFyRVhiSm5OOVJnQzlQejJ3Wit1SmNwV0h5M0RQVnQvTy9DUUJjd3VmbEtxQVhLMElxSDU2d0JFTHpueXY3K0JtSHduUVp6bTIrbTdGdXNKYWhTeWh4d0pqZUZZRVNPczRhc1U0MmZxMEFtTnluYU1MNDRwdkhxUmZQeU0wbVhQODFBQitNUUhQbVZrNlh2VzM4YWV6UHgzdC9rdUYzUkMwQk53SkFqT09CeGdFUDJpSDlXWWRDZXo5bmU5SnI0TnJiYmtxREFCUndvOEZ4cUFpZ0I0aVFzRHMxK3ZzVHZ1dENBUDBKMG9qQ1BoamtkcVgxVDVVQjg2YjFXWThQQmlIZHBrbnJTM3ZCb0dzVHc5VUhIeDMzc0Y4di93UGM5MTVDSTMrT0daMk82MTQwSSsrbkt2akVjNnFlNzcxZHgwL05ZRFBlS0w5aDhwSEJUbTczaGJ4ZzJSczRNQ096Q0RBUjNQK3RtclI2aGdNWm4rTWJOYmY2ZEQyUjBWdDBIelowRDBFcVQrcEhUM2RIbzh6Z3NEZFNVWmlqOWN4R0Z5eG9sMDZiaVN2TXVZN1lXQWZhM0JqSEExZ2pKaXY1STJrV0ExNDl6ZWQyL2lxS2h1T1d4aFFhM2VudGxzdHZXOW1CWUdyYVR1OUFiREp0V3JnOUlOQ2JwdkpIZEZ6L1YyN1Z2N21RZGJTbnFhTVArZUtnTklrN01BMUdGd1JlVzhkcWdPUm05U25kblEraW5TOWJvaHpEZ0xJcFJnTVZueEtJR3JqcUJoc0Zhb0FXLytkNHZNQ2JQVDlTUFQ1ekNEZ25MUnhYM1l3V0RuMklzUEE0L1hjS1ZVQnR2NU5kQmhnZmZ4UllYeGZJZnRUNkZRRXRGUmFLQ0lmUUl4NExtRHJWd2RYY0s0T3FGWUJWcXMwZDFkekh0OHp5WXlaMlY4eFRPZGlqL0kzSUo0cEswZFFiS05Qb3FzQWQvdUM5UXBucWN6SnFXUFgvYmNHVkRvRjE4d0lCbG1mU1kvaU1NYWpROHZNOWljTW9EMk9CcURrN0hNR21SOFZqRDRxZUg1TkphNEI0UFh2T2dTdVNyb2ZEMGlOdHhWQmdFbDFEVzMydDRqbkRHYVVsNlA2VG1ueHJCQUFYbCtET1FnSDArY0RGUUYvN3daRjVRVk44ZDQ2VlFlNlBnd0l6Q0szaHExNlJvREptK3ZyNVg5NFQ2MjZjRWJHWXVMMk1PQ1YxMFFjdVEzUndKSXhXcUVpb0ZRdVZkV3RhbkRFNnQ4RnIxUWQ2RlFGNElnQUswbU9yUXBCb0lLcmcrUE9ndm42YnlVSDZDTFZ6cHhYaHVGcXp3SWNRUmo0eFJjbHpURmpiQytiSHl1REFKUEpTK2Rnc0lyemx4QjFxZ0owbHQzdW5TcTZzbXNxRlFGc3FSb01NaFlkdDY4bzdsZ0ZxSXIyblNkekRWemFqNnVEQUZXQmRSUWVwSExyMit5ejV6RzBxd05VQVg0d3JzKy92bHViUlpOdW55b1ZBZWZ5a3ZRQSthQnExV0FsMWVvQVZRQmQxZHZMZWYwdUlTSUlrQmI3VUE0R1NndU5VbldBS3NDZnFuOWxOZndzSHg5VktnTFFwQlFNRk45MVpGY0hxQUw4YWZWUFdWZkdHejVqVVVHQVFmSmV4c2NHTTJVL1o5QTVESXp4ZS85VUFkYnBkSzh6S2M1TkJTRnRRa1VBMlpTcUJsa3l2b1FvZ3RQQ1BxTmRuTzRYK0Uva3p4QXpTWERFNnE5SFZnNGFsZVpJcFh2Qk1mVDVYR0h0V2FraVFHbXBwbTRWZzhqcXdBcU9jNUJxZ0FibE5keDFQaDRTV1JFWVE3ZVRNM1I3UG1DV0dmZnZNS2tkKzlueG1nRkZvWE1wT2dnQU0zUUtBdzZicTh0MXZrTTFZQzdhd2xCR0VHQ2dZSVpPNDBqNVhwV3Y3Uk9ITUloK3d1Y1VGUUV2em91dUlxZU5RTzFkdDlyMVpLRU41bkthazJWa0JZRUtQNWw2aDh0MXF1dTRDQ3ZjczhJMTNNVWNYS2ZDK01pUzBuWlVCTkNkNDRhUTlXNmNLc0NmYUF1VWtCa0VtRVNZb2ZNNGlycjNhZ0dBQndTMU9ZYnpHZExHRkJXQmVIeHNjTDY3YmVPODhLemVwQmwzUUhIWlFZQkZCaXFjdzhBWTgrZFN0U3JBQTlXQUdKWGFLR0p0U0cydjdDQ3dndnVDam1zcUxUeFh6ZHE4YVV0azY3U09wODgzaFNDUTNnakEvM1JhZkxaVW5vOVVBNEEzRklKQUp6d2ZzQmJ0aEMyRXZIak14ODhrMmtnbENFZzBCakRZTUxDTmRTb1djekdJU2hBWWcwbUdPYnI4RGdHT296K2hTR2JQVXdvQ016SHhlNU9aWUVtNjMvOEt0T2sxdEpzQnRTQXdjOUNvaFFHZUQvQ2lObjV3RFE4SWVxczZENlhHbEZvUW1PMTcxQjFJMkNjMTBRQllhckYvS0FhQkZRdDRpODdFZEl3YmIxUURORGkyNGNvM2tYTHRvUmdFeGxnWEJySVdkbzRGY3RCK2dMK29kZnQ3ck44bkpOY2sxU0N3RXNjRnZYVCtIWUxPcUFiZ3FQWjdnbklRV0QwSjIzYytEbU9jZUtHLzlLaUZxb2gzLzYvVTJ1QS95a0VnQ29HZ1B0a0pDRm1NR1MwejF1aU16ZjlCZWp3UkJINnRHaUE4SDFBRFlkRUQvWVJYdk5uN1FEMElaUHdVS2dPbUpvSVZqbUtzckJIWnJwbnYvbC9KanlmMUlKQkZaUUJCQzJOQ0d3OElnclg3QXBjZ2tEVTVHVlMxOERzRWdLK3R1YWYwN3YrVlJiQjBDUUxacmc0eW5nL1FROXZXUkRYQXcreXZrVmZjL0I5c3hwTlRFRkJvVlBXQmh4aU1BU0NIOHJ0L1cwNUJZQXlOTURBR0E5R2R5ampDSEZRRG9NWnFQTGtGZ1RHMEduaEZJRkM2UDJ3akNHcWdINkRHYmcxM0RBS0szaTFHTEZEYWVIQVFEM1lMdHpuYVc0eHJFRkFjU0J3WCtGRWNSemlPK1FZMWxtdUtheEFZUTdmQkNRUzkwTmZlVk5jUitMRWRTODVCb0NMYmdXU01OdmZFQTRMZWFIc2g3a0dBd1FRRlZBV0EzcXozSXZjZ01JWjVCMEFDWThnTDFRQnYxWTVQN2NkU2hTQUF6SEIzTWxkYTJJQlZxczBUK3hBd1JwMGdVS0V6S3R4RGQ5VVdPVVZVQTN4bHo0K3ZwLy9oU1pVZ01BYWRpL3NZUTlxeU54SmNrMzBVOExyNXo3cVdNdXRGcFNBd2huZkhzTWpWUUQ5cWMxNGpIR1hOaDYxMy80U0FOLzdOdmdCQXpOZGdNMWRFbjNqSkRBQmJDQUVicWxVRXhwamJTZEhuU2RrbE5NeEJIMm9xdDRDTGloNy9SODcrbVpNN0tnYUJNZVpOK01mZ3lRZ0V5TVB2RUdqaEFVRWZrZU0rNDhHL2t1T29haEFZWTM0WW1QazNqNzR1bTBtZWtoTWVXQ1JxdmJyeTVEOUhBaDlVRGdKanJBc0RWQWR3QkgxM0g5VUFmWkVCNEN4Q3dBSFZnOEJNcndNcU1oQlFIY2hSZXZJREUwU0ZnQ3RZTXcvcUVBUm1MdWJ2QmxaMElJQVh0ejVUdWw2cUFib2lqd0t1bUhsdDVjZFFoeUF3eHZvdzhIaU5pQUZEZFNCVytVVkFGR05jbC9KUndCaUVnTk82QklFeFlzTEE0M1dpQWdGaThEc0VubG9zNHNHVXF3QmpFQUl1NlJRRXhvZ0xBN05mNitvMVFBZDlkUnh0cFVmOUtHQU1Rc0JsM1lKQXRJanFBRWNGTVZvdERBWFFYL09vSHdYTXBuSWRZVHAreGZETXI1RDlIc2NHVGNUWDFoNjlGdVNKQ213clBqYnJoSGt3ajNvVjRNRjFyRXJvV2hHSVBDSjRmazJxQTk0NmJUQ01vOTRjamdJZU9CSzRxV3NRR0NNbkRNeCszUzBzNHVpcTVVSSttZE5SQUNGZ2dzNUJZSXo2WVlCQU1GL2J4UUxsT1ZVQnhpQUVUTk05Q0l5Ukd3YW9EbmhxdldnSW8xK3U2L3o4U3Z0eFF4RDRrUlVHWnIvMkZxb0Q2SUJ4Zm8zVFVjQVloSURwQ0FLL3NzTUExUUVQYkRiNjZLTmozSTRDeGlBRUxFRVFXT2ZLZ0tVNm9JMTI4MEovYlhPckFveEJDRmlHSVBDbjJZUGphaGlnT3FDRjhPU0x2dnViV3hWZ0RQcHdLWUxBMzFRR01HRkFBMjFVQS8zb2VSUXd4dnhycGhyd2dpRHduc3BBNWt1SWN0RXV0WFFlNjQ1SEFXTVFBa0lRQkxZcERXaXFBN0U2YnhnZGRPdGZ4eXJBR0lTQU1BU0JmV3BoZ09yQWV0M3Z2NVBxZmUxNkZEQUdJU0FVUWVDekZXR0E2b0FlUWxCUFZmdmQrU2lBRUJDczQ2OFBYckhpMXdQdi9GcGcxSzhaUGw2cnV0VnQ2ZGlHQ3RjY3VVRlgrdlZPcWdDL3F2VHBVbFFFamxNYitIek1jQTVDZ0s3bzM2aDNydzV3RlBBbjV0NUJCSUZ6RkNjQVgwSjBUY1E5c1JETkVkMk9qdVBkOVNoZ0RFSkFPb0xBZVlvVGdlckFPUkVCZ0lWb3JvdzJkUm52cmxXQU1RZ0JFZ2dDMXlpR2dUR29EbnhDRmNBZjFZRmZ6a2NCWXhBQ1pCQUVybE1PQTFRSC91YThZT0pQVkFlOGp3TEdJQVJJSVFqY3N5b01PRlVISEhBVVVGUEhod25kcXdCakVBTGtFQVR1VTA3TTNiK0VpS09BSHJvY0YwUzk1c28xalJBZ2lDQXdoM0lZR0tObmRZQXFRQy9WandzNENuaVBPVGdCUVdBZTlRblVxVHBBRmFDdmF0VUJqZ0syTVE4bklRak10Zkp6dGxRSGpyMHVJUUJWcWdQdVZZQXhDQUVXQ0FKck9GUUhWb3V1RG5BVWdGZk9EeE5XcUFLc2VoNkFlVGdaUVdBZGh6QlFvVHBBRlFDZk9CMFhjQlN3alhtNENFRmdMZlV3TUlaM2RZQXFBSTV5T0M3Z0tHQWI4M0FoZ3NCNkRoL0ZjYXdPVUFYQUZhclZnUXBWQUVLQUtZSkFESmNFN2xBZDRDamdtQXIzc0lwU2RZQ2pnSDJNNHdBRWdUZ3VFMUc1T3NCUkFHYktmcGlRbzRCOXpNVWdCSUZZcTcrd3c3RTZjUFMvb3dxQVZWU1BDKzV5UFFvWWcva1lpaUNRd3lXaEszd0pFVlVBUktnMkRseldtRmZWK3NFQ1FTQ1BVMXJQcWc1UUJVQTA5ekhCVVFCTyt6ZjdBcHI3R3V1L25uVFc1SHI4bmRYWEc0VkZCMXNpeHZvS3JnRmdET1pqS2lvQytiSkw3MmU1VDFoS2p6akthYXdRQW5BWlFVQkQxRm44TEs0VDEvVzZrVXQ1M0RnOWdQektLV2lWUmhEUTRsUWRjSnZFVHRjS1BZcmpuU29BcGlBSTZGSDYyTjRSNmhOYWNRR0hMNFd4NUZ3RkdFT2pEZkdFSUtCSjRXTjdaNmh1dG9yWEJIK1o0OTI1Q2pBR2MxSVNRVUFiMVlGclZJTUphb2tlWis1VkFPYWtLSUtBUG9mdi8zK1dQZUZaYkJCdDlaaGJOYWNxZk1NaEppQUllSWo4L3YrWmdTQWFDdzZ5ckJwN0s2c0FFWmlUQnZoQ0lTK3J2NERvWWRZWEVVVjlNUXVMRGJLdEdPT3JxZ0FSbUpOR3FBajRpWnBnN3RVQklNcnN6WFZGQlREcUdHQU01cnNkZ29DbnlIUDRXUXZJNm84OEFSbFdoSURaSWdNQUljQVFRY0JiOUcrcHowQVlRQlhxSVlBcUFBNGhDUGh6clE2c1FCaEFGT1VRRUIwQUNBSG1DQUoxUkZjSDdpNDBMQ0J3cFJvQ0lnUEFHTXpmTWdnQ3RVUnZyck1Dd1V4VUJlQmt4dmpQQ0FDRWdFSUlBalZGVDlLN0N4RmhBQzZVeGxaMEFCaURBRkFTUWFDdWpOUitaMkVpREVDZHlwRkFWZ0FnQkJSRkVLZ3ZZL0plWGFnSUExQ2xFQUl5QXNBWUJJRHlDQUk5WktYNUt3c1hZUUJxc2tOQVpnQWdCRFRBVnd6M0V2V1Z2NjhlcjVlMXFNejZ5bVQwa3hrQ3NrSXNjNlVaS2dJOVpXN0lSeFkzNTI5WEE3WWNIZGRaRllBeENBRXRFUVQ2eWl6N0hWbm9DQVBJTm5POEhCblAyUUdBRU5BVVJ3UElPaTU0ZmMxM2k5Q0tYMXVzZkV3d3E2MCs5VXNIa2ZNaE02QjI3Vjg4SVFqZ0lUTVFQTC91NjhKRUdIZ3ZxcCsyWHNlOS9mWkVQQmVnVUoycTNJYzRnU0NBVnlxQllJemZhMWtSQnB3bzN2dTdhNnF3c2F3T0FRcDlXYUdmTUJGQkFGc1VOdC9uS3NIczYxR3RDbVMzK1IzdTFZTlZJVUNsVDEzNkFjRUlBdGlqc3BDdGV2M3NNSkRkcmxFY3FnY3JRb0JLLzZxMU5jUVFCSENFU2lCWUlTb01WR3k3Tzl5ckI1OG85SGVWdHNSaUJBR2NVVFVRekE0RDFkb25Va2Ixb0ZwL0VRQndDa0VBVjFRTUJGZkRRS1UyVUxXeWVsQ3Avd2dBdUlRZ2dEdXFCWUpQWWFES2ZWWnh0M3BRcFQ4SkFMaUZJSUFaS2dXQ1J4aW9jQzhkSGEwZVZPaGZBZ0NtSUFoZ3BpcUJ3UDM2OGJkS2ZVb0F3RlFFQWF6d3ZGQlZXb0NCVEFRQUxFRVF3R3BWcWdSQUZnSUFsaUlJSUFxQkFEaU96UjloQ0FLSVJpQ0lkM1JUb1UveUVRQVFqaUNBTER4SE1OL2RUV1RyMzlNL2E3SDVJeFZCQUFxb0Vwd1h1WG1vL295dU93SUFKQkFFb0lRcXdYdUtHd2JWZzJzVSt4TE5FUVNncW1zb2NOOG9xQjY4NTk2dktJd2dBQWVkamc2TzNLUDZwdEtobjQ1UTd5ZGdqRUVRZ0pldVZZSlhuZTlkSFpzLzdCQUU0SXBRQUJWcy9yQkdFRUFGaEFKRVkvTkhHUVFCVkZQeFYrYWdnYzBmSlJFRVVCM1ZBbHpGeG84Vy9zbStBQ0RRMTJCeHh6R01FN1JCUlFBZGNYeUFWMno4YUlzZ0FQQWxPSjJ3NFFNdkNBTEFlNFFEZjJ6NndBRUVBZUE0d29FMk5uN2dBb0lBY0EvaElCNGJQakFSUVFDWWoxL21tNE1OSHdoQUVBRGk3RzFzWFVNQ216MlFqQ0FBYURpNklib0VCalo0d01UL0I0emxqUHV0eC8yOUFBQUFBRWxGVGtTdVFtQ0MiLz4KPC9zdmc+Cg==',
        '4.5'
    ));

});