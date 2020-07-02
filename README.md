# hyperf-fastloader

Make hyperf2.0 load faster by only caching file anotations in the vendor directory.

## install

    ```bash
    composer require hectorqin/hyperf-fastloader
    ```

## usage

This component only take effect when the configure `scan_cache_vendor_only` is turned on and the configure `scan_cacheable` is turned off.

- Init composer classMap (Very important! If you delete PHP class file in the app dirctory or add/remove composer packages, you need to run this command again).

    ```bash
    composer dump-autoload -o
    ```

- Replace `Hyperf\Di\ClassLoader::init();` with `Hector\FastLoader\ClassLoader::init();`

- Add the configure below to the config file `config/config.php`

    ```php
    // is only caching file anotations in the vendor directory
    'scan_cache_vendor_only'     => env('SCAN_CACHE_VENDOR_ONLY', false),
    ```

- Add the configure below to the env file `.env`

    ```env
    SCAN_CACHE_VENDOR_ONLY=true
    ```

- Turn off `scan_cacheable`
