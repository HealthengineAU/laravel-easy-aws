<?php

namespace EasyAws\Credentials;

use Aws\Credentials\Credentials;
use Aws\Credentials\CredentialProvider as BaseProvider;
use Aws\Exception\CredentialsException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\RejectedPromise;

class CredentialProvider extends BaseProvider
{
    /**
     * Provider that creates credentials from environment variables
     * AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, and AWS_SESSION_TOKEN.
     *
     * @return callable
     */
    public static function env()
    {
        return function () {
            // Use credentials from environment variables, if available
            $key = config('easyaws.credentials.key');
            $secret = config('easyaws.credentials.secret');
            if ($key && $secret) {
                return Create::promiseFor(
                    new Credentials($key, $secret, config('easyaws.credentials.session_token'))
                );
            }

            return new RejectedPromise(
                new CredentialsException('Could not find environment variable credentials in easyaws config')
            );
        };
    }
}
