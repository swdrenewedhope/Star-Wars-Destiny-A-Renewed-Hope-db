<?php
use Symfony\Component\ClassLoader\ApcClassLoader;
use Symfony\Component\HttpFoundation\Request;

$root = realpath(__DIR__ . '/..');

if (!is_file($root . '/vendor/autoload.php')) {
    $envRoot = getenv('APP_ROOT');
    if ($envRoot) {
        $root = rtrim($envRoot, '/');
    }
}

$loader = require $root . '/vendor/autoload.php';

/*
use Symfony\Component\ClassLoader\ApcClassLoader;
$apcLoader = new ApcClassLoader(sha1(__FILE__), $loader);
$loader->unregister();
$apcLoader->register(true);
*/

require $root . '/app/AppKernel.php';

$kernel = new AppKernel('prod', false);
$kernel->loadClassCache();

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
