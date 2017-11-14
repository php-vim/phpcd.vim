<?php

namespace PHPCD\Tests\Matcher;

use PHPUnit\Framework\TestCase;

use PHPCD\Matcher\StringMatcher;
use PHPCD\Matcher\StringContainsMatcher;
use PHPCD\Matcher\StringStartsWithMatcher;
use PHPCD\Matcher\SimpleClassNameMatcher;

final class SimpleClassNameMatcherTest extends TestCase
{

    /**
     * @test
     * @dataProvider provideMatchingClassNames
     */
    function itMatchesAClassName(SimpleClassNameMatcher $matcher, $fullyQualifiedName, $pattern)
    {
        $this->assertTrue($matcher($fullyQualifiedName, $pattern));
    }

    public function provideMatchingClassNames()
    {
        $containsMatcher    = new StringContainsMatcher(StringMatcher::CASE_INSENSITIVE);
        $startsWithMatcher  = new StringStartsWithMatcher(StringMatcher::CASE_INSENSITIVE);
        $matcher            = new SimpleClassNameMatcher($containsMatcher, $startsWithMatcher);
        $fullyQualifiedName = '\PHPCD\Matcher\SimpleClassNameMatcher';

        return [
            "partial look up" => [ $matcher, $fullyQualifiedName, 'Matcher\SimpleClassName' ],
            "starting with"   => [ $matcher, $fullyQualifiedName, '\PHPCD\Matcher' ],
        ];
    }

    /**
     * @test
     * @dataProvider provideNonMatchingClassNames
     */
    function itDoesNotMatchAClassName(SimpleClassNameMatcher $matcher, $fullyQualifiedName, $pattern)
    {
        $this->assertFalse($matcher($fullyQualifiedName, $pattern));
    }

    public function provideNonMatchingClassNames()
    {
        $containsMatcher    = new StringContainsMatcher(StringMatcher::CASE_INSENSITIVE);
        $startsWithMatcher  = new StringStartsWithMatcher(StringMatcher::CASE_INSENSITIVE);
        $matcher            = new SimpleClassNameMatcher($containsMatcher, $startsWithMatcher);
        $fullyQualifiedName = '\PHPCD\Matcher\SimpleClassNameMatcher';

        return [
            "partial look up" => [ $matcher, $fullyQualifiedName, 'Matcher\String' ],
            "starting with"   => [ $matcher, $fullyQualifiedName, '\Matcher\SimpleClassName' ],
        ];
    }

}

