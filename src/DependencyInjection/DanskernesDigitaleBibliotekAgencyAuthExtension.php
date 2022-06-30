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

        $openPlatformUserProviderDefinition = $container->getDefinition('DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\OpenPlatformUserProvider');
        $openPlatformUserProviderDefinition->setArgument(0, $config['openplatform_allowed_clients']);
        if (is_string($config['auth_token_cache'])) {
            $openPlatformUserProviderDefinition->setArgument(3, new Reference($config['auth_token_cache']));
        }

        $openplatformOauthApiClientDefinition = $container->getDefinition('DanskernesDigitaleBibliotek\AgencyAuthBundle\Openplatform\OpenplatformOauthApiClient');
        $openplatformOauthApiClientDefinition->setArgument(0, $config['openplatform_id']);
        $openplatformOauthApiClientDefinition->setArgument(1, $config['openplatform_secret']);
        $openplatformOauthApiClientDefinition->setArgument(2, $config['openplatform_introspection_url']);
        if (is_string($config['http_client'])) {
            $openplatformOauthApiClientDefinition->setArgument(3, new Reference($config['http_client']));
        }

        $loggerDefinition = $container->getDefinition('DanskernesDigitaleBibliotek\AgencyAuthBundle\Utils\Logger');
        if (is_string($config['auth_logger'])) {
            $loggerDefinition->setArgument(0, new Reference($config['auth_logger']));
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
