<?php

namespace CarbonWordPress;

use CarbonPHP\Abstracts\ColorCode;
use CarbonPHP\Application;
use CarbonPHP\CarbonPHP;
use CarbonPHP\Programs\Deployment;
use CarbonPHP\Programs\Migrate;

class WordpressApplication extends Application {

    public function startApplication(string $uri): bool
    {

        if (Deployment::github()
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