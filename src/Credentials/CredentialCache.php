<?php

namespace EasyAws\Credentials;

use Aws\Credentials\CredentialsInterface;
use Illuminate\Queue\AwsCredentialCache;

class CredentialCache extends AwsCredentialCache
{
    protected function freshCredentials($key)
    {
        $credentials = parent::freshCredentials($key);

        if ($credentials && ! $credentials->getExpiration()) {
            $this->forget($key);

            return null;
        }

        return $credentials;
    }

    protected function put($key, CredentialsInterface $credentials, $ttl = 0)
    {
        if ($ttl > 0) {
            parent::put($key, $credentials, $ttl);
        }
    }
}
