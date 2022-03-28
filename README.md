# Laravel Easy AWS

[![Latest Stable Version](https://poser.pugx.org/healthengine/laravel-easy-aws/version)](https://packagist.org/packages/healthengine/laravel-easy-aws)
[![Total Downloads](https://poser.pugx.org/healthengine/laravel-easy-aws/downloads)](https://packagist.org/packages/healthengine/laravel-easy-aws)
[![Build Status](https://travis-ci.com/HealthEngineAU/laravel-easy-aws.svg?branch=master)](https://travis-ci.com/HealthEngineAU/laravel-easy-aws)
[![Coverage Status](https://coveralls.io/repos/github/HealthEngineAU/laravel-easy-aws/badge.svg?branch=master)](https://coveralls.io/github/HealthEngineAU/laravel-easy-aws?branch=master)
[![Dependabot Status](https://api.dependabot.com/badges/status?host=github&repo=HealthEngineAU/laravel-easy-aws)](https://dependabot.com)

This is an extension to the [Laravel AWS SDK](https://github.com/aws/aws-sdk-php-laravel) that provides cacheable
dynamic credentials - such as those provided by the EC2 Metadata API. This is necessary if you want to
`php artisan config:cache` whilst also not using hard coded credentials - since Laravel is unable to cache the Guzzle
promise that resolves to credentials when used.

It also binds certain AWS client objects to the Laravel container so you can typehint the specific clients you need,
instead of using the `AWS` facade. Clients are added as needed and we welcome PRs so if you need one that is missing,
please contribute.

## Installation

```
composer require healthengine/laravel-easy-aws
```

## Usage

There's not much to it. You can now typehint the `S3Client` directly and it will be resolved instead of having to use
the facade to construct the S3 client:

```php
// before
$s3 = AWS::createClient('s3');

// after
$s3 = app(Aws\S3\S3Client::class);
```

Credentials are resolved automatically using the default provider chain from the AWS SDK. The only difference is that
they will be cached. This really only helps if that credentials chain ended up resolving credentials from the EC2
Metadata API, otherwise it would have been using hardcoded credentials of some kind and caching them doesn't help much.

You can configure which cache store to use by setting the environment variable `EASYAWS_CACHE_STORE` to any built in
Laravel cache drivers.

## Note

Caching credentials can be insecure. Be careful if you are going to use an external cache driver like Redis or database
as the credentials are stored in plaintext.

## SQS Client

The queue configuration for AWS SQS has also been modified to use these cached credentials. This means you don't need to
add a key or secret for that client - if using SQS as your queue driver. Also **please note** that  the `prefix`
configuration key is not used because it is redundant, you'll need to prepend the value of `prefix` to `queue`.

## License

Laravel Easy AWS is licensed under the MIT license.
