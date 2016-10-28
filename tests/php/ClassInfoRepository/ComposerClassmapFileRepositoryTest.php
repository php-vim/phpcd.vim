<?php

namespace PHPCD\ClassInfo;

use PHPUnit\Framework\TestCase;
use PHPCD\PatternMatcher\PatternMatcher;
use Composer\Autoload\ClassLoader;
use Psr\Log\LoggerInterface;
use PHPCD\PHPFileInfo\PHPFileInfoFactory;
use PHPCD\ClassInfo\ClassInfoFactory;
use PHPCD\Filter\ClassFilter;
use PHPCD\ClassInfo\ComposerClassmapFileRepository;
use PHPCD\ClassInfo\ClassInfoCollection;
use PHPCD\PHPFileInfo\PHPFileInfo;
// Mockery has IMHO more clear syntax
// and more clear error messages than PHPUnit's mocks
use Mockery;

class ComposerClassmapFileRepositoryTest extends TestCase
{
    /**
     * Try to find class info of class that is invalid
     */
    public function testTryToFindNotValidClass()
    {
        $pattern_matcher = Mockery::mock(PatternMatcher::class);
        $pattern_matcher->shouldReceive('match')->andReturn(true);

        $classLoader = Mockery::mock(ClassLoader::class);

        $classMap = [
            'Any\\Class' => 'ExampleFile.php'
        ];

        $classLoader->shouldReceive('findFile')->with(key($classMap))->once()->andReturn(current($classMap));
        $classLoader->shouldReceive('getClassMap')->andReturn($classMap);

        $classInfoFactory = new ClassInfoFactory($pattern_matcher);

        $fileInfo = Mockery::mock(PHPFileInfo::class);
        $fileInfo->shouldReceive('hasErrors')->once()->andReturn(true);
        $fileInfo->shouldReceive('getErrors')->once()->andReturn(['Some syntax error']);
        $fileInfo->shouldReceive('getType')->once()->andReturn('class');

        $fileInfoFactory = Mockery::mock(PHPFileInfoFactory::class);
        $fileInfoFactory->shouldReceive('createFileInfo')->once()->andReturn($fileInfo);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->andReturnNull();

        $repository = new ComposerClassmapFileRepository(
            $classLoader,
            $pattern_matcher,
            $classInfoFactory,
            $fileInfoFactory,
            $logger
        );

        $this->assertInstanceOf(ComposerClassmapFileRepository::class, $repository);

        $collection = $repository->find(new ClassFilter([], key($classMap)));

        $this->assertInstanceOf(ClassInfoCollection::class, $collection);

        $this->assertTrue($collection->isEmpty());
    }
}
