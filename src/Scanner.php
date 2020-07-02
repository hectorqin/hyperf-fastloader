<?php


declare(strict_types=1);

namespace Hector\FastLoader;

use Roave\BetterReflection\Reflection\ReflectionClass;
use Hyperf\Di\Annotation\Scanner as BaseScanner;

class Scanner extends BaseScanner
{
    /**
     * @param ReflectionClass[] $reflections
     */
    protected function clearRemovedClasses(array $collectors, array $reflections): void
    {
        if ($this->scanConfig->isScanCacheVendorOnly) {
            return;
        }

        parent::clearRemovedClasses($collectors, $reflections);
    }
}