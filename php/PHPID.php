<?php

namespace PHPCD;

use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LoggerAwareTrait;
use Lvht\MsgpackRpc\Server as RpcServer;
use Lvht\MsgpackRpc\Handler as RpcHandler;
use PHPCD\Filter\ClassFilter;
use PHPCD\ClassInfo\ClassInfoRepository;
use PHPCD\ClassInfo\ClassInfoCollection;

class PHPID implements RpcHandler
{
    use LoggerAwareTrait;

    /**
     * @var RpcServer
     */
    private $server;

    private $root;

    /**
     * @var ClassInfoRepository
     */
    private $classes_repository;

    public function __construct(
        $root,
        Logger $logger,
        ClassInfoRepository $classes_repository
    ) {
        $this->setRoot($root);
        $this->setLogger($logger);
        $this->setClassInfoRepository($classes_repository);
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

    protected function setClassInfoRepository(ClassInfoRepository $classes_repository)
    {
        $this->classes_repository = $classes_repository;
        return $this;
    }

    /**
     * update index for one class
     *
     * @param string $class_name fqdn
     */
    public function update($class_name)
    {
        list($parent, $interfaces) = $this->getClassInfo($class_name);

        if ($parent) {
            $this->updateParentIndex($parent, $class_name);
        }
        foreach ($interfaces as $interface) {
            $this->updateInterfaceIndex($interface, $class_name);
        }
    }

    /**
     * Fetch an interface's implemation list,
     * or an abstract class's child class.
     *
     * @param string $name name of interface or abstract class
     * @param bool $is_abstract_class
     *
     * @return [
     *   'full class name 1',
     *   'full class name 2',
     * ]
     */
    public function ls($name, $is_abstract_class = false)
    {
        $base_path = $is_abstract_class ? $this->getIntefacesDir()
            : $this->getExtendsDir();
        $path = $base_path . '/' . $this->getIndexFileName($name);
        if (!is_file($path)) {
            return [];
        }

        $list = json_decode(file_get_contents($path));
        if (!is_array($list)) {
            return [];
        }

        sort($list);

        return $list;
    }

    /**
     * Fetch and save class's interface and parent info
     * according the autoload_classmap.php file
     *
     * @param bool $is_force overwrite the exists index
     */
    public function index()
    {
        $this->initIndexDir();

        exec('composer dump-autoload -o -d ' . $this->root . ' 2>&1 >/dev/null');

        $this->class_map = require $this->root
            . '/vendor/composer/autoload_classmap.php';

        $pipe_path = sys_get_temp_dir() . '/' . uniqid();
        posix_mkfifo($pipe_path, 0600);

        $this->vimOpenProgressBar(count($this->class_map));

        while ($this->class_map) {
            $pid = pcntl_fork();

            if ($pid == -1) {
                die('could not fork');
            } elseif ($pid > 0) {
                // 父进程
                $pipe = fopen($pipe_path, 'r');
                $data = fgets($pipe);
                $this->class_map = json_decode(trim($data), true);
                pcntl_waitpid($pid, $status);
            } else {
                // 子进程
                $pipe = fopen($pipe_path, 'w');
                register_shutdown_function(function () use ($pipe) {
                    $data = json_encode($this->class_map, true);
                    fwrite($pipe, "$data\n");
                    fclose($pipe);
                });
                $this->_index();
                fwrite($pipe, "[]\n");
                fclose($pipe);
                exit;
            }
        }
        fclose($pipe);
        unlink($pipe_path);
        $this->vimCloseProgressBar();
    }

    private function getIndexDir()
    {
        return $this->root . '/.phpcd';
    }

    private function getIntefacesDir()
    {
        return $this->getIndexDir() . '/interfaces';
    }

    private function getExtendsDir()
    {
        return $this->getIndexDir() . '/extends';
    }

    private function initIndexDir()
    {
        $extends_dir = $this->getExtendsDir();
        if (!is_dir($extends_dir)) {
            mkdir($extends_dir, 0700, true);
        }

        $interfaces_dir = $this->getIntefacesDir();
        if (!is_dir($interfaces_dir)) {
            mkdir($interfaces_dir, 0700, true);
        }
    }

    private function _index()
    {
        foreach ($this->class_map as $class_name => $file_path) {
            unset($this->class_map[$class_name]);
            $this->vimUpdateProgressBar();
            require $file_path;
            $this->update($class_name);
        }
    }

    private function updateParentIndex($parent, $child)
    {
        $index_file = $this->getExtendsDir() . '/' . $this->getIndexFileName($parent);
        $this->saveChild($index_file, $child);
    }

    private function updateInterfaceIndex($interface, $implementation)
    {
        $index_file = $this->getIntefacesDir() . '/' . $this->getIndexFileName($interface);
        $this->saveChild($index_file, $implementation);
    }

    private function saveChild($index_file, $child)
    {
        $index_directory = dirname($index_file);

        if (!is_dir($index_directory)) {
            mkdir($index_directory, 0755, true);
        }

        if (is_file($index_file)) {
            $childs = json_decode(file_get_contents($index_file));
        } else {
            $childs = [];
        }

        $childs[] = $child;
        $childs = array_unique($childs);
        file_put_contents($index_file, json_encode($childs));
    }

    private function getIndexFileName($name)
    {
        return str_replace("\\", '_', $name);
    }

    private function getClassInfo($name) {
        try {
            $reflection = new \ReflectionClass($name);

            $parent = $reflection->getParentClass();
            if ($parent) {
                $parent = $parent->getName();
            }

            $interfaces = array_keys($reflection->getInterfaces());

            return [$parent, $interfaces];
        } catch (\ReflectionException $e) {
            return [null, []];
        }
    }

    private function vimOpenProgressBar($max)
    {
        $cmd = 'let g:pb = vim#widgets#progressbar#NewSimpleProgressBar("Indexing:", ' . $max . ')';
        $this->server->call('vim_command', [$cmd]);
    }

    private function vimUpdateProgressBar()
    {
        $this->server->call('vim_command', ['call g:pb.incr()']);
    }

    private function vimCloseProgressBar()
    {
        $this->server->call('vim_command', ['call g:pb.restore()']);
    }

    public function getAbsoluteClassesPaths($path_pattern)
    {
        $filter = new ClassFilter([], $path_pattern);

        $collection = $this->classes_repository->find($filter);

        return $this->prepareOutputFromClassInfoCollection($collection, false);
    }

    public function getInterfaces($path_pattern)
    {
        $filter = new ClassFilter([ClassFilter::IS_INTERFACE => true], $path_pattern);

        $collection = $this->classes_repository->find($filter);

        return $this->prepareOutputFromClassInfoCollection($collection, true);
    }

    public function getPotentialSuperclasses($path_pattern)
    {
        $filter = new ClassFilter([
            ClassFilter::IS_FINAL => false,
            ClassFilter::IS_TRAIT => false,
            ClassFilter::IS_INTERFACE => false
        ], $path_pattern);

        $collection = $this->classes_repository->find($filter);

        return $this->prepareOutputFromClassInfoCollection($collection, true);
    }

    public function getInstantiableClasses($path_pattern)
    {
        $filter = new ClassFilter([ClassFilter::IS_INSTANTIABLE => true], $path_pattern);

        $collection = $this->classes_repository->find($filter);

        return $this->prepareOutputFromClassInfoCollection($collection, true);
    }

    public function getNamesToTypeDeclaration($path_pattern)
    {
        // @TODO add basic type here, not in repository
        $filter = new ClassFilter([ClassFilter::IS_TRAIT => false], $path_pattern);

        $collection = $this->classes_repository->find($filter);

        return $this->prepareOutputFromClassInfoCollection($collection, true);
    }

    /**
     * Prepare single element of completion output
     * @param ClassInfo $class_info
     * @param bool $leading_backslash prepend class path with backslash
     * @return array
     */
    private function prepareOutputFromClassInfoCollection(
        ClassInfoCollection $collection,
        $leading_backslash = true
    ) {
        $result = [];

        foreach ($collection as $class_info) {
            $result[] = [
                'full_name' => ($leading_backslash ? '\\' : '') . $class_info->getName(),
                'short_name' => $class_info->getShortName(),
                'doc_comment' => $class_info->getDocComment()
            ];
        }

        return $result;
    }
}
