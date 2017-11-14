<?php

namespace PHPCD\Matcher;

use Assert\Assertion;

use PHPCD\Matcher\StringMatcher;

/**
 * Functor to test if a string contains another one.
 */
final class StringContainsMatcher implements StringMatcher
{

    use StringMatcherTrait;

    /**
     * Checks if a string contains another one.
     *
     * @param string $haystack The string to look into.
     * @param string $needle   The string to look for.
     *
     * @return bool true if $needle is in $haystack, false otherwise.
     */
    public function __invoke($haystack, $needle)
    {
        try {
            Assertion::string($haystack);
            Assertion::string($needle);

            $function = $this->isCaseSensitive() ? 'strstr' : 'stristr';

            return false !== call_user_func($function, (string) $haystack, (string) $needle);
        } catch(\InvalidArgumentException $exception) {
            return false;
        }
    }

}

