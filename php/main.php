<?php
error_reporting(0);
$root = $argv[1];
$daemon_name = $argv[2];
$input_options = json_decode($argv[3], true) ?: [];

/** @todo: update documentation about config variables **/

$default_options = [
    'logger' => [
        'implementation'    => '\\PHPCD\\Logger',
        'parameters'        => []
    ],
    'completion' => [
        'match_type' => 'head',
        'case_sensitivity' => 0
    ]
];

$options = $default_options;

foreach ($default_options as $option => $default_values) {
    if (isset($input_options[$option])) {
        if (is_array($input_options[$option]) && is_array($default_options[$option])) {
            $options[$option] = $input_options[$option] + $default_options[$option];
        }

        if (is_string($input_options[$option]) && is_string($default_options[$option])) {
            $options[$option] = $input_options[$option];
        }
    }
}

/** load autoloader for PHPCD **/
require __DIR__ . '/../vendor/autoload.php';
/** load autoloader for the project **/
require $root . '/vendor/autoload.php';

$factory = new \PHPCD\Factory;

/** Instantiate daemon's logger **/
$logger = $factory->createLogger(
    $options['logger']['implementation'],
    $options['logger']['parameters']
);

try {
    $unpacker = $factory->createMessageUnpacker();

    $pattern_matcher = $factory->createPatternMatcher(
        $options['completion']['match_type'],
        $options['completion']['case_sensitivity']
    );

    $daemon = $factory->createDaemon($daemon_name, $root, $unpacker, $pattern_matcher, $logger);

    $daemon->loop();
} catch (\Throwable $e) {
    $logger->error($e->getMessage(), $e->getTrace());
} catch (\Exception $e) {
    $logger->error($e->getMessage(), $e->getTrace());
}
