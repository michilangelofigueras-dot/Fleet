<?php
/**
 * FleetFlow - entry point.
 *
 *   php index.php            the console app (menu-driven, run in a terminal)
 *   /index.php               the web app (serves index.html)
 *   /index.php?resource=...  the JSON API (see classes/ApiController.php)
 *
 * Needs PHP 7.4+. Records live in data/records.json, created on first run.
 */

declare(strict_types=1);

$isConsole = PHP_SAPI === 'cli';

// On the web, warnings printed into the response would corrupt the JSON the front end parses.
ini_set('display_errors', $isConsole ? '1' : '0');
error_reporting(E_ALL);

/* Load classes, interfaces, traits and exceptions on demand from their folders. */
spl_autoload_register(function (string $name): void {
    foreach (['classes', 'interfaces', 'traits', 'exceptions'] as $folder) {
        $file = __DIR__ . '/' . $folder . '/' . $name . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

date_default_timezone_set(Config::TIMEZONE);

/* Wire the objects together. The web saves every change at once; the console waits for option 7. */
$data  = new FleetDataManager(!$isConsole);
$fleet = new FleetService(
    new VehicleRepository($data),
    new CustomerRepository($data),
    new RentalRepository($data),
    new PricingCalculator()
);

if ($isConsole) {
    (new ConsoleApp($fleet, $data))->run();
    exit(0);
}

/* A plain visit to the folder opens the app; any API call carries a query string. */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && empty($_GET) && is_file(__DIR__ . '/index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

(new ApiController($fleet, $data))->handle();
