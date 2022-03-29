<?php

namespace EasyAws\Tests;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Lambda\LambdaClient;
use Aws\Laravel\AwsServiceProvider;
use Aws\S3\S3Client;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use EasyAws\ServiceProvider;
use Orchestra\Testbench\TestCase;

class ServiceProviderTest extends TestCase
{
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

    public function testMakeDynamoDbClient()
    {
        $this->assertInstanceOf(DynamoDbClient::class, $this->app->make(DynamoDbClient::class));
    }

    protected function getPackageProviders($app)
    {
        return [AwsServiceProvider::class, ServiceProvider::class];
    }
}
