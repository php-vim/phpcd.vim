<?php

namespace PHPCD\Matcher;

/**
 * Interface for some comparaison functors.
 */
interface Matcher
{

    /**
     * Compare two operands.
     *
     * @param mixed $left  Left operand.
     * @param mixed $right Right operand.
     *
     * @return bool true si both operands are a match, false otherwise.
     */
    public function __invoke($left, $right);

}

