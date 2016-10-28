<?php

namespace PHPCD\ClassInfo;

use PHPCD\PatternMatcher\PatternMatcher;
use PHPCD\Filter\ClassFilter;

class ReflectionClass extends \ReflectionClass implements ClassInfo
{
    /**
     * @var PatternMatcher
     */
    private $pattern_matcher;

    /**
     * @param string|object $class
     * @param PatternMatcher $pattern_matcher
     */
    public function __construct($class, PatternMatcher $pattern_matcher)
    {
        parent::__construct($class);

        $this->pattern_matcher = $pattern_matcher;
    }

    public function getMatchingConstants($name_pattern)
    {
        $constants = $this->getConstants();

        foreach (array_keys($constants) as $constant) {
            if ($name_pattern && !$this->pattern_matcher->match($name_pattern, $constant)) {
                unset($constants[$constant]);
            }
        }

        return $constants;
    }

    /**
     * Get methods available for given class
     * depending on context
     *
     * @param bool|null $static Show static|non static|both types
     * @param bool public_only restrict the result to public methods
     * @return \ReflectionMethod[]
     */
    public function getAvailableMethods($static, $public_only = false, $name_pattern = null)
    {
        $methods = $this->getMethods();

        foreach ($methods as $key => $method) {
            if (false === $this->filterMethod($method, $static, $public_only, $name_pattern)) {
                unset($methods[$key]);
            }
        }

        return $methods;
    }

    /**
     * Get properties available for given class
     * depending on context
     *
     * @param bool|null $static Show static|non static|both types
     * @param bool public_only restrict the result to public properties
     * @return \ReflectionProperty[]
     */
    public function getAvailableProperties($static, $public_only = false, $name_pattern = null)
    {
        $properties = $this->getProperties();

        foreach ($properties as $key => $property) {
            if (false === $this->filterMethod($property, $static, $public_only, $name_pattern)) {
                unset($properties[$key]);
            }
        }

        return $properties;
    }

    /**
     * @param \ReflectionMethod|\ReflectionProperty $element
     * @return bool
     */
    private function filterMethod($element, $static, $public_only, $name_pattern = null)
    {
        if ($name_pattern && !$this->pattern_matcher->match($name_pattern, $element->getName())) {
            return false;
        }

        if (!$element instanceof \ReflectionMethod && !$element instanceof \ReflectionProperty) {
            throw new \InvalidArgumentException(
                'Parameter must be a member of ReflectionMethod or ReflectionProperty class'
            );
        }

        if ($static !== null && ($element->isStatic() xor $static)) {
            return false;
        }

        if ($element->isPublic()) {
            return true;
        }

        if ($public_only) {
            return false;
        }

        if ($element->isProtected()) {
            return true;
        }

        // $element is then private
        return $element->getDeclaringClass()->getName() === $this->getName();
    }

    public function isAbstractClass()
    {
        return $this->isAbstract() && $this->isInstantiable();
    }

    public function matchesFilter(ClassFilter $classFilter)
    {
        $methods = $classFilter->getCriteriaNames();

        foreach ($methods as $method) {
            if ($classFilter->$method() !== null) {
                if ($classFilter->$method() !== $this->$method()) {
                    return false;
                }
            }
        }

        return true;
    }
}
