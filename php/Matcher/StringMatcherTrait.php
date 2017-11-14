<?php

namespace PHPCD\Matcher;

use Assert\Assertion;

use PHPCD\Matcher\StringMatcher;

/**
 * Trait for a string matcher functor.
 */
trait StringMatcherTrait
{

    /**
     * @var bool true if the comparaison must be case sensitive, false otherwise.
     */
    private $caseSensitive;

    /**
     * Initializes a string matcher.
     *
     * @param int $caseSensitive (optional) StringMatcher::CASE_SENSITIVE for a case sensitive search, StringMatcher::CASE_INSENSITIVE otherwise. Default to : StringMatcher::CASE_SENSITIVE.
     */
    public function __construct($caseSensitive = StringMatcher::CASE_SENSITIVE)
    {
        Assertion::inArray($caseSensitive, [
            StringMatcher::CASE_SENSITIVE,
            StringMatcher::CASE_INSENSITIVE
        ]);

        $this->caseSensitive = $caseSensitive;
    }

    /**
     * {@inheritdoc}
     */
    public function isCaseSensitive()
    {
        return StringMatcher::CASE_SENSITIVE === $this->caseSensitive;
    }

}

