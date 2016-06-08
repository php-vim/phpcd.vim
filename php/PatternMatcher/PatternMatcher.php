<?php

namespace PHPCD\PatternMatcher;

interface PatternMatcher
{
    /**
     * @return bool
     */
    public function match($pattern, $string);

    /**
     * @return bool
     */
    public function isCaseSensitive();
}
