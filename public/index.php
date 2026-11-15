<?php
/**
 * Front controller: every request enters the app here.
 * Apache (.htaccess) or `php agus serve` sends all URLs to this file.
 */

declare(strict_types=1);

/** @var Agus\App $app */
$app = require dirname(__DIR__) . '/core/bootstrap.php';

$app->run();
