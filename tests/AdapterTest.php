<?php

namespace EasyAws\Tests;

use Aws\Credentials\Credentials;
use Aws\Laravel\AwsServiceProvider;
use EasyAws\ServiceProvider;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\CacheManager;
use Orchestra\Testbench\TestCase;
use function GuzzleHttp\Promise\promise_for;
use function GuzzleHttp\Psr7\stream_for;

class AdapterTest extends TestCase
{
    public function testCredentialResolution()
    {
        app('easyaws.credentials')()->wait();
        $cache = $this->app->make(CacheManager::class)->store('array');

        $this->assertInstanceOf(Credentials::class, app('easyaws.credentials')()->wait());
        $this->assertTrue($cache->has('easyaws_cached_instance_credentials'));
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('easyaws.cache_store', 'array');
        $client = function ($request) {
            $expire = time() + 1000;
            $f = [
                'Code'            => 'Success',
                'AccessKeyId'     => 'foo',
                'SecretAccessKey' => 'bar',
                'Token'           => null,
                'Expiration'      => "@{$expire}",
            ];
            return promise_for(new Response(200, [], \GuzzleHttp\Psr7\Utils::streamFor(json_encode($f))));
        };
        $app['config']->set('easyaws.http_client', $client);
    }

    protected function getPackageProviders($app)
    {
        return [AwsServiceProvider::class, ServiceProvider::class];
    }
}
