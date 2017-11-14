<?php

namespace PHPCD\Matcher;

use PHPCD\Matcher\Matcher;

/**
 * Interface for the matcher related to strings.
 */
interface StringMatcher extends Matcher
{

    /**
     * @var int Represents a case sensitive comparaison.
     */
    const CASE_SENSITIVE = 0;

    /**
     * @var int Represents a case insensitive comparaison.
     */
    const CASE_INSENSITIVE = 1;

    /**
     * Checks if a matcher is case sensitive or insensitive.
     *
     * @return bool true if the matcher is case sensitive, false otherwise.
     */
    public function isCaseSensitive();

}

