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


    public static function getCarbonWordPressLicense(): string
    {
        $licensePath = ABSPATH . 'CarbonWordPressLicense.php';
        if (!file_exists($licensePath)) {
            return '';
        }
        return include $licensePath;
    }

    public static function getCurrentUser(bool $useCache = true) {
        static $user;
        if ($useCache && $user) {
            return $user;
        }
        $user = wp_get_current_user();
        return $user;
    }

    public static function isPrivilegedUser(): bool
    {
        // todo - cache for non socket requests?
        $user = self::getCurrentUser();
        if (array_intersect(['editor', 'administrator', 'author'], $user->roles)) {
            return true;
        }
        return false;
    }

    public static function startProcessInBackground(string $program = 'WordPressWebSocket', string|array $args = '--autoAssignAnyOpenPort'): array
    {
        $absPath = ABSPATH;

        $path = $absPath . "index.php";

        if (is_array($args)) {

            $args = implode(' ', $args);

        }

        if (false !== $pid = self::isProgramRunning($program)) {

            return ['', "Found existing ($program) process PID ($pid)"];

        }


        // @link https://stackoverflow.com/questions/29112446/nohup-doesnt-work-with-os-x-yosmite-get-error-cant-detach-from-console-no-s
        // you cant trust nohup on mac, < /dev/null: Redirects the standard input from /dev/null (i.e., the script won't wait for any input).
        $cmd = /** @lang Shell Script */
            <<<BASH
            
            set -e
            
            cd "$absPath";
            
            if [ ! -d ./logs ]; then
                mkdir ./logs
            fi
            
            php '$path' $program $args < /dev/null > ./logs/$program.txt 2>&1 & 
            
            echo \$! > ./logs/$program.pid
            
            cat ./logs/$program.pid
            
            BASH;

        return [$cmd, "Started new ($program) process under PID (". trim(shell_exec($cmd) ?? '') . ')'];

    }

    public static function isProgramRunning(string $program = 'WordPressWebSocket'): string|bool
    {
        $absPath = ABSPATH;

        $cmd = /** @lang Shell Script */
            <<<BASH
            
            echo "$@"

            # @link https://www.gnu.org/software/bash/manual/html_node/The-Shopt-Builtin.html
            # if a command fails and piped to cat, for example, the full command will exit failure,.. cat will not run.?
            # @link https://distroid.net/set-pipefail-bash-scripts/?utm_source=rss&utm_medium=rss&utm_campaign=set-pipefail-bash-scripts
            # @link https://transang.me/best-practice-to-make-a-shell-script/
            # @link https://stackoverflow.com/questions/2853803/how-to-echo-shell-commands-as-they-are-executed
            set -e
            
            cd "$absPath";
            
            pid=$( cat ./logs/$program.pid 2>/dev/null || echo '' )
            
            if [ -z "\$pid" ] || ! ps -p "\$pid" > /dev/null ; then
                echo '';
            else
                echo "\$pid"
            fi
            
            BASH;

        $output = trim(shell_exec($cmd) ?? '');

        return $output === '' ? false : $output;
    }


}

