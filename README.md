# PhpCodeAnalyzer
PhpCodeAnalyzer finds usage of different non-built-in extensions in your php code.
This tool helps you understand how transportable your code between php installations is.

[![Composer package](http://composer.network/badge/wapmorgan/php-code-analyzer)](https://packagist.org/packages/wapmorgan/php-code-analyzer)
[![Latest Stable Version](https://poser.pugx.org/wapmorgan/php-code-analyzer/v/stable)](https://packagist.org/packages/wapmorgan/php-code-analyzer)
[![Total Downloads](https://poser.pugx.org/wapmorgan/php-code-analyzer/downloads)](https://packagist.org/packages/wapmorgan/php-code-analyzer)
[![License](https://poser.pugx.org/wapmorgan/php-code-analyzer/license)](https://packagist.org/packages/wapmorgan/php-code-analyzer)

# TOC
- [Example of usage](#example-of-usage)
- [Help](#help)
- [Installation](#installation)

# Example of usage
To scan your files or folder launch `phpca` and pass file or directory names.
``` sh
> phpca ..\HttpServer
Scanning ..\HttpServer ...
[spl] Function "spl_autoload_register" used in file ..\HttpServer/vendor/composer/ClassLoader.php[258]
[spl] Function "spl_autoload_unregister" used in file ..\HttpServer/vendor/composer/ClassLoader.php[266]
[spl] Function "spl_autoload_register" used in file ..\HttpServer/vendor/composer/autoload_real.php[22]
[spl] Function "spl_autoload_unregister" used in file ..\HttpServer/vendor/composer/autoload_real.php[24]

Used non-built-in extensions in your code:
- [spl] Standard PHP Library (SPL). This extension is bundled with php since PHP 5.0.0. Extension is available in pecl: spl.
```

You can skip progress with `--no-progress` option:
``` sh
> phpca --no-progress ..\yii-1.1.16.bca042\framework\caching
Scanning ..\yii-1.1.16.bca042\framework\caching ...

Used non-built-in extensions in your code:
- [apc] Alternative PHP Cache. Extension is available in pecl: apc.
- [wincache] Windows Cache for PHP. Extension is available in pecl: wincache.

```

Also, you can keep only progress with `--no-report` option:
``` sh
> phpca --no-report ..\yii-1.1.16.bca042\framework\caching
Scanning ..\yii-1.1.16.bca042\framework\caching ...
[apc] Function "apc_fetch" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[46]
[apc] Function "apc_fetch" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[56]
[apc] Function "apc_store" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[70]
[apc] Function "apc_add" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[84]
[apc] Function "apc_delete" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[95]
[apc] Function "apc_clear_cache" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[107]
[apc] Function "apc_clear_cache" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[109]
[wincache] Function "wincache_ucache_get" used in file ..\yii-1.1.16.bca042\framework\caching/CWinCache.php[46]
[wincache] Function "wincache_ucache_get" used in file ..\yii-1.1.16.bca042\framework\caching/CWinCache.php[56]
[wincache] Function "wincache_ucache_set" used in file ..\yii-1.1.16.bca042\framework\caching/CWinCache.php[70]
[wincache] Function "wincache_ucache_add" used in file ..\yii-1.1.16.bca042\framework\caching/CWinCache.php[84]
[wincache] Function "wincache_ucache_delete" used in file ..\yii-1.1.16.bca042\framework\caching/CWinCache.php[95]
[wincache] Function "wincache_ucache_clear" used in file ..\yii-1.1.16.bca042\framework\caching/CWinCache.php[106]
```

If you want to see only usage of one specific extension, use `--extension=` option:
``` sh
> phpca --extension=apc ..\yii-1.1.16.bca042\framework\caching
Scanning ..\yii-1.1.16.bca042\framework\caching ...
[apc] Function "apc_fetch" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[46]
[apc] Function "apc_fetch" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[56]
[apc] Function "apc_store" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[70]
[apc] Function "apc_add" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[84]
[apc] Function "apc_delete" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[95]
[apc] Function "apc_clear_cache" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[107]
[apc] Function "apc_clear_cache" used in file ..\yii-1.1.16.bca042\framework\caching/CApcCache.php[109]
```
Summary report in this case will not be added at the end.

# Help
Full list of available options:
```sh
> phpca -h
PhpCodeAnalyzer
Usage:
    phpca [-v] [-q] [--output=<path>] [--no-report] [--no-progress] [--since-version=<version>] FILES...
    phpca [-v] [-q] [--output=<path>] --extension=<ext> FILES...
    phpca -h

Options:
  -h --help                 Show this text
  -v --verbose              Show more debug text
  -q --quiet                Don't print any messages
  --output=<path>           Path where to generate XML report
  --extension=<ext>         Look for usage a specific extension
  --no-report               Turn off summary report
  --no-progress             Turn off progress
  --since-version=<version> Only include extensions not included since version
```

# Installation
## Phar
The recommended way to install _phpca_ is as phar-package.

1. Just download a phar from [releases page](https://github.com/wapmorgan/PhpCodeAnalyzer/releases)
2. Make it executable and put it in one of folders listed in your `$PATH`:
    ```sh
    chmod +x phpca.phar
    sudo mv phpca.phar /usr/local/bin/phpca
    ```

Further I will use commands for PhpCodeAnalyzer installed as phar or globally with composer, but if you've installed it locally with composer, just replace `phpca` command with `vendor/bin/phpca`.

## Composer
Another way to install _phpca_ is via composer.

1. If you do not have composer installed, download the [`composer.phar`](https://getcomposer.org/composer.phar) executable or use the installer.
  ``` sh
  $ curl -sS https://getcomposer.org/installer | php
  ```

2. Run `php composer.phar require wapmorgan/php-code-analyzer *` or add requirement in composer.json.
  ``` json
  {
    "require": {
      "wapmorgan/php-code-analyzer": "*"
    }
  }
  ```

3. Run `php composer.phar update`

### Global installation
You can get more profit when _phpca_ installed globally.

1. If you do not have composer installed, look previous section and install composer on your server.

2. Run `php composer.phar global require wapmorgan/php-code-analyzer`

If _phpca_ installed globally, you can use `phpca` command inside any directory.
