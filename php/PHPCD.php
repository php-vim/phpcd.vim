<?php
class PHPCD
{
    private static $req_msg_id = 0;

    /**
     * 套接字
     */
    private $socket;

    /** @var MessagePackUnpacker $unpacker **/
    private $unpacker;

    /**
     * @param string $socket_path 套接字路径
     * @param string $autoload_path PHP 项目自动加载脚本
     */
    public function __construct($socket_path, $autoload_path = null)
    {
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        $this->unpacker = new MessagePackUnpacker();

        if ($autoload_path) {
            require $autoload_path;
        }

        socket_connect($this->socket, $socket_path);

        $this->setChannelId();
    }

    protected function getChannelId()
    {
        list($result, $error) = $this->callRpc('vim_get_api_info');
        return $result[0];
    }

    protected function setChannelId()
    {
        $command = 'let g:phpcd_channel_id = ' . $this->getChannelId();
        $this->callRpc('vim_command',  $command);
    }

    /**
     * @return [$result, $error]
     */
    protected function callRpc()
    {
        $args = func_get_args();
        if (count($args) === 0) {
            throw new InvalidArgumentException('at least one args');
        }

        $method = array_shift($args);

        $req = msgpack_pack([
            0,
            self::$req_msg_id++,
            $method,
            $args
        ]);

        socket_send($this->socket, $req, strlen($req), 0);

        // TODO 默认发送调用请求之后会立即得到相应
        foreach ($this->nextRpcMsg() as $msg) {
            return [$msg[3], $msg[2]];
        }
    }
    private function nextRpcMsg()
    {
        while (socket_recv($this->socket, $buf, 1024, 0)) {
            $this->unpacker->feed($buf);

            while ($this->unpacker->execute()) {
                $unserialized = $this->unpacker->data();
                $this->unpacker->reset();
                yield $unserialized;
            }
        }
    }

    public function loop()
    {
        foreach ($this->nextRpcMsg() as $msg) {
            echo json_encode($msg) . PHP_EOL;
            $pid = pcntl_fork();
            if ($pid == -1) {
                die('could not fork');
            } elseif ($pid) {
                pcntl_wait($status);
            } else {
                $this->on($msg);
                exit;
            }
        }
    }

    private function on($msg)
    {
        $msg_id = null;
        if (count($msg) == 4) {
            // rpc request
            list($type, $msg_id, $method, $args) = $msg;
        } elseif (count($msg)) {
            // rpc notify
            list($type, $method, $args) = $msg;
        }

        $result = null;
        $error = null;
        try {
            $result = $this->onCall($method, $args);
        } catch (Exception $e) {
            $error = true;
        }

        if (count($msg) == 4) {
            $this->sendResp($result, $msg_id, $error);
        }
    }

    private function sendResp($result, $msg_id = null, $error = null)
    {
        if ($msg_id) {
            $msg = msgpack_pack([
                1,
                $msg_id,
                null,
                $result,
            ]);
        } else {
            $msg = msgpack_pack([
                1,
                null,
                $result,
            ]);
        }

        socket_send($this->socket, $msg, strlen($msg), 0);
    }

    private function onCall($method, $args)
    {
        if (!method_exists($this, $method)) {
            return;
        }

        return call_user_func_array([$this, $method], $args);
    }

    public function info ($class_name, $pattern, $mode) {
        if ($class_name) {
            return $this->classInfo($class_name, $pattern, $mode);
        } else {
            return $this->functionOrConstantInfo($pattern);
        }
    }

    private function classInfo($class_name, $pattern, $mode)
    {
        $reflection = new ReflectionClass($class_name);
        $items = [];

        foreach ($reflection->getConstants() as $name => $value) {
            $items[] = [
                'word' => $name,
                'abbr' => "+ @ $name = $value",
                'kind' => 'd',
                'icase' => 1,
            ];
        }

        if ($mode == 1) {
            $methods = $reflection->getMethods(ReflectionMethod::IS_STATIC);
        } else {
            $methods = $reflection->getMethods();
        }
        foreach ($methods as $method) {
            $info = $this->getMethodInfo($method, $pattern);
            if ($info) {
                $items[] = $info;
            }
        }

        if ($mode == 1) {
            $properties = $reflection->getProperties(ReflectionProperty::IS_STATIC);
        } else {
            $properties = $reflection->getProperties();
        }

        foreach ($properties as $property) {
            $info = $this->getPropertyInfo($property, $pattern);
            if ($info) {
                $items[] = $info;
            }
        }

        return $items;
    }

    private function functionOrConstantInfo($pattern)
    {
        $items = [];
        $funcs = get_defined_functions();
        foreach ($funcs['internal'] as $func) {
            $info = $this->getFunctionInfo($func, $pattern);
            if ($info) {
                $items[] = $info;
            }
        }
        foreach ($funcs['user'] as $func) {
            $info = $this->getFunctionInfo($func, $pattern);
            if ($info) {
                $items[] = $info;
            }
        }

        return array_merge($items, $this->getConstantsInfo($pattern));
    }

    private function getConstantsInfo($pattern)
    {
        $items = [];
        foreach (get_defined_constants() as $name => $value) {
            if ($pattern && strpos($name, $pattern) !== 0) {
                continue;
            }

            $items[] = [
                'word' => $name,
                'abbr' => "@ $name = $value",
                'kind' => 'd',
                'icase' => 0,
            ];
        }

        return $items;
    }

    private function getFunctionInfo($name, $pattern = null)
    {
        if ($pattern && strpos($name, $pattern) !== 0) {
            return null;
        }

        $reflection = new ReflectionFunction($name);
        $params = array_map(function ($param) {
            return $param->getName();
        }, $reflection->getParameters());

        return [
            'word' => $name,
            'abbr' => "$name(" . join(', ', $params) . ')',
            'info' => preg_replace('#/?\*(\*|/)?#','', $reflection->getDocComment()),
            'kind' => 'f',
            'icase' => 1,
        ];
    }

    private function getPropertyInfo($property, $pattern)
    {
        $name = $property->getName();
        if ($pattern && strpos($name, $pattern) !== 0) {
            return null;
        }
        $modifier = $this->getModifier($property);

        return [
            'word' => $name,
            'abbr' => "$modifier $name",
            'info' => preg_replace('#/?\*(\*|/)?#','', $property->getDocComment()),
            'kind' => 'p',
            'icase' => 1,
        ];
    }

    private function getMethodInfo($method, $pattern = null)
    {
        $name = $method->getName();
        if ($pattern && strpos($name, $pattern) !== 0) {
            return null;
        }
        $params = array_map(function ($param) {
            return $param->getName();
        }, $method->getParameters());

        $modifier = $this->getModifier($method);

        return [
            'word' => $name,
            'abbr' => "$modifier $name (" . join(', ', $params) . ')',
            'info' => preg_replace('#/?\*(\*|/)?#','', $method->getDocComment()),
            'kind' => 'f',
            'icase' => 1,
        ];
    }

    private function getModifier($reflection)
    {
        $modifier = '';

        if ($reflection->isPublic()) {
            $modifier = '+';
        } elseif ($reflection->isProtected()) {
            $modifier = '#';
        } elseif ($reflection->isPrivate()) {
            $modifier = '-';
        } elseif ($reflection->isFinal()) {
            $modifier = '!';
        }

        $static = $reflection->isStatic() ? '@' : ' ';

        return "$modifier $static";
    }

    private function location($class_name, $method_name = null)
    {
        if ($class_name) {
            return $this->locationClass($class_name, $method_name);
        } else {
            return $this->locationFunction($method_name);
        }
    }

    private function locationClass($class_name, $method_name = null)
    {
        try {
            $class = new ReflectionClass($class_name);
            if (!$method_name) {
                return [
                    $class->getFileName(),
                    $class->getStartLine(),
                ];
            }

            $method  = $class->getMethod($method_name);

            if ($method) {
                return [
                    $method->getFileName(),
                    $method->getStartLine(),
                ];
            }
        } catch (ReflectionException $e) {
        }

        return [
            '',
            null,
        ];
    }

    private function locationFunction($name)
    {
        $func = new ReflectionFunction($name);
        return [
            $func->getFileName(),
            $func->getStartLine(),
        ];
    }

    private function docClass($class_name, $name)
    {
        $class = new ReflectionClass($class_name);
        if ($class->hasProperty($name)) {
            $property = $class->getProperty($name);
            return [
                $class->getFileName(),
                $property->getDocComment()
            ];
        } elseif ($class->hasMethod($name)) {
            $method = $class->getMethod($name);
            return [
                $class->getFileName(),
                $method->getDocComment()
            ];
        }
    }

    private function docFunction($name)
    {
        $function = new ReflectionFunction($name);

        return [
            $function->getFileName(),
            $function->getDocComment()
        ];
    }

    private function doc($class_name, $name)
    {
        if ($class_name && $name) {
            list($path, $doc) = $this->docClass($class_name, $name);
        } elseif ($name) {
            list($path, $doc) = $this->docFunction($name);
        }

        if ($doc) {
            return [$path, $this->clearDoc($doc)];
        } else {
            return [null, null];
        }
    }

    private function clearDoc($doc)
    {
        $doc = preg_replace('/[ \t]*\* ?/m','', $doc);
        return preg_replace('#\s*\/|/\s*#','', $doc);
    }

    private function nsuse($path)
    {
        $file = new SplFileObject($path);
        $s = [
            'namespace' => '',
            'imports' => [
            ],
        ];
        foreach ($file as $line) {
            if (preg_match('/\b(class|interface|trait)\b/i', $line)) {
                break;
            }
            $line = trim($line);
            if (!$line) {
                continue;
            }
            if (strtolower(substr($line, 0, 9)) == 'namespace') {
                $namespace = substr($line, 10, -1);
                $s['namespace'] = $namespace;
            } elseif (strtolower(substr($line, 0, 3) == 'use')) {
                $as_pos = strripos($line, ' as ');
                if ($as_pos !== false) {
                    $alias = trim(substr($line, $as_pos + 3, -1));
                    $s['imports'][$alias] = trim(substr($line, 3, $as_pos - 3));
                } else {
                    $slash_pos = strripos($line, '\\');
                    if ($slash_pos === false) {
                        $alias = trim(substr($line, 4, -1));
                    } else {
                        $alias = trim(substr($line, $slash_pos + 1, -1));
                    }
                    $s['imports'][$alias] = trim(substr($line, 4, -1));
                }
            }
        }

        return $s;
    }
}
