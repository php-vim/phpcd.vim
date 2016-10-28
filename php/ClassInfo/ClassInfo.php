<?php

namespace PHPCD\ClassInfo;

use PHPCD\Filter\ClassFilter;

interface ClassInfo
{
    /**
     * @return bool
     */
    public function isAbstract();

    /**
     * @return bool
     */
    public function isFinal();

    /**
     * @return bool
     */
    public function isTrait();

    /**
     * @return bool
     */
    public function isInstantiable();

    /**
     * @return bool
     */
    public function isInterface();

    /**
     * @return string
     */
    public function getShortName();

    /**
     * @return string
     */
    public function getDocComment();

    public function getMatchingConstants($name_pattern);

    /**
     * Get methods available for given class
     * depending on context
     *
     * @param bool|null $static Show static|non static|both types
     * @param bool public_only restrict the result to public methods
     * @return \ReflectionMethod[]
     */
    public function getAvailableMethods($static, $public_only = false, $name_pattern = null);

    /**
     * Get properties available for given class
     * depending on context
     *
     * @param bool|null $static Show static|non static|both types
     * @param bool public_only restrict the result to public properties
     * @return \ReflectionProperty[]
     */
    public function getAvailableProperties($static, $public_only = false, $name_pattern = null);

    /**
     * @return bool
     */
    public function matchesFilter(ClassFilter $classFilter);

    /**
     * @return string
     */
    public function getName();
}
