<?php

namespace PHPCD\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class DateTimeLogger extends AbstractLogger
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __destruct()
    {
        $this->logger = null;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function log($level, $message, array $context = [])
    {
        $datetimestring = date('Y-m-d H:i:s');

        $message = sprintf('%s %s', $datetimestring, $message);

        return $this->logger->log($level, $message, $context);
    }
}
