# hyperf-fastloader

Make hyperf2.0 load faster by only caching vendor anotation.

## install

    ```bash
    composer require hectorqin/hyperf-fastloader
    ```

## usage

Replace `Hyperf\Di\ClassLoader::init();` with `Hyperf\FastLoader::init();`
