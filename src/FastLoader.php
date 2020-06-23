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
namespace Hyperf\FastLoader;

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

    public static function init(?string $proxyFileDirPath = null, ?string $configDir = null): void
    {
        if (! $proxyFileDirPath) {
            // This dir is the default proxy file dir path of Hyperf
            $proxyFileDirPath = \BASE_PATH . '/runtime/container/proxy/';
        }

        if (! $configDir) {
            // This dir is the default proxy file dir path of Hyperf
            $configDir = \BASE_PATH . '/config/';
        }

        $loaders = spl_autoload_functions();

        // Proxy the composer class loader
        foreach ($loaders as &$loader) {
            $unregisterLoader = $loader;
            if (is_array($loader) && $loader[0] instanceof ComposerClassLoader) {
                /** @var ComposerClassLoader $composerClassLoader */
                $composerClassLoader = $loader[0];
                AnnotationRegistry::registerLoader(function ($class) use ($composerClassLoader) {
                    return (bool) $composerClassLoader->findFile($class);
                });
                $loader[0] = new static($composerClassLoader, $proxyFileDirPath, $configDir);
            }
            spl_autoload_unregister($unregisterLoader);
        }

        unset($loader);

        // Re-register the loaders
        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }

        // Initialize Lazy Loader. This will prepend LazyLoader to the top of autoload queue.
        LazyLoader::bootstrap($configDir);
    }

    protected function loadDotenv(): void
    {
        $repository = RepositoryBuilder::create()
            ->withReaders([
                new Adapter\PutenvAdapter(),
            ])
            ->withWriters([
                new Adapter\PutenvAdapter(),
            ])
            ->immutable()
            ->make();

        Dotenv::create($repository, [\BASE_PATH])->load();
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