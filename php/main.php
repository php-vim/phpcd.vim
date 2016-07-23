<?php
ini_set('display_errors', 'stderr');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$root = $argv[1];
$handler_name = $argv[2];
$parameters = isset($argv[3]) ? json_decode($argv[3], true) : [];

/** load autoloader for PHPCD **/
require __DIR__ . '/../vendor/autoload.php';

/** load autoloader for the project **/
$composer_autoload_file = $root . '/vendor/autoload.php';
if (is_readable($composer_autoload_file)) {
    require $composer_autoload_file;
}

use PHPCD\Factory as F;
use PHPCD\ClassInfo\ComposerClassmapFileRepository;

$logger = F::createLogger(getenv('HOME').'/.phpcd.log');
$messenger_type = isset($parameters['messenger']) ? $parameters['messenger'] : null;
$messenger = F::createIoMessenger($messenger_type);
$matcher = F::createPatternMatcher();

$class_info_factory = new PHPCD\ClassInfo\ClassInfoFactory($matcher);
$phpfile_info_factory = new PHPCD\PHPFileInfo\PHPFileInfoFactory();

if ($handler_name == 'PHPCD') {
    $handler = new PHPCD\PHPCD($root, $logger,
        $class_info_factory, $phpfile_info_factory);
} else {
    $ccfr = new ComposerClassmapFileRepository($root, $matcher,
        $class_info_factory, $phpfile_info_factory, $logger);
    $handler = new PHPCD\PHPID($root, $logger, $ccfr);
}

$server = new Lvht\MsgpackRpc\ForkServer($messenger, $handler);

try {
    $server->loop();
} catch (\Throwable $e) {
    $logger->error($e->getMessage(), $e->getTrace());
} catch (\Exception $e) {
    $logger->error($e->getMessage(), $e->getTrace());
}
