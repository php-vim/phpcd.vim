<?php

namespace PHPCD\ClassInfo;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use PHPCD\PatternMatcher\PatternMatcher;
use PHPCD\PHPFileInfo\PHPFileInfoFactory;
use Composer\Autoload\ClassLoader;
use PHPCD\Filter\ClassFilter;

class ComposerClassmapFileRepository implements ClassInfoRepository
{
    use LoggerAwareTrait;

    private $classLoader;

    private $classmap = [];

    /** @var ClassInfoFactory **/
    private $classInfoFactory;

    /**
     * @var PHPFileInfoFactory
     */
    private $fileInfoFactory;

    /**
     * @var PatternMatcher
     */
    private $pattern_matcher;

    public function __construct(
        ClassLoader $classLoader,
        PatternMatcher $pattern_matcher,
        ClassInfoFactory $classInfoFactory,
        PHPFileInfoFactory $fileInfoFactory,
        LoggerInterface $logger
    ) {
        $this->pattern_matcher = $pattern_matcher;
        $this->classLoader = $classLoader;
        $this->classInfoFactory = $classInfoFactory;
        $this->fileInfoFactory = $fileInfoFactory;
        $this->setLogger($logger);
        $this->loadClassMap();
    }

    private function loadClassMap()
    {
        $this->classmap = $this->classLoader->getClassmap();

        return $this;
    }

    /**
     * @param ClassFilter $filter criteria to search
     * @return ClassInfoCollection
     */
    public function find(ClassFilter $filter)
    {
        $collection = $this->classInfoFactory->createClassInfoCollection();

        foreach (array_keys($this->classmap) as $classpath) {
            if ($this->pattern_matcher->match($filter->getPattern(), $classpath)) {
                $class_info = $this->get($classpath);

                if ($class_info !== null && ($filter === null || $class_info->matchesFilter($filter))) {
                    $collection->add($class_info);
                }
            }
        }

        // @todo complete also built-in declared classes
        // get_declared_classes() returns classes
        // from phpcd's (not project's) environment
        return $collection;
    }

    /**
     * Check if getting information about classes cause no problem
     * Examples:
     *  - file has syntax errors
     *  - superclass is not defined
     *
     *  @return bool
     */
    private function isValid($classpath)
    {
        $filePath = $this->classLoader->findFile($classpath);

        $fileInfo = $this->fileInfoFactory->createFileInfo($filePath);

        if ($fileInfo->hasErrors()) {
            $message = '%s %s did not pass validation and then cannot be added to class info repository. Reason:';
            $this->logger->warning(
                sprintf($message, ucfirst($fileInfo->getType()), $classpath),
                $fileInfo->getErrors()
            );
            return false;
        } else {
            return true;
        }
    }

    /**
     * Get ClassInfo based on class name
     *
     * @param string class path
     * @return ClassInfo|null
     */
    public function get($classpath)
    {
        if ($this->isValid($classpath)) {
            return $this->classInfoFactory->createClassInfo($classpath);
        }

        return null;
    }
}
