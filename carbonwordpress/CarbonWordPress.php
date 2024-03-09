<?php
/*
 * Plugin Name: YOUR PLUGIN NAME
 */

namespace CarbonWordPress;

use CarbonPHP\Abstracts\ColorCode;
use CarbonPHP\CarbonPHP;
use CarbonPHP\Error\ThrowableHandler;
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

        ThrowableHandler::$defaultLocation = ABSPATH . '/wp-content/debug.txt';

        (new CarbonPHP(Configuration::class, ABSPATH))(WordPressApplication::class);

        if (false === defined('WP_DEBUG_LOG')) {

            define('WP_DEBUG_LOG', ThrowableHandler::$defaultLocation);

        }

        ini_set('error_log', WP_DEBUG_LOG);

        // cli adjustments
        $_SERVER['HTTP_HOST'] ??= '127.0.0.1';

        if (empty($_SERVER['REQUEST_URI'])) {

            $_SERVER['REQUEST_URI'] = '/';

        }

        if (false === CarbonPHP::$setupComplete) {

            if (self::$verbose) {

                ColorCode::colorCode("CarbonWordpress detected CarbonPHP had an unexpected finish!", iColorCode::BACKGROUND_RED);

            }

        }

    }

}

