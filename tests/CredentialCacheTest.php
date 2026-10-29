<?php

namespace EasyAws\Tests;

use Aws\Credentials\Credentials;
use Aws\Laravel\AwsServiceProvider;
use EasyAws\Credentials\CredentialCache;
use EasyAws\ServiceProvider;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Cache\CacheManager;
use Orchestra\Testbench\TestCase;
use GuzzleHttp\Promise\Create;

class CredentialCacheTest extends TestCase
{
    public function testCredentialResolution()
    {
        app('easyaws.credentials')()->wait();

        $this->assertInstanceOf(Credentials::class, app('easyaws.credentials')()->wait());
        $this->assertTrue($this->cache()->has('easyaws:credentials'));
    }

    public function testStaticCredentialsAreNotCached()
    {
        $this->useStaticCredentials();

        $this->assertSame('static-key', app('easyaws.credentials')()->wait()->getAccessKeyId());
        $this->assertFalse($this->cache()->has('easyaws:credentials'));
    }

    public function testNonExpiringCredentialsAreIgnoredAndPurged()
    {
        $this->cache()->forever('easyaws:credentials', new Credentials('revoked-key', 'revoked-secret'));
        $cache = new CredentialCache(fn () => $this->cache());

        $credentials = $cache->resolve('easyaws:credentials', fn () => Create::promiseFor(new Credentials('static-key', 'static-secret')))->wait();

        $this->assertSame('static-key', $credentials->getAccessKeyId());
        $this->assertFalse($this->cache()->has('easyaws:credentials'));
    }

    protected function tearDown(): void
    {
        putenv('AWS_ACCESS_KEY_ID');
        putenv('AWS_SECRET_ACCESS_KEY');

        parent::tearDown();
    }

    protected function useStaticCredentials()
    {
        putenv('AWS_ACCESS_KEY_ID=static-key');
        putenv('AWS_SECRET_ACCESS_KEY=static-secret');
    }

    protected function cache()
    {
        return $this->app->make(CacheManager::class)->store('array');
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
            return Create::promiseFor(new Response(200, [], Utils::streamFor(json_encode($f))));
        };
        $app['config']->set('easyaws.http_client', $client);
    }

    protected function getPackageProviders($app)
    {
        return [AwsServiceProvider::class, ServiceProvider::class];
    }
}
