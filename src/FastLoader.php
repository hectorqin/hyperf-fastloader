<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hector\FastLoader;

use Hyperf\Di\ClassLoader as BaseClassLoader;
use Composer\Autoload\ClassLoader as ComposerClassLoader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Dotenv\Dotenv;
use Dotenv\Repository\Adapter;
use Dotenv\Repository\RepositoryBuilder;
use Hyperf\Di\Annotation\ScanConfig;
use Hyperf\Di\Annotation\Scanner;
use Hyperf\Di\Aop\ProxyManager;
use Hyperf\Di\LazyLoader\LazyLoader;

class FastLoader extends BaseClassLoader
{
    public function __construct(ComposerClassLoader $classLoader, string $proxyFileDir, string $configDir)
    {
        $this->setComposerClassLoader($classLoader);
        if (file_exists(\BASE_PATH . '/.env')) {
            $this->loadDotenv();
        }

        $scanConfig = ScanConfig::instance($configDir);
        $config = $this->extendScanConfig($scanConfig, $configDir);

        // Scan by ScanConfig to generate the reflection class map
        $scanner = new Scanner($this, $config);
        $classLoader->addClassMap($config->getClassMap());
        $reflectionClassMap = $scanner->scan();
        // Get the class map of Composer loader
        $composerLoaderClassMap = $this->getComposerClassLoader()->getClassMap();
        $proxyManager = new ProxyManager($reflectionClassMap, $composerLoaderClassMap, $proxyFileDir);
        $this->proxies = $proxyManager->getProxies();
    }

    protected function extendScanConfig(ScanConfig $scanConfig, string $configDir)
    {
        if ($this->isScanCacheVendorOnly($configDir)) {
            $func = function() {
                $filteredPath = [];
                $vendorPath = \BASE_PATH . '/vendor';
                foreach ($this->paths as $path) {
                    if (\strpos($path, $vendorPath) !== 0) {
                        $filteredPath[] = $path;
                    }
                }
                $this->paths = $filteredPath;
            };
            call_user_func_array($func->bindTo($scanConfig, ScanConfig::class), []);
        }
        return $scanConfig;
    }

    protected function isScanCacheVendorOnly(string $configDir): bool
    {
        $cacheFile = \BASE_PATH . '/runtime/container/collectors.cache';
        if (\file_exists($cacheFile)) {
            $configContent = include $configDir . '/config.php';
            return value($configContent['scan_cache_vendor_only']);
        }
        return false;
    }
}