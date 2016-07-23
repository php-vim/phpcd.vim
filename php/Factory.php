<?php

namespace PHPCD;

use Lvht\MsgpackRpc\JsonMessenger;
use Lvht\MsgpackRpc\StdIo;
use Lvht\MsgpackRpc\MsgpackMessenger;

/**
 * Simple factory to separate details of object creation
 */
class Factory
{
    /**
     * @param string $path the log file path
     * @return \Psr\Log\LoggerInterface
     */
    public static function createLogger($path, $is_null = false)
    {
        $logger = new \Monolog\Logger('PHPCD');
        if (!$is_null) {
            $logger->pushHandler(new \Monolog\Handler\StreamHandler($path, \Monolog\Logger::DEBUG));
        }

        return $logger;
    }

    /**
     * @return \PHPCD\PatternMatcher\PatternMatcher
     */
    public static function createPatternMatcher($match_type = 'head', $case_sensitivity = null)
    {
        $case_sensitivity = (bool)$case_sensitivity;

        if ($match_type === 'subsequence') {
            return new \PHPCD\PatternMatcher\SubsequencePatternMatcher($case_sensitivity);
        }

        return new \PHPCD\PatternMatcher\HeadPatternMatcher($case_sensitivity);
    }

    public static function createIoMessenger($type = 'msgpack')
    {
        $io = new StdIo();
        if ($type === 'msgpack') {
            return new MsgpackMessenger($io);
        } else {
            return new JsonMessenger($io);
        }
    }
}
