<?php

namespace PHPCD\PHPFileInfo;

class StringBasedPHPFileInfoTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return StringBasedPHPFileInfo
     */
    private function getFileInfo($file_path)
    {
        $factory = new PHPFileInfoFactory;

        return $factory->createFileInfo($file_path);
    }

    public function newUsedClassInputAndFixProvider()
    {
        return [
            [
                // Alias Image is the same as the current class so suggest its change
                // The path is not used, suggest to add it
                'Fixtures/ClassNamesAndAliases/Models/Image.php',
                ['alias' => 'Image', 'full_path' => '\PHPCD\Fixtures\ClassNamesAndAliases\Repositories\Image'],
                ['Image2' => ['alias' => 'Image2', 'full_path' => 'PHPCD\Fixtures\ClassNamesAndAliases\Repositories\Image']]
            ],
            [
                // Alias Image is already used, the path is not used
                // Suggest both to change alias and to add the path to imports
                'Fixtures/ClassNamesAndAliases/Services/Image.php',
                ['alias' => 'Image', 'full_path' => '\PHPCD\Fixtures\ClassNamesAndAliases\Models\Image'],
                ['Image2' => ['alias' => 'Image2', 'full_path' => 'PHPCD\Fixtures\ClassNamesAndAliases\Models\Image']]
            ],
            [
                // inserted alias may be used (expect null),
                //  the path does not exist yet (expect that it will be suggested to adding)
                'Fixtures/ClassNamesAndAliases/Services/Image.php',
                ['alias' => 'Image3', 'full_path' => '\PHPCD\Fixtures\ClassNamesAndAliases\Models\Image'],
                [null => ['alias' => null, 'full_path' => 'PHPCD\Fixtures\ClassNamesAndAliases\Models\Image']]
            ],
            [
                // the path is already used but with another alias
                // so suggest change the alias only
                'Fixtures/ClassNamesAndAliases/Services/Image.php',
                ['alias' => 'Category', 'full_path' => '\PHPCD\Fixtures\ClassNamesAndAliases\Repositories\Category'],
                ['Cat' => ['alias' => 'Cat', 'full_path' => null]]
            ],
            [
                // When the same path is aliased more than once, but one of alias is the same as inserted
                // then do nothing
                'Fixtures/ClassNamesAndAliases/Repositories/Category.php',
                ['alias' => 'Image', 'full_path' => '\PHPCD\Fixtures\ClassNamesAndAliases\Repositories\Image'],
                [
                    null => ['alias' => null, 'full_path' => null]
                ]
            ],
            [
                'Fixtures/ClassNamesAndAliases/Repositories/Category.php',
                ['alias' => 'PHPUnit_Framework_TestCase', 'full_path' => '\PHPUnit_Framework_TestCase'],
                [
                    null => ['alias' => null, 'full_path' => 'PHPUnit_Framework_TestCase']
                ]
            ],
            [
                // When the same path is aliased more than once, but differently than the inserted alias
                // give user the choice from the list of used aliases
                'Fixtures/ClassNamesAndAliases/Models/Gallery.php',
                ['alias' => 'Image', 'full_path' => '\PHPCD\Fixtures\ClassNamesAndAliases\Models\Image'],
                [
                    'Photo' => ['alias' => 'Photo', 'full_path' => null],
                    'Picture' => ['alias' => 'Picture', 'full_path' => null]
                ]
            ]
        ];
    }

    /**
     * @dataProvider newUsedClassInputAndFixProvider
     */
    public function testGetFixForNewClassUsage(
        $where,
        $input_class_info,
        $expected_suggestions
    ) {
        $file_path = $this->getAbsoluteFilePath($where);

        $file_info = $this->getFileInfo($file_path);

        $fix = $file_info->getFixForNewClassUsage($input_class_info);

        $this->assertEquals(
            count($expected_suggestions),
            count($fix),
            'Count of fixes differs from expected.'
        );

        foreach ($fix as $suggestion) {
            $new_alias = $suggestion['alias'];

            $this->assertTrue(array_key_exists($new_alias, $expected_suggestions), 'No such alias');
            $this->assertEquals(
                $new_alias,
                $expected_suggestions[$new_alias]['alias'],
                'Aliases are not equal.'
            );
            $this->assertEquals(
                $suggestion['full_path'],
                $expected_suggestions[$new_alias]['full_path'],
                'Paths are not equal.'
            );
        }
    }

    public function testScanFileWithoutErrors()
    {
        $path = 'Fixtures/StringBasedPHPFileInfo/ExampleWithoutErrors.php';

        $fileInfo = $this->getFileInfo($this->getAbsoluteFilePath($path));

        $this->assertInstanceOf('PHPCD\PHPFileInfo\PHPFileInfo', $fileInfo);
        $this->assertEquals('PHPCD\Fixtures\StringBasedPHPFileInfo', $fileInfo->getNamespace());
        $this->assertEquals('ExampleWithoutErrors', $fileInfo->getClass());
        $this->assertEquals('Cat', $fileInfo->getSuperclass());

        $interfaces = $fileInfo->getInterfaces();
        $this->assertFalse($fileInfo->hasErrors());
    }

    /**
     * @test
     */
    public function scanFileWithNonExistingInterface()
    {
        $path = 'Fixtures/StringBasedPHPFileInfo/ExampleWithNonExistingInterfaces.php';

        $fileInfo = $this->getFileInfo($this->getAbsoluteFilePath($path));

        $this->assertInstanceOf('PHPCD\PHPFileInfo\PHPFileInfo', $fileInfo);
        $this->assertEquals('PHPCD\Fixtures\StringBasedPHPFileInfo', $fileInfo->getNamespace());
        $this->assertEquals('ExampleWithNonExistingInterfaces', $fileInfo->getClass());
        $this->assertNull($fileInfo->getSuperclass());

        $interfaces = $fileInfo->getInterfaces();
        $this->assertEquals(['I1', 'I2'], $interfaces);

        $this->assertTrue($fileInfo->hasErrors());
        $errors = $fileInfo->getErrors();
        $this->assertEquals('Interface PHPCD\Fixtures\StringBasedPHPFileInfo\I1 does not exist.', current($errors));

    }

    /**
     * @markTestSuiteSkipped
     */
    public function testScanFileWithSyntaxErrors()
    {
        $path = 'Fixtures/StringBasedPHPFileInfo/ExampleWithSyntaxError.php';

        $fileInfo = $this->getFileInfo($this->getAbsoluteFilePath($path));

        $this->assertInstanceOf('PHPCD\PHPFileInfo\PHPFileInfo', $fileInfo);
        $this->assertTrue($fileInfo->hasErrors());
    }

    public function testScanFileWithNonexistingSuperclass()
    {
        $path = 'Fixtures/StringBasedPHPFileInfo/ExampleWithNonExistingSuperclass.php';

        $fileInfo = $this->getFileInfo($this->getAbsoluteFilePath($path));

        $this->assertInstanceOf('PHPCD\PHPFileInfo\PHPFileInfo', $fileInfo);
        $this->assertTrue($fileInfo->hasErrors());

        $errors = $fileInfo->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('Class A\\X1234 does not exist.', $errors[0]);
    }

    public function testScanFileWithNonexistingInterface()
    {
        $path = 'Fixtures/StringBasedPHPFileInfo/ExampleWithNonExistingInterface.php';

        $fileInfo = $this->getFileInfo($this->getAbsoluteFilePath($path));

        $this->assertInstanceOf('PHPCD\PHPFileInfo\PHPFileInfo', $fileInfo);
        $this->assertTrue($fileInfo->hasErrors());

        $errors = $fileInfo->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('Interface A\\X456 does not exist.', $errors[0]);
    }

    private function getAbsoluteFilePath($relativePath)
    {
        return sprintf("%s/%s/%s", realpath('.'), 'tests/php', $relativePath);
    }

    public function testScanSuperInterface()
    {
        $path = 'Fixtures/StringBasedPHPFileInfo/Subinterface.php';

        $fileInfo = $this->getFileInfo($this->getAbsoluteFilePath($path));

        $this->assertInstanceOf('PHPCD\PHPFileInfo\PHPFileInfo', $fileInfo);
        $this->assertFalse($fileInfo->hasErrors(), implode(',', $fileInfo->getErrors()));
        $this->assertTrue($fileInfo->isInterface());
    }

    public function testScanClassThatImplementsQualifiedName()
    {
        $path = 'Fixtures/StringBasedPHPFileInfo/ClassImplementsQualifiedName.php';

        $fileInfo = $this->getFileInfo($this->getAbsoluteFilePath($path));

        $this->assertInstanceOf('PHPCD\PHPFileInfo\PHPFileInfo', $fileInfo);

        $this->assertFalse($fileInfo->hasErrors(), implode(',', $fileInfo->getErrors()));

        $this->assertTrue($fileInfo->isClass());
    }

    /**
     * @test
     */
    public function scanClassWithAliasedNamespace()
    {
        $path = 'Fixtures/StringBasedPHPFileInfo/ExampleWithAliasedNamespace.php';

        $fileInfo = $this->getFileInfo($this->getAbsoluteFilePath($path));

        $this->assertInstanceOf('PHPCD\PHPFileInfo\PHPFileInfo', $fileInfo);

        $this->assertFalse($fileInfo->hasErrors(), implode(',', $fileInfo->getErrors()));
    }
}
