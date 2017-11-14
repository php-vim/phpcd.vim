<?php

namespace PHPCD\Tests\Matcher;

use PHPUnit\Framework\TestCase;

use PHPCD\Matcher\Matcher;

final class MatcherTest extends TestCase
{

    /**
     * @test
     */
    function itInvokesAMatcher()
    {
        $left    = 'left';
        $right   = 'right';
        $matcher = $this->createMock(Matcher::class);

        $matcher->expects($this->once())
            ->method('__invoke')
            ->with($left, $right);

        $matcher($left, $right);
    }

}

