<?php

use PHPUnit\Framework\TestCase;
use PHPCD\PatternMatcher\SubsequencePatternMatcher;
use Psr\Log\LoggerInterface;
use PHPCD\PHPFileInfo\PHPFileInfoFactory;
use PHPCD\ClassInfo\ClassInfoFactory;
use PHPCD\ClassInfo\ComposerClassmapFileRepository;

class ComposerClassmapFileRepositoryTest extends TestCase
{
    public function testXxx()
    {
        $pattern_matcher = $this->createMock(SubsequencePatternMatcher::class);
        $pattern_matcher->method('match')->will($this->returnArgument(true));

        $project_root =  dirname(__FILE__).'/../Fixtures/ClassInfoRepository/Fakeroot/ExampleWithNonExistingSuperclass';

        $classInfoFactory = new ClassInfoFactory($pattern_matcher);

        $fileInfoFactory = $this->createMock(PHPFileInfoFactory::class);
        $logger = $this->createMock(LoggerInterface::class);

        $repository = new ComposerClassmapFileRepository(
            $project_root,
            $pattern_matcher,
            $classInfoFactory,
            $fileInfoFactory,
            $logger
        );

        $repository->find('PHPCD\\Fixtures\\StringBasedPHPFileInfo\\A');
    }
}
