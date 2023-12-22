<?php
/*
 * Plugin Name: YOUR PLUGIN NAME
 */

namespace CarbonWordPress;

use CarbonPHP\Abstracts\ColorCode;
use CarbonPHP\CarbonPHP;
use CarbonPHP\Interfaces\iColorCode;


class CarbonWordPress
{

    public static bool $verbose = true;

    public static function make(): void
    {

        $_ENV['CARBONORM_VERBOSE'] ??= true;

        if ($_ENV['CARBONORM_VERBOSE'] === 'false') {

            self::$verbose = false;

        }

        CarbonPHP::$wordpressPluginEnabled = true;

        if (self::$verbose) {

            ColorCode::colorCode("Starting Full Wordpress CarbonPHP Configuration!",
                iColorCode::BACKGROUND_CYAN);

        }

        (new CarbonPHP(Configuration::class, ABSPATH))(WordpressApplication::class);

        if (false === CarbonPHP::$setupComplete) {

            if (self::$verbose) {

                ColorCode::colorCode("CarbonWordpress detected CarbonPHP had an unexpected finish!", iColorCode::BACKGROUND_RED);

            }

        }

    }

}