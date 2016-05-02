<?php

namespace PHPCD;

use Psr\Log\LoggerInterface;

class PHPCD extends RpcServer
{
    const MATCH_SUBSEQUENCE = 'match_subsequence';
    const MATCH_HEAD        = 'match_head';

    private $matchType = self::MATCH_HEAD;

    /**
     * Set type of matching
     *
     * @param string $matchType
     * @return null;
     */
    public function setMatchType($matchType)
    {
        if ($matchType !== self::MATCH_SUBSEQUENCE && $matchType !== self::MATCH_HEAD) {
            throw new \InvalidArgumentException('Wrong match type');
        }

        $this->matchType = $matchType;

        return null;
    }

    public function __construct(
        $root,
        \MessagePackUnpacker $unpacker,
        LoggerInterface $logger
    ) {
        parent::__construct($root, $unpacker, $logger);
    }

    /**
     *  @param array Map between modifier numbers and displayed symbols
     */
    private $modifier_symbols = [
        \ReflectionMethod::IS_FINAL      => '!',
        \ReflectionMethod::IS_PRIVATE    => '-',
        \ReflectionMethod::IS_PROTECTED  => '#',
        \ReflectionMethod::IS_PUBLIC     => '+',
        \ReflectionMethod::IS_STATIC     => '@'
    ];

    /**
     * @param string $mode
     * @return bool|null
     */
    private function translateStaticMode($mode)
    {
        $map = [
            'both'           => null,
            'only_nonstatic' => false,
            'only_static'    => true
        ];

        return isset($map[$mode]) ? $map[$mode] : null;
    }

    /**
     * Fetch the completion list.
     *
     * If both $class_name and $pattern are setted, it will list the class's
     * methods, constants, and properties, filted by pattern.
     *
     * If only $pattern is setted, it will list all the defined function
     * (including the PHP's builtin function', filted by pattern.
     *
     * @var string $class_name
     * @var string $pattern
     * @var string $static_mode see translateStaticMode method
     * @var bool $public_only
     */
    public function info($class_name, $pattern, $static_mode = 'both', $public_only)
    {
        if ($class_name) {
            $static_mode = $this->translateStaticMode($static_mode);
            return $this->classInfo($class_name, $pattern, $static_mode, $public_only);
        }

        if ($pattern) {
            return $this->functionOrConstantInfo($pattern);
        }

        return [];
    }

    /**
     * Fetch function or class method's source file path
     * and their defination line number.
     *
     * @param string $class_name class name
     * @param string $method_name method or function name
     *
     * @return [path, line]
     */
    public function location($class_name, $method_name = null)
    {
        try {
            if ($class_name) {
                $reflection = new \ReflectionClass($class_name);
                if ($method_name) {
                    if ($reflection->hasMethod($method_name)) {
                        $reflection = $reflection->getMethod($method_name);
                    } elseif ($reflection->hasConstant($method_name)) {
                        // 常量则返回 [ path, 'const CONST_NAME' ]
                        return [$this->getConstPath($method_name, $reflection), 'const ' . $method_name];
                    }
                }
            } else {
                $reflection = new \ReflectionFunction($method_name);
            }

            return [$reflection->getFileName(), $reflection->getStartLine()];
        } catch (\ReflectionException $e) {
            return ['', null];
        }
    }

    private function getConstPath($const_name, \ReflectionClass $reflection)
    {
        $path = $reflection->getFileName();

        while ($reflection = $reflection->getParentClass()) {
            if ($reflection->hasConstant($const_name)) {
                $path = $reflection->getFileName();
            } else {
                break;
            }
        }

        return $path;
    }

    /**
     * Fetch function, class method or class attribute's docblock
     *
     * @param string $class_name for function set this args to empty
     * @param string $name
     */
    private function doc($class_name, $name)
    {
        try {
            $reflection_class = null;
            if ($class_name) {
                $reflection = new \ReflectionClass($class_name);
                if ($reflection->hasProperty($name)) {
                    $reflection_class = $reflection;
                    // ReflectionProperty does not have the getFileName method
                    // use ReflectionClass instead
                    $reflection = $reflection->getProperty($name);
                } else {
                    $reflection = $reflection->getMethod($name);
                }
            } else {
                $reflection = new \ReflectionFunction($name);
            }

            $path = $reflection_class ? $reflection_class->getFileName()
                : $reflection->getFileName();
            $doc = $reflection->getDocComment();

            return [$path, $this->clearDoc($doc)];
        } catch (\ReflectionException $e) {
            $this->logger->debug((string) $e);
            return [null, null];
        }
    }

    /**
     * Fetch the php script's namespace and imports(by use) list.
     *
     * @param string $path the php scrpit path
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
        $use_pattern =
            '/^use\s+((?<type>(constant|function)) )?(?<left>[\\\\\w]+\\\\)?({)?(?<right>[\\\\,\w\s]+)(})?\s*;$/';
        $alias_pattern = '/(?<suffix>[\\\\\w]+)(\s+as\s+(?<alias>\w+))?/';
        $class_pattern = '/^\s*\b((((final|abstract)\s+)?class)|interface|trait)\s+(?<class>\S+)/i';

        $file = new \SplFileObject($path);
        $s = [
            'namespace' => '',
            'imports' => [
            ],
            'class' => '',
        ];

        foreach ($file as $line) {
            if (preg_match($class_pattern, $line, $matches)) {
                $s['class'] = $matches['class'];
                break;
            }
            $line = trim($line);
            if (!$line) {
                continue;
            }
            if (preg_match('/(<\?php)?\s*namespace\s+(.*);$/', $line, $matches)) {
                $s['namespace'] = $matches[2];
            } elseif (strtolower(substr($line, 0, 3) == 'use')) {
                if (preg_match($use_pattern, $line, $use_matches) && !empty($use_matches)) {
                    $expansions = array_map('self::trim', explode(',', $use_matches['right']));

                    foreach ($expansions as $expansion) {
                        if (preg_match($alias_pattern, $expansion, $expansion_matches) && !empty($expansion_matches)) {
                            $suffix = $expansion_matches['suffix'];

                            if (empty($expansion_matches['alias'])) {
                                $suffix_parts = explode('\\', $suffix);
                                $alias = array_pop($suffix_parts);
                            } else {
                                $alias = $expansion_matches['alias'];
                            }
                        }

                        /** empty type means import of some class **/
                        if (empty($use_matches['type'])) {
                            $s['imports'][$alias] = $use_matches['left'] . $suffix;
                        }
                        // @todo case when $use_matches['type'] is 'constant' or 'function'
                    }
                }
            }
        }

        return $s;
    }

    private static function trim($str)
    {
        return trim($str, "\t\n\r\0\x0B\\ ");
    }

    /**
     * Fetch the function or class method return value's type
     * and class attribute's type.
     *
     * For PHP7 or newer version, it tries to use the return type gramar
     * to fetch the real return type.
     *
     * For PHP5, it use the docblock's return or var annotation to fetch
     * the type.
     *
     * @return [type1, type2]
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
                $reflection = new \ReflectionClass($class_name);
                $reflection = $reflection->getMethod($name);
            } else {
                $reflection = new \ReflectionFunction($name);
            }
            $type = $reflection->getReturnType();

            return (string) $type;
        } catch (\ReflectionException $e) {
            $this->logger->debug((string) $e);
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

    private function classInfo($class_name, $pattern, $is_static, $public_only)
    {
        try {
            $reflection = new \PHPCD\Reflection\ReflectionClass($class_name);
            $items = [];

            if (false !== $is_static) {
                foreach ($reflection->getConstants() as $name => $value) {
                    if (!$pattern || $this->matchPattern($pattern, $name)) {
                        $items[] = [
                            'word' => $name,
                            'abbr' => sprintf(" +@ %s %s", $name, $value),
                            'kind' => 'd',
                            'icase' => 1,
                        ];
                    }
                }
            }

            $methods = $reflection->getAvailableMethods($is_static, $public_only);

            foreach ($methods as $method) {
                $info = $this->getMethodInfo($method, $pattern);
                if ($info) {
                    $items[] = $info;
                }
            }

            $properties = $reflection->getAvailableProperties($is_static, $public_only);

            foreach ($properties as $property) {
                $info = $this->getPropertyInfo($property, $pattern);
                if ($info) {
                    $items[] = $info;
                }
            }

            return $items;
        } catch (\ReflectionException $e) {
            $this->logger->debug($e->getMessage());
            return [null, []];
        }
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

        $reflection = new \ReflectionFunction($name);
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
        if ($pattern && !$this->matchPattern($pattern, $name)) {
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
        if ($pattern && !$this->matchPattern($pattern, $name)) {
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
     * @return bool
     */
    private function matchPattern($pattern, $fullString)
    {
        if (!$pattern) {
            return true;
        }

        switch ($this->matchType) {
            case self::MATCH_SUBSEQUENCE:
                // @TODO Case sensitivity of matching should be probably configurable
                // @TODO Quote characters that may be treat not literally
                $regex = '/'.implode('.*', str_split($pattern)).'/i';

                return (bool)preg_match($regex, $fullString);

            case self::MATCH_HEAD:
                return (stripos($fullString, $pattern) === 0);
                break;
        }

        return false;
    }

    /**
     *
     * @return array
     */
    private function getModifierSymbols()
    {
        return $this->modifier_symbols;
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

    /**
     * generate psr4 namespace according composer.json and file path
     */
    public function psr4ns($path)
    {
        $dir = dirname($path);

        $composer_path = $this->root . '/composer.json';
        $composer = json_decode(file_get_contents($composer_path), true);

        $list = (array) @$composer['autoload']['psr-4'];
        foreach ((array) @$composer['autoload-dev']['psr-4'] as $namespace => $paths) {
            if (isset($list[$namespace])) {
                $list[$namespace] = array_merge((array)$list[$namespace], (array) $paths);
            } else {
                $list[$namespace] = (array) $paths;
            }
        }

        $namespaces = [];
        foreach ($list as $namespace => $paths) {
            foreach ((array)$paths as $path) {
                $path = realpath($this->root.'/'.$path);
                if (strpos($dir, $path) === 0) {
                    $sub_path = str_replace($path, '', $dir);
                    $sub_path = str_replace('/', '\\', $sub_path);
                    $sub_namespace = trim(ucwords($sub_path, '\\'), '\\');
                    if ($sub_namespace) {
                        $sub_namespace = '\\' . $sub_namespace;
                    }
                    $namespaces[] = trim($namespace, '\\').$sub_namespace;
                }
            }
        }

        return $namespaces;
    }
}
