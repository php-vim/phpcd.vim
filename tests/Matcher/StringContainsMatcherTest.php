<?php

namespace PHPCD\Tests\Matcher;

use PHPUnit\Framework\TestCase;

use PHPCD\Matcher\StringMatcher;
use PHPCD\Matcher\StringContainsMatcher;

final class StringContainsMatcherTest extends TestCase
{

    /**
     * @test
     * @dataProvider provideMatchingStrings
     */
    function itContainsASubString(StringContainsMatcher $matcher, $haystack, $needle)
    {
        $this->assertTrue($matcher($haystack, $needle));
    }

    public function provideMatchingStrings()
    {
        $sensitiveMatcher   = new StringContainsMatcher(StringMatcher::CASE_SENSITIVE);
        $insensitiveMatcher = new StringContainsMatcher(StringMatcher::CASE_INSENSITIVE);
        $haystack           = 'aBcDef';

        return [
            "insensitive match" => [ $insensitiveMatcher, $haystack, 'bcdE' ],
            "sensitive match"   => [ $sensitiveMatcher  , $haystack, 'BcDe' ],
        ];
    }

    /**
     * @test
     * @dataProvider provideNonMatchingStrings
     */
    function itDoesNotContainASubString(StringContainsMatcher $matcher, $haystack, $needle)
    {
        $this->assertFalse($matcher($haystack, $needle));
    }

    public function provideNonMatchingStrings()
    {
        $sensitiveMatcher   = new StringContainsMatcher(StringMatcher::CASE_SENSITIVE);
        $insensitiveMatcher = new StringContainsMatcher(StringMatcher::CASE_INSENSITIVE);
        $haystack           = 'aBcDef';

        return [
            "insensitive match" => [ $insensitiveMatcher, $haystack, 'bcsdE' ],
            "sensitive match"   => [ $sensitiveMatcher  , $haystack, 'Bcde' ] ,
        ];
    }

}

