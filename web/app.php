<?php
use Symfony\Component\HttpFoundation\Request; ini_set('memory_limit', '256M');
require __DIR__ . '/../app/autoload.php'; require __DIR__ . '/../app/AppKernel.php';
$kernel = new AppKernel(getenv('SYMFONY_ENV') ?: 'dev', getenv('SYMFONY_ENV') !== 'prod');
$request = Request::createFromGlobals(); $response = $kernel->handle($request);
$response->send(); $kernel->terminate($request, $response);