<?php

namespace PHPCD\ClassInfo;

use ArrayObject;
use IteratorAggregate;

class ClassInfoCollection implements IteratorAggregate
{
    /**
     * @var ClassInfo[]
     */
    private $collection = [];

    /**
     * @param ClassInfo $class_info
     * @return $this
     */
    public function add(ClassInfo $class_info)
    {
        $this->collection[$class_info->getName()] = $class_info;

        return $this;
    }

    public function getIterator()
    {
        return (new ArrayObject($this->collection))->getIterator();
    }

    public function isEmpty()
    {
        return empty($this->collection);
    }
}
