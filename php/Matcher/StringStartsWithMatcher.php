<?php

namespace PHPCD\Matcher;

use Assert\Assertion;

use PHPCD\Matcher\StringMatcher;

/**
 * Functor to test if a string starts with another one.
 */
final class StringStartsWithMatcher implements StringMatcher
{

    use StringMatcherTrait;

    /**
     * Checks if a string strats with another one.
     *
     * @param string $haystack The string to look into.
     * @param string $needle   The string to look for.
     *
     * @return bool true if $haystack starts with $needle, false otherwise.
     */
    public function __invoke($haystack, $needle)
    {
        try {
            Assertion::string($haystack);
            Assertion::string($needle);

            $function = $this->isCaseSensitive() ? 'strpos' : 'stripos';

            return 0 === call_user_func($function, (string) $haystack, (string) $needle);
        } catch(\InvalidArgumentException $exception) {
            return false;
        }
    }

}

