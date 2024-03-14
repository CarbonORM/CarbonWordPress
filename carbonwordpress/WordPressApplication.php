<?php

namespace CarbonWordPress;

use CarbonPHP\Abstracts\ColorCode;
use CarbonPHP\Application;
use CarbonPHP\CarbonPHP;
use CarbonPHP\Abstracts\Background;
use CarbonPHP\Programs\Deployment;
use CarbonPHP\Programs\Migrate;

class WordPressApplication extends Application
{

    public const uriPrefix = 'c6wordpress/';

    public static ?string $composerExecutable = null;

    public static bool $updateComposerRouteEnabled = true;

    public function startApplication(string $uri): bool
    {

        // these requests will be coming from external servers which will not be authenticated via wordpress
        if (Deployment::github('c6wordpress/github')
            || Migrate::enablePull([
                CarbonPHP::$app_root
            ])) {

            return true;

        }

        $isPrivilegedUser = CarbonWordPress::isPrivilegedUser();

        $requiresLoginNotice = static function (callable $closure) use ($isPrivilegedUser) {
            return $isPrivilegedUser ? $closure : static function () {
                print 'You are not logged in to a privileged user account! You must login or switch accounts to view this content.';
            };
        };

        putenv('PATH=/bin:/usr/bin/:/usr/sbin/:/usr/local/bin:$PATH');

        if (self::regexMatch('#c6wordpress/logs/websocket#', $requiresLoginNotice(static function () {
                $abspath = ABSPATH;
                //print str_replace("\n", '<br/>', shell_exec("cd '$abspath' && tail -n 1000 ./logs/websocket.txt"));
                $cmd = "cd '$abspath' && tail -n 1000 ./logs/websocket.txt";
                print ">> $cmd\n";
                print shell_exec($cmd);
                exit(0);
            }))
            || self::regexMatch('#c6wordpress/logs/migrate#', $requiresLoginNotice(static function () {
                $abspath = ABSPATH;
                $cmd = "cd '$abspath' && tail -n 1000 ./logs/migrate.txt";
                print ">>> $cmd";
                print shell_exec($cmd);
                exit(0);
            }))
            || self::regexMatch('#c6wordpress/migrate#', $requiresLoginNotice(static function () {

                [$cmd, $resp] = WordPressMigration::getPid();

                /** @noinspection ForgottenDebugOutputInspection */
                print_r([
                    'Command' => $cmd,
                    'output' => $resp
                ]);

                exit(0);
            }))
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