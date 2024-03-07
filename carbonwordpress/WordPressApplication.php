<?php

namespace CarbonWordPress;

use CarbonPHP\Abstracts\ColorCode;
use CarbonPHP\Application;
use CarbonPHP\CarbonPHP;
use CarbonPHP\Programs\Deployment;
use CarbonPHP\Programs\Migrate;

class WordPressApplication extends Application
{

    public function startApplication(string $uri): bool
    {
        ;

        if (self::regexMatch('#logs/websocket#', static function () {
                $abspath = ABSPATH;
                //print str_replace("\n", '<br/>', shell_exec("cd '$abspath' && tail -n 1000 ./logs/websocket.txt"));
                print shell_exec("cd '$abspath' && tail -n 100 ./logs/websocket.txt");
                exit(0);
            })
            || self::regexMatch('#logs/migrate#', static function () {
                $abspath = ABSPATH;
                //print str_replace("\n", '<br/>', shell_exec("cd '$abspath' && tail -n 1000 ./logs/websocket.txt"));
                print shell_exec("cd '$abspath' && tail -n 100 ./logs/migrate.txt");
                exit(0);
            })
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