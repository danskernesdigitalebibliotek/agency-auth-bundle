<?php
/**
 * @file
 * Bundle configuration
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('ddb_agency_auth');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('openplatform_id')->defaultValue('my_id')->end()
                ->scalarNode('openplatform_secret')->defaultValue('my_secret')->end()
                ->scalarNode('openplatform_introspection_url')->defaultValue('https://login.bib.dk/oauth/introspection')->end()
                ->arrayNode('openplatform_allowed_clients')->scalarPrototype()->end()->end()
                ->scalarNode('http_client')->defaultValue('Symfony\Contracts\HttpClient\HttpClientInterface')->end()
                ->scalarNode('auth_token_cache')->defaultNull()->end()
                ->scalarNode('auth_logger')->defaultNull()->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
