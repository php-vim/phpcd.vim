<?php

namespace PHPCD\PatternMatcher;

class HeadPatternMatcher extends AbstractPatternMatcher implements PatternMatcher
{
    /**
     * @return bool
     */
    public function match($pattern, $string)
    {
        if (!$pattern) {
            return true;
        }

        if ($this->isCaseSensitive()) {
            return (strpos($string, $pattern) === 0);
        } else {
            return (stripos($string, $pattern) === 0);
        }
    }
}
