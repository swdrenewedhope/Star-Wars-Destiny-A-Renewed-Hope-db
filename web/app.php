<?php
use Symfony\Component\HttpFoundation\Request;
$root = realpath(__DIR__ . '/..');
$loader = require $root . '/vendor/autoload.php';
require $root . '/app/AppKernel.php';
if (getenv('SYMFONY_ENV') == 'dev') { $kernel = new AppKernel('dev', true); ini_set('memory_limit', '256M'); }
else { $kernel = new AppKernel('prod', false); }
$kernel->loadClassCache();
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
