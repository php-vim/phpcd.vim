<?php

namespace PHPCD\Log;

use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger
{
    private $log_file;

    private $log_path;

    public function __construct($log_path = null)
    {
        $this->setLogPath($log_path);

        // @todo handle bugs with opening log file
        $this->log_file = fopen($this->log_path, 'a');
    }

    private function setLogPath($log_path = null)
    {
        if (!$log_path) {
            $log_path = getenv('HOME') . '/.phpcd.log';
        }

        $this->log_path = $log_path;

        return $this;
    }

    public function __destruct()
    {
        fclose($this->log_file);
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
        if (is_string($level)) {
            $message = strtoupper($level) . ': ' . $message;
        }

        if ($context !== []) {
            $message .= PHP_EOL . json_encode($context, JSON_PRETTY_PRINT);
        }
        $message .= PHP_EOL;
        fwrite($this->log_file, $message);
    }
}
