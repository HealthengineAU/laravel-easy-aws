<?php

namespace EasyAws\Tests;

use Aws\Athena\AthenaClient;
use Aws\Credentials\Credentials;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Lambda\LambdaClient;
use Aws\Laravel\AwsServiceProvider;
use Aws\Result;
use Aws\S3\S3Client;
use Aws\Sdk;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Aws\Sts\StsClient;
use EasyAws\Queue\SqsConnector;
use EasyAws\ServiceProvider;
use GuzzleHttp\Promise\Create;
use Illuminate\Queue\Connectors\SqsConnector as LaravelSqsConnector;
use Illuminate\Queue\SqsQueue;
use Orchestra\Testbench\TestCase;

class ServiceProviderTest extends TestCase
{
    private const SQS_CONNECTION = [
        'driver' => 'sqs',
        'prefix' => 'https://sqs.ap-southeast-2.amazonaws.com/123456789012',
        'queue' => 'default',
        'region' => 'ap-southeast-2',
    ];

    private const WEB_IDENTITY_ENV = [
        'AWS_ROLE_ARN' => 'arn:aws:iam::123456789012:role/test',
        'AWS_WEB_IDENTITY_TOKEN_FILE' => __FILE__,
    ];

    public function testMakeLambdaClient()
    {
        $this->assertInstanceOf(LambdaClient::class, $this->app->make(LambdaClient::class));
    }

    public function testMakeS3Client()
    {
        $this->assertInstanceOf(S3Client::class, $this->app->make(S3Client::class));
    }

    public function testMakeSnsClient()
    {
        $this->assertInstanceOf(SnsClient::class, $this->app->make(SnsClient::class));
    }

    public function testMakeSqsClient()
    {
        $this->assertInstanceOf(SqsClient::class, $this->app->make(SqsClient::class));
    }

    public function testSqsClientHasLongPollingTimeout()
    {
        $client = $this->app->make(SqsClient::class);

        $prop = (new \ReflectionClass('Aws\AwsClient'))->getProperty('defaultRequestOptions');
        $prop->setAccessible(true);
        $options = $prop->getValue($client);

        // SQS long-polling waits up to 20s — timeout must not be 5s
        $this->assertSame(60.0, $options['timeout']);
        $this->assertSame(5.0, $options['connect_timeout']);
    }

    public function testClientsUseStandardRetryMode()
    {
        $sdk = new class (['region' => 'ap-southeast-2', 'version' => 'latest']) extends Sdk {
            public array $args = [];

            public function createClient($name, array $args = [])
            {
                $this->args = $args;

                return parent::createClient($name, $args);
            }
        };
        $this->app->instance('aws', $sdk);

        $this->app->make(S3Client::class);

        $this->assertSame(['mode' => 'standard'], $sdk->args['retries']);
    }

    public function testMakeDynamoDbClient()
    {
        $this->assertInstanceOf(DynamoDbClient::class, $this->app->make(DynamoDbClient::class));
    }

    public function testMakeAthenaClient()
    {
        $this->assertInstanceOf(AthenaClient::class, $this->app->make(AthenaClient::class));
    }

    public function testCredentialProviderSharesStsClientForWebIdentity()
    {
        config(['aws.region' => 'ap-southeast-2']);

        $client = $this->withEnv(self::WEB_IDENTITY_ENV, fn () => $this->getCredentialProviderConfig())['stsClient'];

        $this->assertInstanceOf(StsClient::class, $client);
        $this->assertSame('ap-southeast-2', $client->getRegion());
        $this->assertSame(['connect_timeout' => 5.0, 'timeout' => 5.0], $client->getCommand('GetCallerIdentity')['@http']);
    }

    public function testStsClientRegionFallsBackToEnvWhenConfigRegionIsEmpty()
    {
        config(['aws.region' => '']);

        $config = $this->withEnv(
            self::WEB_IDENTITY_ENV + ['AWS_REGION' => 'ap-southeast-2'],
            fn () => $this->getCredentialProviderConfig(),
        );

        $this->assertSame('ap-southeast-2', $config['stsClient']->getRegion());
    }

    public function testCredentialProviderHasNoStsClientWithoutWebIdentity()
    {
        $config = $this->withEnv(
            ['AWS_ROLE_ARN' => null, 'AWS_WEB_IDENTITY_TOKEN_FILE' => null],
            fn () => $this->getCredentialProviderConfig(),
        );

        $this->assertArrayNotHasKey('stsClient', $config);
    }

    public function testStsClientIsBuiltOnceAcrossCredentialRefreshes()
    {
        config(['easyaws.cache_store' => 'array']);
        $provider = new class ($this->app) extends ServiceProvider {
            public int $builds = 0;

            protected function getCredentialProviderConfig(): array
            {
                $this->builds++;
                $issued = 0;
                $handler = function () use (&$issued) {
                    return Create::promiseFor(new Result(['Credentials' => [
                        'AccessKeyId' => 'key' . $issued++,
                        'SecretAccessKey' => 'secret',
                        'SessionToken' => 'token',
                        'Expiration' => new \DateTimeImmutable('+30 seconds'),
                    ]]));
                };
                $stsClient = new StsClient(['credentials' => false, 'region' => 'ap-southeast-2', 'handler' => $handler]);

                return ['stsClient' => $stsClient] + parent::getCredentialProviderConfig();
            }
        };
        $this->app->register($provider);

        $keys = $this->withEnv(
            self::WEB_IDENTITY_ENV + ['AWS_ACCESS_KEY_ID' => null, 'AWS_SECRET_ACCESS_KEY' => null],
            fn () => array_map(fn () => app('easyaws.credentials')()->wait()->getAccessKeyId(), range(1, 3)),
        );

        $this->assertCount(3, array_unique($keys));
        $this->assertSame(1, $provider->builds);
    }

    public function testSqsQueueUsesEasyAwsCredentialsByDefault()
    {
        $this->assertSame('easyaws', $this->sqsQueueAccessKeyId(['key' => null, 'secret' => null]));
    }

    public function testSqsQueueKeepsStaticKeys()
    {
        $this->assertSame('static', $this->sqsQueueAccessKeyId(['key' => 'static', 'secret' => 'secret']));
    }

    public function testSqsQueueUsesBoundClientFactory()
    {
        $client = new SqsClient(['region' => 'ap-southeast-2', 'credentials' => false]);
        $credentials = null;
        $this->app->instance(SqsConnector::CLIENT_FACTORY, function (array $config) use ($client, &$credentials) {
            $credentials = $config['credentials'];

            return $client;
        });
        config(['queue.connections.sqs' => self::SQS_CONNECTION]);

        $this->assertSame($client, $this->app->make('queue')->connection('sqs')->getSqs());
        $this->assertSame($this->app->make('easyaws.credentials'), $credentials);
    }

    public function testSqsConnectorBuildsSameQueueAsLaravel()
    {
        $config = self::SQS_CONNECTION + ['suffix' => '-dev', 'after_commit' => true, 'key' => 'k', 'secret' => 's'];

        $ours = (new SqsConnector())->connect($config);
        $laravels = (new LaravelSqsConnector())->connect($config);

        $this->assertSame($this->queueState($laravels), $this->queueState($ours));
    }

    private function queueState(SqsQueue $queue): array
    {
        $state = [];
        foreach ((new \ReflectionClass($queue))->getProperties() as $property) {
            if (!in_array($property->getName(), ['sqs', 'container'], true)) {
                $state[$property->getName()] = $property->getValue($queue);
            }
        }

        return $state;
    }

    private function sqsQueueAccessKeyId(array $config): string
    {
        $this->app->instance('easyaws.credentials', fn () => Create::promiseFor(new Credentials('easyaws', 'secret')));
        config(['queue.connections.sqs' => $config + [
            'driver' => 'sqs',
            'prefix' => 'https://sqs.ap-southeast-2.amazonaws.com/123456789012',
            'queue' => 'default',
            'region' => 'ap-southeast-2',
        ]]);

        return $this->app->make('queue')->connection('sqs')->getSqs()->getCredentials()->wait()->getAccessKeyId();
    }

    private function getCredentialProviderConfig(): array
    {
        $method = new \ReflectionMethod(ServiceProvider::class, 'getCredentialProviderConfig');

        return $method->invoke(new ServiceProvider($this->app));
    }

    private function withEnv(array $env, callable $callback): mixed
    {
        $original = [];

        try {
            foreach ($env as $name => $value) {
                $original[$name] = getenv($name);
                putenv($value === null ? $name : "$name=$value");
            }

            return $callback();
        } finally {
            foreach ($original as $name => $value) {
                putenv($value === false ? $name : "$name=$value");
            }
        }
    }

    protected function getPackageProviders($app)
    {
        return [AwsServiceProvider::class, ServiceProvider::class];
    }
}
