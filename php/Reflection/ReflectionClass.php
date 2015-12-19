<?php

namespace Reflection;

class ReflectionClass extends \ReflectionClass
{
    /**
     * Get methods available for given class
     * depending on context
     *
     * @param bool|null $static Show static|non static|both types
     * @param bool public_only restrict the result to public methods
     * @return ReflectionMethod[]
     */
    public function getAvailableMethods($static, $public_only = false)
    {
        $methods = $this->getMethods();

        foreach ($methods as $key => $method) {
            if (false === $this->filter($method, $static, $public_only)) {
                unset($methods[$key]);
            }
        }

        return $methods;
    }

    /**
     * @return bool
     */
    private function filter(\ReflectionMethod $method, $static, $public_only)
    {
        if ($static !== null && ($method->isStatic() xor $static)) {
            return false;
        }

        if ($method->isPublic()) {
            return true;
        }

        if ($public_only) {
            return false;
        }

        if ($method->isProtected()) {
            return true;
        }

        // $method is then private
        return $method->getDeclaringClass()->getName() === $this->getName();
    }
}
