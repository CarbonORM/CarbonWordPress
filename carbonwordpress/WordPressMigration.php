<?php

namespace CarbonWordPress;

use CarbonPHP\Error\PublicAlert;
use CarbonPHP\Programs\Migrate;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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

            $localServerUrl = htmlspecialchars($localServerUrl, ENT_QUOTES, 'UTF-8');

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


    /**
     * @throws \JsonException
     */
    public static function getPastMigrations() {
        // Path to the 'tmp/' directory. Adjust it to your needs.
        $directoryPath = ABSPATH . 'cache' . DIRECTORY_SEPARATOR . 'tmp';

        if (!is_dir($directoryPath)) {
            return '{}';
        }

        $tree = self::buildDirectoryTree($directoryPath);

        // Encode the directory tree to JSON, with pretty print for readability
        return json_encode($tree, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    }
    public static function buildDirectoryTree($basePath)
    {
        $root = [];
        $directoryIterator = new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $filePath => $fileObject) {
            // Get relative path from base path
            $relativePath = substr($filePath, strlen($basePath) + 1);

            // Initialize a reference to the root of the directory tree
            $currentLevel = &$root;

            // Split the relative path into components
            foreach (explode(DIRECTORY_SEPARATOR, $relativePath) as $part) {
                // If the directory doesn't exist, create it
                if (!isset($currentLevel[$part])) {
                    $currentLevel[$part] = $fileObject->isDir() ? [] : null;
                }
                // Move deeper into the directory structure
                $currentLevel = &$currentLevel[$part];
            }
        }

        return $root;
    }


}