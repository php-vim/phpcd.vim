<?php
error_reporting(0);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$handler_name = $argv[2];
$root = $argv[1];
$parameters = (empty($argv[3]) ? '' : $argv[3]);
$parameters = json_decode($parameters, true) ?: [];
$parameters['root'] = $root;

/** load autoloader for PHPCD **/
require __DIR__ . '/../vendor/autoload.php';

/** load autoloader for the project **/
$composer_autoload_file = $root . '/vendor/autoload.php';
$autoload_file = empty($parameters['autoload_file']) ? $composer_autoload_file : $parameters['autoload_file'];
if (is_readable($autoload_file)) {
    $class_loader = require $autoload_file;
    // @TODO non-composer class loader
}

$parameters['class_loader'] = $class_loader;

$factory = new \PHPCD\Factory;

$configdir = __DIR__.'/../config/';
$handler_name = strtolower($handler_name);
$configfile =  $handler_name.'.yml';
$dIContainer = $factory->createDIContainer($configfile, $configdir, $parameters);

$logger = $dIContainer->get('default_logger');

try {
    if ($handler_name !== 'phpcd' && $handler_name !== 'phpid') {
        throw new \InvalidArgumentException('The daemon name should be PHPCD or PHPID');
    }

    $server = $dIContainer->get('server.'. $handler_name);
    $server->loop();
} catch (\Throwable $e) {
    $logger->error($e->getMessage(), $e->getTrace());
} catch (\Exception $e) {
    $logger->error($e->getMessage(), $e->getTrace());
}
