<?php
namespace PHPCD;

use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LoggerAwareTrait;
use Lvht\MsgpackRpc\Server as RpcServer;
use Lvht\MsgpackRpc\Handler as RpcHandler;
use PHPCD\PHPFileInfo\PHPFileInfoFactory;
use PHPCD\ClassInfo\ClassInfoFactory;

class PHPCD implements RpcHandler
{
    use LoggerAwareTrait;

    /**
     * @var RpcServer
     */
    private $server;

    private $root;

    /*
     * Probably it should be replaced by
     * correctly implemented repository
     * to avoid scanning each file each time
     * even if such was not changed in meantime.
     *
     * @var PHPFileInfoFactory
     */
    private $file_info_factory;

    public function __construct(
        $root,
        Logger $logger,
        ClassInfoFactory $class_info_factory,
        PHPFileInfoFactory $file_info_factory
    ) {
        $this->setRoot($root);
        $this->setLogger($logger);
        $this->class_info_factory = $class_info_factory;
        $this->file_info_factory = $file_info_factory;
    }

    /**
     * Set the composer root dir
     *
     * @param string $root the path
     * @return static
     */
    private function setRoot($root)
    {
        // @TODO do we need to validate this input variable?
        $this->root = $root;
        return $this;
    }

    public function setServer(RpcServer $server)
    {
        $this->server = $server;
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
    public function info($class_name, $pattern, $static_mode = 'both', $public_only = true)
    {
        if ($class_name) {
            $static_mode = $this->translateStaticMode($static_mode);
            return $this->getMatchingClassDetails($class_name, $pattern, $static_mode, $public_only);
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
        $origin = $path = $reflection->getFileName();
        $origin_reflection = $reflection;

        while ($reflection = $reflection->getParentClass()) {
            if ($reflection->hasConstant($const_name)) {
                $path = $reflection->getFileName();
            } else {
                break;
            }
        }

        if ($origin === $path) {
            $interfaces = $origin_reflection->getInterfaces();
            foreach ($interfaces as $interface) {
                if ($interface->hasConstant($const_name)) {
                    $path = $interface->getFileName();
                    break;
                }
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
    private function doc($class_name, $name, $is_method = true)
    {
        try {
            $reflection_class = null;
            if ($class_name) {
                $reflection = new \ReflectionClass($class_name);
                $reflection_class = $reflection;
                if (!$is_method) {
                    // ReflectionProperty does not have the getFileName method
                    // use ReflectionClass instead
                    $reflection = $reflection->getProperty($name);
                } else {
                    $interfaces = $reflection->getInterfaces();
                    foreach ($interfaces as $interface) {
                        if ($interface->hasMethod($name)) {
                            $reflection = $interface;
                            break;
                        }
                    }

                    $reflection = $reflection->getMethod($name);
                }
            } else {
                $reflection = new \ReflectionFunction($name);
            }

            $doc = $reflection->getDocComment();
            if (preg_match('/@return\s+(static|self|\$this)/i', $doc) && $reflection_class) {
                $path = $reflection_class->getFileName();
            } else {
                $path = $reflection->getFileName();
            }

            return [$path, $this->clearDoc($doc)];
        } catch (\ReflectionException $e) {
            $this->logger->debug((string) $e);
            return [null, null];
        }
    }

    /**
     * Fetch the php script's namespace and imports(by use) list.
     *
     * @param string $path the php script path
     * @param string $specific_alias in not empty, do not get information about other aliases
     *
     * @return [
     *   'namespace' => 'ns',
     *   'class' => 'shortname'
     *   'imports' => [
     *     'alias1' => 'fqdn1',
     *   ]
     * ]
     */
    public function nsuse($path)
    {
        $file_info = $this->file_info_factory->createFileInfo($path);
        return [
            'namespace' => $file_info->getNamespace(),
            'class' => $file_info->getClass(),
            'imports' => $file_info->getImports()
        ];
    }

    /**
     * Fetch the function or class method return value's type
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

        list($path, $doc) = $this->doc($class_name, $name);
        return $this->typeByDoc($path, $doc);
    }

    /**
     * Fetch class attribute's type by @var annotation
     *
     * @return [type1, type2, ...]
     */
    public function proptype($class_name, $name)
    {
        list($path, $doc) = $this->doc($class_name, $name, false);
        return $this->typeByDoc($path, $doc);
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
            $type = (string) $reflection->getReturnType();

            if (strtolower($type) == 'self') {
                $type = $class_name;
            }

            return $type;
        } catch (\ReflectionException $e) {
            $this->logger->debug((string) $e);
        }
    }

    private function typeByDoc($path, $doc) {
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

    private function getMatchingClassDetails($class_name, $pattern, $is_static, $public_only)
    {
        try {
            $reflection = $this->class_info_factory->createClassInfo($class_name);
            $items = [];

            if (false !== $is_static) {
                foreach ($reflection->getMatchingConstants($pattern) as $name => $value) {
                        $items[] = [
                            'word' => $name,
                            'abbr' => sprintf(" +@ %s %s", $name, $value),
                            'kind' => 'd',
                            'icase' => 1,
                        ];
                }
            }

            $methods = $reflection->getAvailableMethods($is_static, $public_only, $pattern);

            foreach ($methods as $method) {
                $items[] = $this->getMethodInfo($method, $pattern);
            }

            $properties = $reflection->getAvailableProperties($is_static, $public_only, $pattern);

            foreach ($properties as $property) {
                $items[] = $this->getPropertyInfo($property);
            }

            return $items;
        } catch (\ReflectionException $e) {
            $this->logger->debug($e->getMessage());
            return [null, []];
        }
    }

    public function getFixForNewClassUsage($path, array $new_class_params)
    {
        $info = $this->file_info_factory->createFileInfo($path);

        return $info->getFixForNewClassUsage($new_class_params);
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

    private function getPropertyInfo($property)
    {
        $name = $property->getName();

        $modifier = $this->getModifiers($property);
        if ($property->getModifiers() & \ReflectionMethod::IS_STATIC) {
            $name = '$'.$name;
        }

        return [
            'word' => $name,
            'abbr' => sprintf("%3s %s", $modifier, $name),
            'info' => preg_replace('#/?\*(\*|/)?#', '', $property->getDocComment()),
            'kind' => 'p',
            'icase' => 1,
        ];
    }

    private function getMethodInfo($method)
    {
        $name = $method->getName();

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

        if (isset($composer['autoload']['psr-4'])) {
            $list = $composer['autoload']['psr-4'];
        } else {
            $list = [];
        }

        if (isset($composer['autoload-dev']['psr-4'])) {
            $list_dev = $composer['autoload']['psr-4'];
        } else {
            $list_dev = [];
        }

        foreach ($list_dev as $namespace => $paths) {
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
