<?php

namespace CarbonWordPress;

use CarbonPHP\Abstracts\ColorCode;
use CarbonPHP\Error\ThrowableHandler;
use CarbonPHP\Interfaces\iColorCode;
use CarbonPHP\Programs\WebSocket;
use Error;
use Throwable;

class WordPressWebSocket extends WebSocket
{

    public static string $cookieName = 'WsNonce';

    public static function getPid(): array
    {
        return CarbonWordPress::startProcessInBackground();
    }

    public static function wpValidation()
    {

        $ary = array();

        // @link https://stackoverflow.com/questions/24590818/what-is-the-difference-between-ipproto-ip-and-ipproto-raw
        if (socket_create_pair(AF_UNIX, SOCK_STREAM, IPPROTO_IP, $ary) === false) {

            throw new Error("socket_create_pair() failed. Reason: " . socket_strerror(socket_last_error()));

        }

        $pid = pcntl_fork();

        if ($pid === -1) {

            throw new Error('Could not fork Process.');

        }

        if ($pid !== 0) {

            socket_close($ary[0]);

            // lets return this so we can get benefits from this
            return static function () use (&$ary): int {

                /** @noinspection PhpRedundantOptionalArgumentInspection */
                $userId = socket_read($ary[1], 1024, PHP_BINARY_READ);

                ColorCode::colorCode("socket read ($userId)", iColorCode::BACKGROUND_CYAN);

                if (empty($userId)) {

                    $userId = 0;

                }

                socket_close($ary[1]);

                return $userId;

            };

        }

        /*child*/
        socket_close($ary[1]);

        self::importWordpress();

        $scheme = 'logged_in';

        if (!function_exists('wp_validate_auth_cookie')) {

            throw new Error('wp_validate_auth_cookie() does not exist. WordPress was not loaded correctly?' . PHP_EOL);

        }

        $id = wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], $scheme);

        $id = (string)$id;

        if (socket_write($ary[0], $id, strlen($id)) === false) {

            ColorCode::colorCode("socket_write() failed. <$id> Reason: " . socket_strerror(socket_last_error($ary[0])), iColorCode::BACKGROUND_RED);

        }

        socket_close($ary[0]);

        exit(0);

    }

    private static function getWordpressLoggedInCookieName($cookies): ?string
    {

        static $fullName = '';

        if ($fullName !== '') {

            return $fullName;

        }

        foreach ($cookies as $key => $value) {

            if (str_starts_with($key, 'wordpress_logged_in_')) {

                return $fullName = $key;

            }

        }

        return null;

    }

    public static function logWordpressUserIn(string $cookies): ?array
    {

        try {

            parse_str(strtr($cookies, array('&' => '%26', '+' => '%2B', ';' => '&')), $_SERVER['HTTP_COOKIE']);

            $_COOKIE = $_SERVER['HTTP_COOKIE'];

            if (false === defined('LOGGED_IN_COOKIE')) {

                $getFullName = self::getWordpressLoggedInCookieName($_COOKIE);

                if (null === $getFullName) {

                    return null;

                }

                define('LOGGED_IN_COOKIE', $getFullName);

            }

            if (false === array_key_exists(LOGGED_IN_COOKIE, $_COOKIE)) {

                return null;

            }

            $cookie_elements = explode('|', $_COOKIE[LOGGED_IN_COOKIE]);

            if (count($cookie_elements) !== 4) {

                return null;

            }

            // @link https://stackoverflow.com/questions/52263267/using-wp-session-tokens-class-in-a-procedural-wordpress-plugin
            // @link https://www.securitysift.com/understanding-wordpress-auth-cookies/
            ColorCode::colorCode('The user is trying to auth with (' . print_r($_COOKIE, true) . ')');

            $wpValidate = self::wpValidation();

            ColorCode::colorCode("completing login auth chain socket connection");

            $userId = $wpValidate();

            // REMOVE THE JUST SET COOKIE AS IT IS USER SPECIFIC AND ONLY NEEDED FOR WP LOGIN AUTH
            $_COOKIE = $_SERVER['HTTP_COOKIE'] = [];

            return $userId;

        } catch (Throwable $e) {

            ColorCode::colorCode($e->getMessage(), iColorCode::BACKGROUND_RED);

        } /** @noinspection PhpStatementHasEmptyBodyInspection */ finally {
        }

        return null;

    }

    public static function importWordpress(): void
    {

        ThrowableHandler::stop(true);   // we have low standards for our plugins

        $time_start = microtime(true);

        ColorCode::colorCode("importing wordpress", iColorCode::BACKGROUND_WHITE);

        // Include wordpress
        require_once ABSPATH . 'wp-load.php';

        $time_end = microtime(true);

        $time = $time_end - $time_start;

        ColorCode::colorCode("require_once 'wp-load.php'; # executed in ($time) seconds\n", iColorCode::RED);

        ThrowableHandler::start();  // stop low standards

    }

}