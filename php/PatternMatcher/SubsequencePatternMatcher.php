<?php

namespace PHPCD\PatternMatcher;

class SubsequencePatternMatcher extends AbstractPatternMatcher implements PatternMatcher
{
    /**
     * @return bool
     */
    public function match($pattern, $string)
    {
        if (!$pattern) {
            return true;
        }

        // @TODO Quote characters that may be treat not literally
        $modifiers = $this->isCaseSensitive() ? '' : 'i';
        $regex = sprintf('/%s/%s', implode('.*', str_split($pattern)), $modifiers);

        return (bool)preg_match($regex, $string);

        return false;
    }
}
