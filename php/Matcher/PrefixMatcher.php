<?php
namespace PHPCD\Matcher;

class PrefixMatcher implements Matcher
{
    public function match($pattern, $target)
    {
        if (!$pattern) {
            return true;
        }

        return preg_match('/^' . preg_quote($pattern, '/') . '/i', $target);
    }
}
