<?php

namespace PHPCD\Matcher;

use PHPCD\ClassFinder\ClassFinder;
use PHPCD\Matcher\SimpleClassNameMatcher;
use PHPCD\Matcher\StringContainsMatcher;
use PHPCD\Matcher\StringStartsWithMatcher;

/**
 * Functor to compare classes fully qualified names with a pattern.
 */
final class SimpleClassNameMatcher implements ClassMatcher
{

    /**
     * @var StringContainsMatcher The matcher used to check if the class name contains the pattern.
     */
    private $containsMatcher;

    /**
     * @var StringStartsWithMatcher The matcher used to check if the class name starts with the pattern.
     */
    private $startsWithMatcher;

    /**
     * Initializes the matcher.
     *
     * @param StringContainsMatcher   $containsMatcher   The matcher used to check if the class name contains the pattern.
     * @param StringStartsWithMatcher $startsWithMatcher The matcher used to check if the class name starts with the pattern.
     */
    public function __construct(StringContainsMatcher $containsMatcher, StringStartsWithMatcher $startsWithMatcher)
    {
        $this->containsMatcher   = $containsMatcher;
        $this->startsWithMatcher = $startsWithMatcher;
    }

    /**
     * Checks if a fully qualified class name contains a pattern.
     * If the pattern starts with a "\" then the fully qualified name
     * must start with the pattern.
     *
     * @param string $fullyQualifiedName The fully qualified name of the class.
     * @param mixed  $pattern            The pattern to use.
     *
     * @return bool true if the class is a match, false otherwise.
     */
    public function __invoke($fullyQualifiedName, $pattern)
    {
        /**
         * Even if it's a public method I don't assert that the parameters
         * are indeed strings because it will be done by the matcher.
         */
        $matcher = call_user_func($this->startsWithMatcher, $pattern, ClassFinder::NAMESPACE_SEPARATOR)
            ? 'startsWithMatcher'
            : 'containsMatcher';

        return call_user_func($this->{$matcher}, $fullyQualifiedName, $pattern);
    }

}

