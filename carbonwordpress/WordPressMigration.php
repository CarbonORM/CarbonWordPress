<?php

namespace CarbonWordPress;

class WordPressMigration
{

    // this is the c6 program name that will be used to start the process
    public const MIGRATE = 'migrate';

    public static function getPid(): array
    {

        $localServerUrl = $_POST['localURL'] ?? null;

        $remoteServerUrl = $_POST['remoteURL'];

        $license = $_POST['remoteAPIKey'] ?? null;

        if (null === $localServerUrl) {

            $localServerUrl = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

            $localServerUrl = htmlspecialchars( $localServerUrl, ENT_QUOTES, 'UTF-8' );

        }

        $args = [
            '--local-url',
            $localServerUrl,
            '--remote-url',
            $remoteServerUrl
        ];

        if (null !== $license) {
            $args[] = '--license';
            $args[] = $license;
        }

        return CarbonWordPress::startProcessInBackground(self::MIGRATE, $args);


    }




}