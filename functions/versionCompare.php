<?php

$rootPluginDir = dirname(__DIR__, 1);

$composerJsonPath = $rootPluginDir . '/composer.json';

$composerLockPath = $rootPluginDir . '/composer.lock';

if (false === file_exists($composerLockPath)
    || false === file_exists($composerJsonPath)) {

    $adminNotice = "The required CarbonPHP plugin file ($composerLockPath) and/or ($composerJsonPath) was not found. Please reinstall this plugin. CarbonPHP was not loaded.";

    throwAlert($adminNotice);

    return false;

}

try {

    $composer = json_decode(file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);

    $composerLock = json_decode(file_get_contents($composerLockPath), true, 512, JSON_THROW_ON_ERROR);

} catch (Throwable $e) {

    throwAlert("Failed to load a required plugin file. Please reinstall this plugin. CarbonPHP was not loaded.");

    return false;

}

$packages = $composerLock['packages'] ?? [];

$carbonPHPInfoFromLockFile = array_filter($packages, static fn(array $package) => 'carbonorm/carbonphp' === $package['name']);

if (empty($carbonPHPInfoFromLockFile)) {

    throwAlert("The required CarbonPHP plugin was not found in the lock file. This is unexpected and possibly means a corrupted installation. Please reinstall this plugin. CarbonPHP was not loaded!");

    return false;

}

$requiredPHPVersion = $carbonPHPInfoFromLockFile[0]['require']['php'] ?? false;

$requiredPHPVersion = preg_split('/([<>=^]+)/', $requiredPHPVersion, -1, PREG_SPLIT_DELIM_CAPTURE);

[,, $requiredPHPVersion] = $requiredPHPVersion;

// todo - make this a better comparison
if (false === version_compare(PHP_VERSION, $requiredPHPVersion, '>=')) {

    throwAlert("CarbonPHP requires PHP version (>=$requiredPHPVersion). Please update your version of PHP (" . PHP_VERSION . ')');

    return false;

}

$carbonPHPVersionFromLock = $carbonPHPInfoFromLockFile[0]['version'] ?? false;


function findCarbonPHP($require)
{
    foreach ($require as $name => $version) {
        if ($name === 'carbonorm/carbonphp') {
            return $version;
        }
    }
    return false;
}

$requiredCarbonPHPVersion = findCarbonPHP($composer['require']);

if (false === $requiredCarbonPHPVersion) {

    throwAlert("Failed to parse the required PHP version. CarbonPHP was not loaded!");

    return false;

}

// split version into operator and version number
$carbonWordPressVersion = $composer['version'] ?? false;

if (false === version_compare($carbonPHPVersionFromLock, $requiredCarbonPHPVersion, '>=')) {

    throwAlert("This version of CarbonWordPress ($carbonWordPressVersion) uses CarbonPHP ($carbonPHPVersionFromLock) which requires "
        . "PHP version (>=$requiredCarbonPHPVersion) or greater. Your server is running PHP (" . PHP_VERSION
        . '). Please update your version of PHP or you may consider downgrading the CarbonWordPress plugin.');

    return false;

}

return [$carbonPHPVersionFromLock, $carbonWordPressVersion];