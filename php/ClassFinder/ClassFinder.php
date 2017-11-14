<?php

namespace PHPCD\ClassFinder;

use PHPCD\Matcher\ClassMatcher;

/**
 * Finds the classes used by a project
 */
interface ClassFinder
{

    /**
     * @var string The namespace separator.
     */
    CONST NAMESPACE_SEPARATOR = '\\';

    /**
     * Finds the classes used by the project.
     * Looks for every autoloading defined in the composer.json file until
     * finding a match.
     *
     * @param mixed        $pattern The pattern to provide to the matcher.
     * @param ClassMatcher $matcher The object in charge of finding the corresponding classes.
     *
     * @return array The list of all fully qualified class names matching the pattern.
     */
    public function find($pattern, ClassMatcher $matcher);

}

