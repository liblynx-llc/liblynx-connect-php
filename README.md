# liblynx-connect-php

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

This is a PHP client library for the LibLynx Connect identity and
access management API. The API allows a publisher to control access
to electronic resources without being concerned about the method
used, e.g. IP, Shibboleth, SAML, OpenID Connect etc.

**This library is open source, but access to the API requires a 
commercial agreement with LibLynx - contact us at info@liblynx.com
to discuss your requirements.**


## Install

Via Composer

``` bash
$ composer require liblynx-llc/liblynx-connect-php
```

## Setting API credentials

In order to use this, you will need an API client id and client secret from LibLynx. These
can be passed to the API client in one of two ways


### Set API credentials through environment variables

You can set the following environment variables to avoid placing credentials in your code

* LIBLYNX_CLIENT_ID
* LIBLYNX_CLIENT_SECRET


###  Set API credentials through code

Alternatively, you can set the credentials directly, e.g.

```php
$liblynx=new Liblynx\Connect\Client;
$liblynx->setCredentials('your client id', 'your client secret');
```

## Caching

To work as efficiently as possible, the client caches API responses such as the entrypoint
resource. Any PSR-16 compatible cache can be used, for example [symfony/cache](https://packagist.org/packages/symfony/cache)

For testing, you could use the `ArrayCache` from symfony/cache - install as follows:

``` bash
$ composer require symfony/cache
```

Then create and use an `ArrayCache` as follows

```php
$cache=new \Symfony\Component\Cache\Simple\ArrayCache;
$liblynx->setCache($cache);
```


## Diagnostic logging

Detailed information on API usage can be obtained by passing a PSR-3 compatible
logger to the client. This package includes a useful `DiagnosticLogger` class which
can be used to store logs and then output them for console or HTML reading.

```php
$logger = new \LibLynx\Connect\DiagnosticLogger;
$liblynx->setLogger($logger);
```

## Examples

A simple integration involves obtaining an account from data provided in the current
request superglobals

```php
try {
    $identification = $liblynx->authorize(IdentificationRequest::fromArray($_SERVER));
    if ($identification->isIdentified()) {
        //visitor is identified, you can now check their access rights
    } elseif ($identification->requiresWAYF()) {
         //to find our who the visitor is, redirect to WAYF page
         $url = $identification->getWayfUrl();
         header("Location: $url");
         exit;
    } else {
        //liblynx failed - check diagnostic logs
    }
} catch (LibLynx\Connect\Exception\LibLynxException $e) {
    //exceptions are throw for API failures and erroneous integrations
    throw $e;
}    
```

See the `examples` folder for other examples:

- `examples\example.php` is a console application which prompts for an IP and URL performs an 
  identification. 


## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@liblynx.com instead of using the issue tracker.

## Credits

- [Paul Dixon][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/liblynx-llc/liblynx-connect-php.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/liblynx-llc/liblynx-connect-php/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/liblynx-llc/liblynx-connect-php.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/liblynx-llc/liblynx-connect-php.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/liblynx-llc/liblynx-connect-php.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/liblynx-llc/liblynx-connect-php
[link-travis]: https://travis-ci.org/liblynx-llc/liblynx-connect-php
[link-scrutinizer]: https://scrutinizer-ci.com/g/liblynx-llc/liblynx-connect-php/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/liblynx-llc/liblynx-connect-php
[link-downloads]: https://packagist.org/packages/liblynx-llc/liblynx-connect-php
[link-author]: https://github.com/lordelph
[link-contributors]: ../../contributors
