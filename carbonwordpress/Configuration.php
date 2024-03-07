<?php

namespace CarbonWordPress;

use CarbonPHP\CarbonPHP;
use CarbonPHP\Documentation;
use CarbonPHP\Interfaces\iConfig;
use CarbonPHP\Tables\Carbons;

class Configuration implements iConfig {

    public static function configuration(): array
    {

        if (!defined('WEBSOCKET_PORT')) {

            define('WEBSOCKET_PORT', 8888);

        }

        return [
            CarbonPHP::SOCKET => [
                CarbonPHP::PORT => WEBSOCKET_PORT,    // the ladder would case when boot-strapping server setup on aws invocation stating at dig.php
            ],
            CarbonPHP::VIEW => [
                // TODO - THIS IS USED AS A URL AND DIRECTORY PATH. THIS IS BAD. WE NEED DS
                CarbonPHP::VIEW => DS,  // This is where the MVC() function will map the HTML.PHP and HTML.HBS . See Carbonphp.com/mvc
            ],
            // ERRORS on point
            CarbonPHP::ERROR => [
                CarbonPHP::LOCATION => CarbonPHP::$app_root . 'logs' . DS,
                CarbonPHP::LEVEL => E_ALL | E_USER_DEPRECATED | E_DEPRECATED | E_RECOVERABLE_ERROR | E_STRICT
                    | E_USER_NOTICE | E_USER_WARNING | E_USER_ERROR | E_COMPILE_WARNING | E_COMPILE_ERROR
                    | E_CORE_WARNING | E_CORE_ERROR | E_NOTICE | E_PARSE | E_WARNING | E_ERROR,  // php ini level
                CarbonPHP::STORE => true,      // Database if specified and / or File 'LOCATION' in your system
                CarbonPHP::SHOW => true,       // Show errors on browser
                CarbonPHP::FULL => true        // Generate custom stacktrace will high detail - DO NOT set to TRUE in PRODUCTION
            ],
            CarbonPHP::SESSION => [
                CarbonPHP::REMOTE => false,  // Store the session in the SQL database
                CarbonPHP::CALLBACK => static fn() => true,
            ],
            CarbonPHP::DATABASE => [
                CarbonPHP::DB_HOST => DB_HOST,
                CarbonPHP::DB_PORT => '', //3306
                CarbonPHP::DB_NAME => DB_NAME,
                CarbonPHP::DB_USER => DB_USER,
                CarbonPHP::DB_PASS => DB_PASSWORD,
            ],
            CarbonPHP::REST => [
                // This section has a recursion property, as the generated data is input for its program
                CarbonPHP::NAMESPACE => Carbons::CLASS_NAMESPACE,
                CarbonPHP::TABLE_PREFIX => Carbons::TABLE_PREFIX
            ],
            CarbonPHP::SITE => [
                CarbonPHP::URL => '', // todo - this should be changed back :: CarbonPHP::$app_local ? '127.0.0.1:8080' : basename(CarbonPHP::$app_root),    /* Evaluated and if not the accurate Redirect. Local php server okay. Remove for any domain */
                CarbonPHP::CACHE_CONTROL => [
                    'ico|pdf|flv' => 'Cache-Control: max-age=29030400, public',
                    'jpg|jpeg|png|gif|swf|xml|txt|css|woff2|tff|ttf|svg' => 'Cache-Control: max-age=604800, public',
                    'html|htm|hbs|js|json|map' => 'Cache-Control: max-age=0, private, public',
                ],
                CarbonPHP::CONFIG => __FILE__,
                CarbonPHP::IP_TEST => false,
                CarbonPHP::HTTP => true,
                CarbonPHP::PROGRAMS => [
                    WordPressWebSocket::class
                ]
            ],
        ];

    }


}