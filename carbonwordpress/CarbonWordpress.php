<?php
/*
 * Plugin Name: YOUR PLUGIN NAME
 */
namespace CarbonWordpress;

use CarbonPHP\Abstracts\ColorCode;
use CarbonPHP\CarbonPHP;
use CarbonPHP\Documentation;
use CarbonPHP\Interfaces\iColorCode;


class CarbonWordpress
{

    public static bool $verbose = true;

    public static function addCarbonPHPWordpressMenuItem(bool $advanced): void
    {
        $notice = $advanced ? "<Advanced>" : "<Basic>";

        add_action('admin_menu', static fn() => add_menu_page(
            "CarbonPHP $notice",
            "CarbonPHP $notice",
            'edit_posts',
            'CarbonPHP',
            static function () {

                print Documentation::inlineReact();

            },
            'dashicons-editor-customchar',
            '4.5'
        ));
    }

    public static function make() : void
    {

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

            return ;    // an error occurred

        }

        // todo - licensing!
        addCarbonPHPWordpressMenuItem(true);

        if (self::$verbose) {

            ColorCode::colorCode("FINISHED Full Wordpress CarbonPHP Configuration!");

        }

    }

}