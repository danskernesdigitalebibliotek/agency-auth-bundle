<?php
/**
 * @file
 * Bundle extension
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\DependencyInjection;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\MissingConfigurationException;
use Exception;
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
     * @throws Exception
     */
    final public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $configuration = $this->getConfiguration($configs, $container);
        if (null === $configuration) {
            throw new MissingConfigurationException('The configuration for ddb_agency_auth could ot be loaded');
        }
        $config = $this->processConfiguration($configuration, $configs);

        $definition = $container->getDefinition('ddb.agency_token_auth');
        $definition->setArgument(0, $config['openplatform_id']);
        $definition->setArgument(1, $config['openplatform_secret']);
        $definition->setArgument(2, $config['openplatform_introspection_url']);
        $definition->setArgument(3, $config['openplatform_allowed_clients']);

        if (is_string($config['http_client'])) {
            $definition->setArgument(4, new Reference($config['http_client']));
        }
        if (is_string($config['auth_token_cache'])) {
            $definition->setArgument(5, new Reference($config['auth_token_cache']));
        }
        if (is_string($config['auth_logger'])) {
            $definition->setArgument(6, new Reference($config['auth_logger']));
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function getAlias(): string
    {
        return 'ddb_agency_auth';
    }
}
