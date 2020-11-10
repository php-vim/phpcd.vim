<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$root   = $argv[1];
$messenger = $argv[2];
$autoload_file = $argv[3];
$disable_modifier = $argv[4];

/** load autoloader for PHPCD **/
require __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Lvht\MsgpackRpc\ForkServer;
use Lvht\MsgpackRpc\MsgpackMessenger;
use Lvht\MsgpackRpc\JsonMessenger;
use Lvht\MsgpackRpc\StdIo;

$log_path = getenv('HOME') . '/.phpcd.log';
ini_set('display_errors', 'Off');
$logger = new Logger('PHPCD');
$logger->pushHandler(new StreamHandler($log_path, Logger::DEBUG));
if ($messenger == 'json') {
    $messenger = new JsonMessenger(new StdIo());
} else {
    $messenger = new MsgpackMessenger(new StdIo());
}

$composer_file = $root."/composer.json";

if (is_readable($composer_file)) {
    $composer = json_decode(file_get_contents($composer_file), true);

    if (isset($composer["config"]["vendor-dir"])) {
        $home = getenv("HOME");

        $vendor_dir = $composer["config"]["vendor-dir"];
        if ($vendor_dir[0] == '~') {
            $vendor_dir = str_replace("~", $home, $vendor_dir);
        } elseif (substr($vendor_dir, 0, 5) == '$HOME') {
            $vendor_dir = str_replace("$HOME", $home, $vendor_dir);
        } elseif ($vendor_dir[0] != '/') {
            $vendor_dir = $root.'/'.$vendor_dir;
        }

        $autoload_file = $vendor_dir."/autoload.php";
    }
}

/** load autoloader for the project **/
if (is_readable($autoload_file)) {
    require $autoload_file;
}

$server = new ForkServer($messenger, new PHPCD\PHPCD($root, $logger, $disable_modifier));
$server->addHandler(new PHPCD\PHPID($root, $logger));

$server->loop();
