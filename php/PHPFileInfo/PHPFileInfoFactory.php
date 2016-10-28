<?php

namespace PHPCD\PHPFileInfo;

use SplFileObject;

class PHPFileInfoFactory
{
    /**
     * Create PHPFileInfo
     *
     * @return PHPFileInfo|null
     */
    public function createFileInfo($path)
    {
        if (is_readable($path)) {
            $file = new SplFileObject($path);

            return new StringBasedPHPFileInfo($file);
        }

        throw new Exception(sprintf('File %s does not exist.', $path));
    }
}
