<?php

declare(strict_types=1);

namespace SmartPHPUnitTestGenerator\Util;

class ReturnTypeChecker
{
    /**
     * @param \ReflectionMethod $method
     *
     * @return bool
     */
    public static function isReturnTypeVoid(\ReflectionMethod $method): bool
    {
        return $method->getReturnType() === 'void';
    }
}
