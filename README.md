# php-cba-netbank

Unofficial The Commonwealth Bank of Australia NetBank API wrap for PHP

## Install

Via Composer

``` bash
$ composer require kravock/php-cba-netbank
```

## Usage

``` php
$api = new Kravock\Netbank\API();

$accounts = $api->login('CLIENT_NUMBER', 'PASSWORD');

foreach ($accounts as $bsbNumber => $account) {
    $transactions = $api->getTransactions($accounts[$bsbNumber], '12/04/2017', '22/04/2017');
}

```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email andrew@relentlesshosting.com.au instead of using the issue tracker.

## Credits

- [Kravock][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/kravock/php-cba-netbank.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/kravock/php-cba-netbank/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/kravock/php-cba-netbank.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/kravock/php-cba-netbank.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/kravock/php-cba-netbank.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/kravock/php-cba-netbank
[link-travis]: https://travis-ci.org/kravock/php-cba-netbank
[link-scrutinizer]: https://scrutinizer-ci.com/g/kravock/php-cba-netbank/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/kravock/php-cba-netbank
[link-downloads]: https://packagist.org/packages/kravock/php-cba-netbank
[link-author]: https://github.com/kravock
[link-contributors]: ../../contributors
