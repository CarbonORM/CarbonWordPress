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

    public static ?string $composerExecutable = null;

    public static bool $updateComposerRouteEnabled = true;

    public function startApplication(string $uri): bool
    {
        putenv('PATH=/bin:/usr/bin/:/usr/sbin/:/usr/local/bin:$PATH');

        if (self::regexMatch('#logs/websocket#', static function () {
                $abspath = ABSPATH;
                //print str_replace("\n", '<br/>', shell_exec("cd '$abspath' && tail -n 1000 ./logs/websocket.txt"));
                $cmd ="cd '$abspath' && tail -n 1000 ./logs/websocket.txt";
                print ">> $cmd\n";
                print shell_exec($cmd);
                exit(0);
            })
            || self::regexMatch('#logs/migrate#', static function () {
                $abspath = ABSPATH;
                //print str_replace("\n", '<br/>', shell_exec("cd '$abspath' && tail -n 1000 ./logs/websocket.txt"));
                print shell_exec("cd '$abspath' && tail -n 100 ./logs/migrate.txt");
                exit(0);
            })
            || (self::$updateComposerRouteEnabled && self::regexMatch('#logs/composer/update/?#', static function () {

                    $abspath = ABSPATH;

                    if (null === self::$composerExecutable) {
                        throw new \Error('Composer executable not set');
                    }

                    $cmd = "cd '$abspath' && php " . self::$composerExecutable . " update 2>&1";

                    print 'Running: ' . $cmd;

                    //print str_replace("\n", '<br/>', shell_exec("cd '$abspath' && tail -n 1000 ./logs/websocket.txt"));
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
                }))
            || Deployment::github()
            || Migrate::enablePull([CarbonPHP::$app_root])) {

            ColorCode::colorCode("CarbonPHP matched matched a route with the Wordpress Plugin Feature!");


        }

        return true;

    }

    public function defaultRoute(): void
    {
        // TODO: Implement defaultRoute() method.
    }
}