<?php
/**
 * PHPUnit bootstrap. Loads Composer's autoloader when present; the test
 * suite itself only requires two plain PHP files directly, so Composer
 * is not required to run it (e.g. via a standalone phpunit.phar).
 */

$autoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
}
