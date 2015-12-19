<?php

class PHPCD extends RpcServer
{
    /**
     *  @param array Map between modifier numbers and displayed symbols
     */
    private $modifierSymbols = [
        ReflectionMethod::IS_FINAL      => '!',
        ReflectionMethod::IS_PRIVATE    => '-',
        ReflectionMethod::IS_PROTECTED  => '#',
        ReflectionMethod::IS_PUBLIC     => '+',
        ReflectionMethod::IS_STATIC     => '@'
    ];


    /**
     * @param string $isStatic
     * @return bool|null
     */
    private function translateIsStaticInput($isStatic)
    {
        $map = [
            'both'           => null,
            'only_nonstatic' => false,
            'only_static'    => true
        ];

        return isset($map[$isStatic]) ? $map[$isStatic] : null;
    }


    public function info($class_name, $pattern, $isStatic = 'both', $publicInfoOnly)
    {
        if ($class_name) {
            return $this->classInfo($class_name, $pattern, $this->translateIsStaticInput($isStatic), $publicInfoOnly);
        }

        if ($pattern) {
            return $this->functionOrConstantInfo($pattern);
        }

        return [];
    }

    /**
     * 获取函数或者类成员方法的源代码位置（文件路径和行号）
     *
     * @param string $class_name 类名，传空值则表示函数
     * @param string $method_name 函数名或者方法名
     *
     * @return [ path, line ]
     */
    public function location($class_name, $method_name = null)
    {
        try {
            if ($class_name) {
                $reflection = new ReflectionClass($class_name);
                if ($method_name) {
                    $reflection = $reflection->getMethod($method_name);
                }
            } else {
                $reflection = new ReflectionFunction($method_name);
            }

            return [$reflection->getFileName(), $reflection->getStartLine()];
        } catch (ReflectionException $e) {
            $this->log((string) $e);
            return ['', null];
        }
    }

    /**
     * 获取类成员方法、成员变量或者函数的注释块
     *
     * @param string $class_name 类名，传空值则表示第二个参数为函数名
     * @param string $name 函数名或者成员名
     */
    private function doc($class_name, $name)
    {
        try {
            if ($class_name) {
                $reflection = new ReflectionClass($class_name);
                if ($reflection->hasProperty($name)) {
                    $reflection = $reflection->getProperty($name);
                } else {
                    $reflection = $reflection->getMethod($name);
                }
            } else {
                $reflection = new ReflectionFunction($name);
            }

            $path = $reflection->getFileName();
            $doc = $reflection->getDocComment();

            return [$path, $this->clearDoc($doc)];
        } catch (ReflectionException $e) {
            $this->log((string) $e);
            return [null, null];
        }
    }

    /**
     * 获取 PHP 文件的名称空间和 use 列表
     *
     * @param string $path 文件路径
     *
     * @return [
     *   'namespace' => 'ns',
     *   'imports' => [
     *     'alias1' => 'fqdn1',
     *   ]
     * ]
     */
    public function nsuse($path)
    {
        $file = new SplFileObject($path);
        $s = [
            'namespace' => '',
            'imports' => [
            ],
            'class' => '',
        ];
        foreach ($file as $line) {
            if (preg_match('/\b(class|interface|trait)\s+(\S+)/i', $line, $matches)) {
                $s['class'] = $matches[2];
                break;
            }
            $line = trim($line);
            if (!$line) {
                continue;
            }
            if (preg_match('/(<\?php)?\s*namespace\s+(.*);$/', $line, $matches)) {
                $s['namespace'] = $matches[2];
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

    /**
     * 获取函数或者成员方法的返回值类型
     */
    public function functype($class_name, $name)
    {
        if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
            $type = $this->typeByReturnType($class_name, $name);
            if ($type) {
                return [$type];
            }
        }

        return $this->typeByDoc($class_name, $name);
    }

    private function typeByReturnType($class_name, $name)
    {
        try {
            if ($class_name) {
                $reflection = new ReflectionClass($class_name);
                $reflection = $reflection->getMethod($name);
            } else {
                $reflection = new ReflectionFunction($name);
            }
            $type = $reflection->getReturnType();

            return (string) $type;
        } catch (ReflectionException $e) {
            $this->log((string) $e);
        }
    }

    private function typeByDoc($class_name, $name) {
        list($path, $doc) = $this->doc($class_name, $name);
        $has_doc = preg_match('/@(return|var)\s+(\S+)/m', $doc, $matches);
        if (!$has_doc) {
            return [];
        }

        $nsuse = $this->nsuse($path);

        $types = [];
        foreach (explode('|', $matches[2]) as $type) {
            if (isset($this->primitive_types[$type])) {
                continue;
            }

            if (in_array(strtolower($type) , ['static', '$this', 'self'])) {
                $type = $nsuse['namespace'] . '\\' . $nsuse['class'];
            } elseif ($type[0] != '\\') {
                $parts = explode('\\', $type);
                $alias = array_shift($parts);
                if (isset($nsuse['imports'][$alias])) {
                    $type = $nsuse['imports'][$alias];
                    if ($parts) {
                        $type = $type . '\\' . join('\\', $parts);
                    }
                } else {
                    $type = $nsuse['namespace'] . '\\' . $type;
                }
            }

            if ($type) {
                if ($type[0] != '\\') {
                    $type = '\\' . $type;
                }
                $types[] = $type;
            }
        }

        return $types;
    }

    private $primitive_types = [
        'array'    => 1,
        'bool'     => 1,
        'callable' => 1,
        'double'   => 1,
        'float'    => 1,
        'int'      => 1,
        'mixed'    => 1,
        'null'     => 1,
        'object'   => 1,
        'resource' => 1,
        'scalar'   => 1,
        'string'   => 1,
        'void'     => 1,
    ];

    private function classInfo($class_name, $pattern, $isStatic, $publicInfoOnly)
    {
        $reflection = new \Reflection\ReflectionClass($class_name);
        $items = [];

        foreach ($reflection->getConstants() as $name => $value) {
            $items[] = [
                'word' => $name,
                'abbr' => "+ @ $name = $value",
                'kind' => 'd',
                'icase' => 1,
            ];
        }

        $methods = $reflection->getAvailableMethods($isStatic, $publicInfoOnly);

        foreach ($methods as $method) {
            $info = $this->getMethodInfo($method, $pattern);
            if ($info) {
                $items[] = $info;
            }
        }

        if ($isStatic) {
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

        $modifier = $this->getModifiers($property);

        return [
            'word' => $name,
            'abbr' => sprintf("%3s %s", $modifier, $name),
            'info' => preg_replace('#/?\*(\*|/)?#', '', $property->getDocComment()),
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

        $modifier = $this->getModifiers($method);

        return [
            'word' => $name,
            'abbr' => sprintf("%3s %s (%s)", $modifier, $name, join(', ', $params)),
            'info' => $this->clearDoc($method->getDocComment()),
            'kind' => 'f',
            'icase' => 1,
        ];
    }

    /**
     *
     * @return array
     */
    private function getModifierSymbols()
    {
        return $this->modifierSymbols;
    }


    private function getModifiers($reflection)
    {
        $signs = '';

        $modifiers = $reflection->getModifiers();
        $symbols = $this->getModifierSymbols();

        foreach ($symbols as $number => $sign) {
            if ($number & $modifiers) {
                $signs .= $sign;
            }
        }

        return $signs;
    }

    private function clearDoc($doc)
    {
        $doc = preg_replace('/[ \t]*\* ?/m','', $doc);
        return preg_replace('#\s*\/|/\s*#','', $doc);
    }
}
