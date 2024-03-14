<?php

namespace CarbonWordPress;

use CarbonPHP\Error\PublicAlert;
use CarbonPHP\Programs\Migrate;

class WordPressMigration extends Migrate
{

    // this is the c6 program name that will be used to start the process
    public const MIGRATE = 'migrate';

    public static function getPid(): array
    {

        $localServerUrl = $_POST['localURL'] ?? null;

        $remoteServerUrl = $_POST['remoteURL'] ?? null;

        if (empty($remoteServerUrl)) {

            // todo - should I handle this differently
            throw new PublicAlert('No remote URL was provided to the migration command. (' . __FILE__ . ':' . __LINE__ . ')');

        }

        $license = $_POST['remoteAPIKey'] ?? null;

        if (null === $localServerUrl) {

            $localServerUrl = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://{$_SERVER['HTTP_HOST']}/";

            $localServerUrl = htmlspecialchars( $localServerUrl, ENT_QUOTES, 'UTF-8' );

        }

        $args = [
            '--local-url',
            $localServerUrl,
            '--remote-url',
            $remoteServerUrl
        ];

        if (!empty($license)) {
            $args[] = '--license';
            $args[] = $license;
        }

        return CarbonWordPress::startProcessInBackground(self::MIGRATE, $args);


    }




}