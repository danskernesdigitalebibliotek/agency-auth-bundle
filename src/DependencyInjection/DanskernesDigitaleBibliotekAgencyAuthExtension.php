<?php
/**
 * @file
 * Bundle extension
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class DanskernesDigitaleBibliotekAgencyAuthExtension.
 */
class DanskernesDigitaleBibliotekAgencyAuthExtension extends Extension
{
    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $definition = $container->getDefinition('ddb.agency_token_auth');
        $definition->setArgument(0, $config['openplatform_id']);
        $definition->setArgument(1, $config['openplatform_secret']);
        $definition->setArgument(2, $config['openplatform_introspection_url']);
        $definition->setArgument(3, $config['openplatform_allowed_clients']);

        if (null !== $config['http_client']) {
            $definition->setArgument(4, new Reference($config['http_client']));
        }
        if (null !== $config['auth_token_cache']) {
            $definition->setArgument(5, new Reference($config['auth_token_cache']));
        }
        if (null !== $config['auth_logger']) {
            $definition->setArgument(6, new Reference($config['auth_logger']));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return 'ddb_agency_auth';
    }
}
