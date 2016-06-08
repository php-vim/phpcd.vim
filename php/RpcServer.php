<?php

namespace PHPCD;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use PHPCD\PatternMatcher\PatternMatcher;

class RpcServer
{
    use LoggerAwareTrait;

    const TYPE_REQUEST = 0;
    const TYPE_RESPONSE = 1;
    const TYPE_NOTIFICATION = 2;

    const DIRECTION_IN = 0;
    const DIRECTION_OUT = 1;

    private $msg_id = 0;

    private $current_msg_id;

    private $request_callback = [];

    /**
     * Composer root dir(containing vendor)
     */
    protected $root;

   /**
    * @var \MessagePackUnpacker
    */
    private $unpacker;

    /**
     * @var PatternMatcher
     */
    protected $pattern_matcher;

    public function __construct(
        $root,
        \MessagePackUnpacker $unpacker,
        PatternMatcher $pattern_matcher,
        LoggerInterface $logger
    ) {
        $this->setRoot($root);
        $this->unpacker = $unpacker;
        $this->pattern_matcher = $pattern_matcher;
        $this->setLogger($logger);

        register_shutdown_function([$this, 'shutdown']);
    }

    /**
     * Set the composer root dir
     *
     * @param string $root the path
     * @return static
     */
    protected function setRoot($root)
    {
        // @TODO do we need to validate this input variable?
        $this->root = $root;
        return $this;
    }

    public function loop()
    {
        $stdin = fopen('php://stdin', 'r');
        while (true) {
            $buffer = fread($stdin, 1024);
            $this->unpacker->feed($buffer);

            if ($this->unpacker->execute()) {
                $message = $this->unpacker->data();
                $this->unpacker->reset();

                $pid = pcntl_fork();
                if ($pid == -1) {
                    die('failed to fork');
                } elseif ($pid > 0) {
                    pcntl_waitpid($pid, $status);
                } else {
                    $this->onMessage($message);
                    exit;
                }
            }
        }
    }

    public function shutdown()
    {
        if (!$this->current_msg_id) {
            return;
        }

        $error = error_get_last();
        $error = sprintf('"%s at %s:%d"',
            $error['message'], $error['file'], $error['line']);

        $response = [self::TYPE_RESPONSE, $this->current_msg_id, $error, null];
        $this->write($response);
    }

    private function onMessage($message)
    {
        $this->rpcLog(self::DIRECTION_IN, $message);

        $type = current($message);
        switch ($type) {
        case self::TYPE_REQUEST:
            $this->onRequest($message);
            break;
        case self::TYPE_RESPONSE:
            $this->onResponse($message);
            break;
        case self::TYPE_NOTIFICATION:
            $this->onNotification($message);
            break;
        }
    }

    private function onRequest($message)
    {
        list($type, $msg_id, $method, $params) = $message;

        $this->current_msg_id = $msg_id;

        $result = null;
        $error = null;
        if (method_exists($this, $method)) {
            $result = $this->doRequest([$this, $method], $params);
        } else {
            $error = 'method not exists';
        }
        $response = [self::TYPE_RESPONSE, $msg_id, $error, $result];
        $this->write($response);

        $this->current_msg_id = null;
    }

    private function onResponse($message)
    {
        list($type, $msg_id, $error, $result) = $message;
        $this->doCallback($msg_id, $error, $result);
    }

    private function onNotification($message)
    {
        list($type, $method, $params) = $message;
        if (method_exists($this, $method)) {
            $this->doRequest([$this, $method], $params);
        }
    }

    protected function doRequest($callback, $params)
    {
        return call_user_func_array($callback, $params);
    }

    protected function call($method, $params, $callback = null)
    {
        if ($callback) {
            $msg_id = $this->getMessageId();
            $message = [self::TYPE_REQUEST, $msg_id, $method, $params];
            $this->addCallback($msg_id, $callback);
        } else {
            $message = [self::TYPE_NOTIFICATION, $method, $params];
        }

        $this->write($message);
    }

    private function addCallback($msg_id, $callback)
    {
        $this->request_callback[$msg_id] = $addCallback;
    }

    private function doCallback($msg_id, $error, $result)
    {
        if (!array_key_exists($msg_id, $this->request_callback)) {
            return;
        }

        $callback = $this->request_callback[$msg_id];
        $callback($error, $result);
    }

    private function getMessageId()
    {
        return $this->msg_id++;
    }

    private function write(array $message)
    {
        $this->rpcLog(self::DIRECTION_OUT, $message);
        fwrite(STDOUT, msgpack_pack($message));
    }

    private $rpc_directions = [
        self::DIRECTION_IN  => 'NeoVim -> PHPCD',
        self::DIRECTION_OUT => 'PHPCD  -> NeoVim'
    ];

    private function getDirectionString($direction)
    {
        if (!array_key_exists($direction, $this->rpc_directions)) {
            throw new InvalidArgumentException('Invalid direction');
        }

        return sprintf("[RPC][%s]: ", $this->rpc_directions[$direction]);
    }

    private function rpcLog($direction, $message)
    {
        $message = $this->getDirectionString($direction) . json_encode($message);
        $this->logger->info($message);
    }
}
