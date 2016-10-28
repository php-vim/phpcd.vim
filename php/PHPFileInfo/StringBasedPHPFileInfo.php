<?php

namespace PHPCD\PHPFileInfo;

use SplFileObject;

/**
 * @TODO fix validation when there exists class in the current namespace
 *      and built-in with the same name (e.g. Exception)
 */
class StringBasedPHPFileInfo implements PHPFileInfo
{
    const USE_PATTERN =
        '/^use\s+((?<type>(constant|function)) )?(?<left>[\\\\\w]+\\\\)?({)?(?<right>[\\\\,\w\s]+)(})?\s*;$/';

    const ALIAS_PATTERN = '/(?<suffix>[\\\\\w]+)(\s+as\s+(?<alias>\w+))?/';

    const CLASS_PATTERN = '/^\s*\b(?<type>(((final|abstract)\s+)?(?<isClass>class))|interface|trait)\s+(?<name>\S+)/i';

    const EXTENDS_PATTERN = '/\s*\bextends\s+(?<superclass>\S+)\b/i';

    const IMPLEMENTS_PATTERN = '/\s*\bimplements\s+(?<interfaces>([\w\\\\]+(,\s+)?)+)/i';

    const NAMESPACE_PATTERN = '/(<\?php)?\s*namespace\s+(?<namespace>.*);$/';

    /*
     * @var SplFileObject
     */
    private $file;

    private $namespace = '';

    private $class;

    private $type;

    private $superclass;

    private $interfaces = [];

    private $imports = [];

    private $errors = [];

    public function __construct(SplFileObject $file)
    {
        try {
            $this->file = $file;

            $this->validateSyntax();

            $this->scanFile();

            if (!empty($this->getSuperclass())) {
                if ($this->isClass()) {
                    $this->classExists($this->getSuperclass());
                }

                if ($this->isInterface()) {
                    $this->interfaceExists($this->getSuperClass());
                }
            }

            foreach ($this->getInterfaces() as $interface) {
                $this->interfaceExists($interface);
            }
        } catch (FileInfoException $e) {
            $this->addError($e->getMessage());
        }
    }

    private function rewindFile()
    {
        $this->file->rewind();
    }

    /**
     * Scan the file to retrieve informations
     * about its namespace, imports and class.
     *
     * @todo scan multiline declarations correctly
     */
    public function scanFile()
    {
        $this->rewindFile();

        foreach ($this->file as $line) {
            $class = $this->scanLineForClassDeclaration($line);
            if (!empty($class['name']) && !empty($class['type'])) {
                $this->setClass($class['name']);
                $this->setType($class['type']);

                $superclass = $this->scanLineForSuperclass($line);
                if (!empty($superclass)) {
                    $this->setSuperclass($superclass);
                }

                $interfaces = $this->scanLineForInterfaces($line);
                if (!empty($interfaces)) {
                    $this->setInterfaces($interfaces);
                }

                break;
            }

            $line = trim($line);
            if (!$line) {
                continue;
            }

            $namespace = $this->scanLineForNamespaceDeclaration($line);
            if ($namespace) {
                $this->setNamespace($namespace);
            } else {
                $imports = $this->scanLineForImports($line);

                if (!empty($imports)) {
                    $this->mergeImports($imports);
                }
            }
        }

        $this->rewindFile();
    }

    /**
     * @var string $class
     * @return $this
     */
    private function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    private function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return bool
     */
    public function isClass()
    {
        return ($this->type === 'class');
    }

    /**
     * @return bool
     */
    public function isInterface()
    {
        return ($this->type === 'interface');
    }

    /**
     * @return bool
     */
    public function isTrait()
    {
        return ($this->type === 'trait');
    }

    /**
     * Get type
     *
     * @return string|null
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @var string $class
     * @return $this
     */
    private function setSuperclass($class)
    {
        $this->superclass = $class;

        return $this;
    }

    private function setInterfaces($interfaces)
    {
        $this->interfaces = $interfaces;

        return $this;
    }

    /**
     * @var string
     */
    private function setNamespace($namespace)
    {
        $this->namespace = $namespace;

        return $this;
    }

    private function mergeImports(array $imports)
    {
        $this->imports = $imports + $this->imports;

        return $this;
    }

    private function scanLineForNamespaceDeclaration($line)
    {
        if (preg_match(self::NAMESPACE_PATTERN, $line, $matches)) {
            return $matches['namespace'];
        }

        return null;
    }

    private function scanLineForClassDeclaration($line)
    {
        if (preg_match(self::CLASS_PATTERN, $line, $matches)) {
            $type = $matches['type'];
            if (!empty($matches['isClass'])) {
                $type = 'class';
            }

            return [
                'name' => $matches['name'],
                'type' => $type
            ];
        }
    }

    private function scanLineForSuperclass($line)
    {
        if (preg_match(self::EXTENDS_PATTERN, $line, $matches)) {
            return $matches['superclass'];
        }
    }

    private function scanLineForInterfaces($line)
    {
        $interfaces = [];

        if (preg_match(self::IMPLEMENTS_PATTERN, $line, $matches)) {
            $matches = explode(',', $matches['interfaces']);

            foreach ($matches as $interface) {
                $interfaces[] = trim($interface);
            }
        }

        return $interfaces;
    }

    private function scanLineForImports($line)
    {
        $imports = [];

        if (strtolower(substr($line, 0, 3) == 'use')) {
            if (preg_match(self::USE_PATTERN, $line, $use_matches) && !empty($use_matches)) {
                $expansions = array_map('self::trim', explode(',', $use_matches['right']));

                foreach ($expansions as $expansion) {
                    if (preg_match(self::ALIAS_PATTERN, $expansion, $expansion_matches) && !empty($expansion_matches)) {
                        $suffix = $expansion_matches['suffix'];

                        if (empty($expansion_matches['alias'])) {
                            // Get default alias
                            $suffix_parts = explode('\\', $suffix);
                            $alias = array_pop($suffix_parts);
                        } else {
                            $alias = $expansion_matches['alias'];
                        }
                    }

                    /** empty type means import of some class **/
                    if (empty($use_matches['type'])) {
                        $imports[$alias] = $use_matches['left'] . $suffix;
                    }
                    // @todo case when $use_matches['type'] is 'constant' or 'function'
                    // This requires change of the oputput format because
                    // we can use the same alias string to some class and some function at once
                }
            }
        }

        return $imports;
    }

    private static function trim($str)
    {
        return trim($str, "\t\n\r\0\x0B\\ ");
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function getSuperclass()
    {
        return $this->superclass;
    }

    public function getInterfaces()
    {
        return $this->interfaces;
    }

    public function getImports()
    {
        return $this->imports;
    }

    public function getFullClassPath()
    {
        return $this->getFullPath($this->getClass());
    }

    public function hasAliasUsed($alias)
    {
        return !empty($this->imports[$alias]);
    }

    public function hasErrors()
    {
        return ! empty($this->errors);
    }

    private function addError($error)
    {
        $this->errors[] = $error;

        return $this;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getUsedAliasesForPath($full_path)
    {
        $used = array_filter($this->imports, function ($value) use ($full_path) {
            return $value === $full_path;
        });

        return array_keys($used);
    }

    public function getPathByAlias($alias)
    {
        if ($this->hasAliasUsed($alias)) {
            return $this->imports[$alias];
        }

        return null;
    }

    /**
     * Given an initial alias of class and class path corresponding to it
     * check that that alias can be used then return array of arrays consisting of:
     *  - alias: null if we can use initial alias, otherwise generated alternative alias
     *  - full_path: null if there is no need to make new import,
     *      otherwise path to import associated with the above alias
     *
     * Sometimes the same path may associated with more than one aliases (see unit test)
     * so this function returns array of suggestions to be used by client.
     *
     * @param array $new_class_params {
     *  @type string  $alias
     *  @type string  $full_path
     *  }
     *
     * @return array {
     *  @type string        $alias        the original or modified alias
     *  @type string|null   $full_path    null if we have no new import to do
     *  }[]
     */
    public function getFixForNewClassUsage(array $new_class_params)
    {
        $new_alias  = $new_class_params['alias'];
        $new_path   = trim($new_class_params['full_path'], '\\');

        $used_aliases = $this->getUsedAliasesForPath($new_path);
        if (!empty($used_aliases)) {
            $suggestions = [];
            foreach ($used_aliases as $alias) {
                if ($alias === $new_alias) {
                    // Nothing to do
                    return [['alias' => null, 'full_path' => null ]];
                }

                $suggestions[] = [ 'alias' => $alias, 'full_path' => null ];
            }

            return $suggestions;
        }

        if (!empty($this->imports)) {
            if ($this->hasAliasUsed($new_alias)) {
                if ($this->extractNamespaceFromPath($new_path) === $this->getNamespace()) {
                    return [['alias' => 'namespace\\'.$new_alias, 'full_path' => null ]];
                }

                // The path was not used,
                // but an alternative alias is needed.
                $new_alias = $this->generateNewAlias($new_alias);

                return [['alias' => $new_alias, 'full_path' => $new_path ]];
            }
        }

        if ($new_alias === $this->getClass()) {
            // Although it is not an error, we encourage
            // to not override the current class name with
            // an the same named alias of another path.
            $new_alias = $this->generateNewAlias($new_alias);

            return [['alias' => $new_alias, 'full_path' => $new_path ]];
        }

        // The alias was not used, so it does not need change,
        // the path need to insert
        return [['alias' => null, 'full_path' => $new_path ]];
    }

    private function extractNamespaceFromPath($path)
    {
        return substr($path, 0, strrpos($path, '\\'));
    }

    private function generateNewAlias($alias)
    {
        $suffix = '1';

        do {
            ++$suffix;
        } while ($this->canNewAliasConflict($alias.$suffix));

        return $alias.$suffix;
    }

    public function canNewAliasConflict($alias)
    {
        if ($this->hasAliasUsed($alias) || $alias === $this->getClass()) {
            return true;
        }

        // TODO: use an external knowledge from repository
        // and check if there is no class in the current namespace
        // whose name is the same as the newly generated alias.
        return false;
    }

    private function validateSyntax()
    {
        $path = $this->file->getPathName();
        $php_cmd = sprintf('/usr/bin/php -l %s >/dev/null 2>&1', $path);

        system($php_cmd, $return_code);

        if ($return_code !== 0) {
            throw new FileInfoException('Syntax error');
        }

        return (! $return_code);
    }

    /**
     * Check if given class exists
     * @return bool
     */
    private function classExists($className)
    {
        $className = $this->getFullPath($className);

        $exists = class_exists($className);

        if (!$exists) {
            throw new FileInfoException(sprintf('Class %s does not exist.', $className));
        }

        return $exists;
    }

    /**
     * Check if given interface exists
     * @return bool
     */
    private function interfaceExists($interfaceName)
    {
        $interfaceName = $this->getFullPath($interfaceName);

        $exists = interface_exists($interfaceName);

        if (!$exists) {
            throw new FileInfoException(sprintf('Interface %s does not exist.', $interfaceName));
        }

        return $exists;
    }

    private function getFullPath($className)
    {
        if (strpos($className, '\\') !== 0) {
            $path = explode('\\', $className);
            $first = array_shift($path);

            if ($this->hasAliasUsed($first)) {
                return implode('\\', array_merge(explode('\\', $this->getPathByAlias($first)), $path));
            }

            return sprintf('%s\%s', $this->getNamespace(), $className);
        }

        return $className;
    }
}
