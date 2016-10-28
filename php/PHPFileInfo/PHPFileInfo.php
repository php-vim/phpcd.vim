<?php

namespace PHPCD\PHPFileInfo;

interface PHPFileInfo
{
    /**
     * @return string
     */
    public function getNamespace();

    /**
     * @return string
     */
    public function getClass();

    /**
     * @return string
     */
    public function getSuperclass();

    /**
     * @return array
     */
    public function getInterfaces();

    /**
     * @return string
     */
    public function getFullClassPath();

    /**
     * @return bool
     */
    public function hasAliasUsed($alias);

    /**
     * @return bool
     */
    public function hasErrors();

    /**
     * @return array
     */
    public function getErrors();

    /**
     * @return array
     */
    public function getUsedAliasesForPath($full_path);

    /**
     * @return string
     */
    public function getPathByAlias($alias);

    /**
     * @return string
     */
    public function getImports();

    /**
     * @param array     $new_class_params {
     *  @type string    $alias
     *  @type string    $full_path
     *  }
     *
     * @return array {
     *  @type string        $alias        the original or modified alias
     *  @type string|null   $full_path    null if we have no new import to do
     *  }
     */
    public function getFixForNewClassUsage(array $new_class_params);

    /**
     * @return bool
     */
    public function isClass();

    /**
     * @return bool
     */
    public function isInterface();

    /**
     * @return bool
     */
    public function isTrait();

    /**
     * Get type
     *
     * @return string|null
     */
    public function getType();
}
