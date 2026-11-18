<?php

namespace Wexample\SymfonyRemoteWikimedia\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyRemoteWikimedia\Service\CommonsClient;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_remote_wikimedia');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('user_agent')
                    ->defaultValue('wexample-symfony-remote-wikimedia/1.0')
                    ->info('Who asks: Wikimedia turns away anonymous clients. Name the app.')
                ->end()
                ->arrayNode('commons')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('api_url')->defaultValue(CommonsClient::DEFAULT_API_URL)->end()
                        ->integerNode('attempts')->defaultValue(3)->min(1)->end()
                        ->integerNode('retry_delay')->defaultValue(5)->min(0)->info('Seconds before the next attempt, times the attempt number.')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
