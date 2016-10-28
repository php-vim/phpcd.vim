<?php

namespace PHPCD\PatternMatcher;

abstract class AbstractPatternMatcher implements PatternMatcher
{
    private $case_sensitive = false;

    public function __construct($case_sensitive = null)
    {
        if ($case_sensitive === true) {
            $this->case_sensitive = $case_sensitive;
        }
    }

    /**
     * @return bool
     */
    public function isCaseSensitive()
    {
        return $this->case_sensitive;
    }
}
