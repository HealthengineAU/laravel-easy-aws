<?php

namespace EasyAws\Queue;

use Aws\Sqs\SqsClient;
use Illuminate\Container\Container;
use Illuminate\Queue\Connectors\SqsConnector as BaseSqsConnector;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Arr;

class SqsConnector extends BaseSqsConnector
{
    public const CLIENT_FACTORY = 'easyaws.sqs_client_factory';

    public function connect(array $config)
    {
        $config = $this->withCredentials($this->getDefaultConfiguration($config));
        $clientConfig = Arr::except($config, ['token', 'overflow', 'credential_cache']);
        $container = Container::getInstance();

        return new SqsQueue(
            $container->bound(self::CLIENT_FACTORY)
                ? $container->make(self::CLIENT_FACTORY)($clientConfig)
                : new SqsClient($clientConfig),
            $config['queue'],
            $config['prefix'] ?? '',
            $config['suffix'] ?? '',
            $config['after_commit'] ?? null,
            $config['overflow'] ?? [],
        );
    }

    protected function withCredentials(array $config)
    {
        if (!array_key_exists('credentials', $config) && (empty($config['key']) || empty($config['secret']))) {
            $config['credentials'] = Container::getInstance()->make('easyaws.credentials');
        }

        return parent::withCredentials($config);
    }
}
