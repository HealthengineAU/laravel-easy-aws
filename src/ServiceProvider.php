<?php

namespace EasyAws;

use Aws\Athena\AthenaClient;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Lambda\LambdaClient;
use Aws\S3\S3Client;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Aws\Sts\StsClient;
use EasyAws\Credentials\CredentialProvider;
use EasyAws\Queue\SqsConnector;
use Illuminate\Queue\AwsCredentialCache;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    private const CLIENT_DEFAULTS = [
        'http' => [
            'connect_timeout' => 5.0,
            'timeout' => 5.0,
        ],
        'retries' => [
            'mode' => 'standard',
        ],
    ];

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/easyaws.php' => config_path('easyaws.php')]);

        $this->callAfterResolving('queue', function (QueueManager $manager) {
            $manager->extend('sqs', fn () => new SqsConnector());
        });
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/easyaws.php', 'easyaws');

        $this->app->singleton('easyaws.credentials', function ($app) {
            $provider = null;
            $cache = new AwsCredentialCache(fn () => $app->make('cache')->store(config('easyaws.cache_store')));
            $resolve = function () use (&$provider) {
                $provider ??= CredentialProvider::defaultProvider($this->getCredentialProviderConfig());

                return $provider();
            };

            return CredentialProvider::memoize(fn () => $cache->resolve('easyaws:credentials', $resolve));
        });
        $this->app->singleton(LambdaClient::class, $this->getAwsClientClosure('lambda'));
        $this->app->singleton(S3Client::class, $this->getAwsClientClosure('s3'));
        $this->app->singleton(SnsClient::class, $this->getAwsClientClosure('sns'));
        $this->app->singleton(SqsClient::class, $this->getAwsClientClosure('sqs', [
            'http' => [
                'connect_timeout' => 5.0,
                'timeout' => 60.0, // SQS long-polling waits up to 20s — must not be 5s
            ],
        ]));
        $this->app->singleton(DynamoDbClient::class, $this->getAwsClientClosure('dynamoDb'));
        $this->app->singleton(AthenaClient::class, $this->getAwsClientClosure('athena'));
    }

    protected function getCredentialProviderConfig(): array
    {
        $config = array_merge(
            config('aws', []),
            [
                'client' => config('easyaws.http_client'), // NOTE: used for unit testing only
            ],
        );

        if (
            !isset($config['stsClient'])
            && getenv(CredentialProvider::ENV_TOKEN_FILE)
            && getenv(CredentialProvider::ENV_ARN)
        ) {
            $config['stsClient'] = new StsClient(self::CLIENT_DEFAULTS + [
                'credentials' => false,
                'region' => ($config['region'] ?? null)
                    ?: (getenv(CredentialProvider::ENV_REGION) ?: CredentialProvider::FALLBACK_REGION),
            ]);
        }

        return $config;
    }

    /**
     * Get a closure for binding into the laravel container that will create the provider AWS client.
     *
     * @param string $client The AWS client to retrieve
     * @return callable|\Closure For binding a concrete class using this factory.
     */
    protected function getAwsClientClosure(string $client, array $config = [])
    {
        return function ($app, $runtimeConfig) use ($client, $config) {
            $config = array_merge(
                ['credentials' => $app->make('easyaws.credentials')],
                self::CLIENT_DEFAULTS,
                $config,
                $runtimeConfig
            );
            return $app->make('aws')->createClient($client, $config);
        };
    }
}
