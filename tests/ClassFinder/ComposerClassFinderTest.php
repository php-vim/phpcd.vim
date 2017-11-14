<?php

namespace PHPCD\Tests\ClassFinder;

use PHPUnit\Framework\TestCase;

use Psr\Log\LoggerInterface;
use PHPCD\ClassFinder\ComposerClassFinder;
use PHPCD\Matcher\ClassMatcher;

/**
 * Don't forget to run composer dump-autoload in the tests/fixtures directory
 */
final class ComposerClassFinderTest extends TestCase
{

    /**
     * @var ComposerClassFinder
     */
    private $finder;

    public function setUp()
    {
        $logger       = $this->createMock(LoggerInterface::class);
        $autoloadPath = dirname(__DIR__) . '/fixtures/vendor/autoload.php';
        $this->finder = new ComposerClassFinder($logger, $autoloadPath);
    }

    /**
     * @test
     */
    public function itFindsAClass()
    {
        $pattern = 'test';
        $matcher = $this->createMock(ClassMatcher::class);

        $files = [ // List of all the tests files to find
			'CFile',
			'PHPCD\A\Alpha',
			'Special\Tools\Root',
			'Test\tclass',
			'Psr4\psr4',
			'Psr4\Tmp\Other',
			'PHPCD\B\C\ExpectPublicVariable',
			'PHPCD\B\C\ExpectClassConstantOnly',
			'Test\Foo',
        ];
        $filesFound = [];

        /**
         * The function will be call for every classes available in the project.
         * Inluding PHP built in classes, so the matcher will not be call
         * exactly count($files) times but at least this nummber.
         * After what we juste need to remember all classes provided to the matcher,
         * in order to verify that we have loaded them all.
         */
        $matcher->expects($this->atLeast(count($files)))
            ->method('__invoke')
            ->with($this->callback(function ($fullyQualifiedName) use($files, &$filesFound) {

                if (in_array($fullyQualifiedName, $files)) {
                    $filesFound[] = $fullyQualifiedName;
                }

                return true;
            }));

        $this->finder->find($pattern, $matcher);

        $this->assertCount(count($files), $filesFound);      // Ensure that we have the right number of classes
        $this->assertEmpty(array_diff($files, $filesFound)); // And then be sure that they are the ones expected.
    }

}

