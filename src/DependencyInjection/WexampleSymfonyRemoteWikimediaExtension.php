<?php

namespace Wexample\SymfonyRemoteWikimedia\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyRemoteWikimediaExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('wexample_symfony_remote_wikimedia.user_agent', $config['user_agent']);
        $container->setParameter('wexample_symfony_remote_wikimedia.commons.api_url', $config['commons']['api_url']);
        $container->setParameter('wexample_symfony_remote_wikimedia.commons.attempts', $config['commons']['attempts']);
        $container->setParameter('wexample_symfony_remote_wikimedia.commons.retry_delay', $config['commons']['retry_delay']);

        $this->loadConfig(
            __DIR__,
            $container
        );
    }
}
