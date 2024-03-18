<?php

namespace CarbonWordPress;

use CarbonPHP\Abstracts\ColorCode;
use CarbonPHP\Abstracts\GitHub;
use CarbonPHP\Application;
use CarbonPHP\CarbonPHP;
use CarbonPHP\Abstracts\Background;
use CarbonPHP\Error\PublicAlert;
use CarbonPHP\Programs\Migrate;

class WordPressApplication extends Application
{

    public static ?string $composerExecutable = null;

    public static bool $updateComposerRouteEnabled = true;

    /** @noinspection NotOptimalIfConditionsInspection */
    public function startApplication(string $uri): bool
    {

        // these requests will be coming from external servers which will not be authenticated via wordpress
        if (GitHub::hooks('c6wordpress/github')
            || Migrate::enablePull([
                CarbonPHP::$app_root
            ])) {

            return true;

        }

        $isPrivilegedUser = CarbonWordPress::isPrivilegedUser();

        $requiresLoginNotice = static function (callable $closure) use ($isPrivilegedUser) {
            return $isPrivilegedUser ? $closure : static function (): never {
                print 'You are not logged in to a privileged user account! You must login or switch accounts to view this content.';
                exit(0);
            };
        };

        $catLogs = static function (string $program) {
            $abspath = ABSPATH;
            $cmd = "cd '$abspath' && tail -n 1000 ./logs/$program.txt";
            print ">> $cmd\n";
            print shell_exec($cmd);
            exit(0);
        };

        putenv('PATH=/bin:/usr/bin/:/usr/sbin/:/usr/local/bin:$PATH');

        if (self::regexMatch('#c6wordpress/logs/websocket#', $requiresLoginNotice(static fn () => $catLogs('WordPressWebSocket')))
            || self::regexMatch('#c6wordpress/logs/migrate#', $requiresLoginNotice(static fn () => $catLogs('migrate')))
            || self::regexMatch('#c6wordpress/migrate(?:/([^/]*))?#', static function ($subAction = null) use ($requiresLoginNotice) {

                switch ($subAction) {
                    case null:
                        $requiresLoginNotice(static function () {

                            [$cmd, $resp] = WordPressMigration::getPid();

                            /** @noinspection ForgottenDebugOutputInspection */
                            print_r([
                                'Command' => $cmd,
                                'output' => $resp
                            ]);

                        })();
                        exit(0);
                    case 'verify':
                        /** @noinspection PhpUndefinedFunctionInspection - in wordpress context */
                        print get_site_url() . '/';
                        exit(0);
                    default:
                        throw new PublicAlert('Unknown migration sub command (' . __FILE__ . ':' . __LINE__ . ')');
                }

            })
            || (self::$updateComposerRouteEnabled && self::regexMatch('#c6wordpress/logs/composer/update#', $requiresLoginNotice(static function () {

                    $abspath = ABSPATH;

                    if (null === self::$composerExecutable) {
                        throw new \Error('Composer executable not set');
                    }

                    $cmd = "cd '$abspath' && php " . self::$composerExecutable . " update 2>&1";

                    print 'Running: ' . $cmd;

                    if (0 !== $exitCode = Background::executeAndCheckStatus("cd '$abspath' && HOME=/ php " . self::$composerExecutable . " install 2>&1", false, $output)) {

                        print 'Update Failed with exit code: (' . $exitCode . ')';

                    } else {

                        print 'Update Succeeded with exit code: (' . $exitCode . ')';

                    }

                    if (is_array($output)) {
                        print json_encode($output, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                    } else {
                        print $output;
                    }

                    exit(0);
                })))
        ) {

            ColorCode::colorCode("CarbonPHP matched matched a route with the Wordpress Plugin Feature!");

        }


        return true;

    }

    public function defaultRoute(): void
    {
        // do nothing here :) - RM
    }
}