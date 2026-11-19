<?php

namespace EasyAws\Queue;

use Illuminate\Container\Container;
use Illuminate\Queue\Connectors\SqsConnector as BaseSqsConnector;

class SqsConnector extends BaseSqsConnector
{
    protected function withCredentials(array $config)
    {
        if (!array_key_exists('credentials', $config) && (empty($config['key']) || empty($config['secret']))) {
            $config['credentials'] = Container::getInstance()->make('easyaws.credentials');
        }

        return parent::withCredentials($config);
    }
}
