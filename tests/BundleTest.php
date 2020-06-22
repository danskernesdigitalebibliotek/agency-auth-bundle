<?php
/**
 * @file
 * Integration test for bundle service wiring
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\DanskernesDigitaleBibliotekAgencyAuthBundle;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\TokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class BundleTest.
 */
class BundleTest extends TestCase
{
    public function testServiceWiring()
    {
        $kernel = new DanskernesDigitaleBibliotekAgencyAuthBundleTestingKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();

        $tokenAuthenticator = $container->get('ddb.agency_token_auth');
        $this->assertInstanceOf(TokenAuthenticator::class, $tokenAuthenticator);
        $this->assertFalse($tokenAuthenticator->supportsRememberMe());
    }
}

/**
 * Class DanskernesDigitaleBibliotekAgencyAuthBundleTestingKernel.
 */
class DanskernesDigitaleBibliotekAgencyAuthBundleTestingKernel extends Kernel
{
    /** {@inheritdoc} */
    public function registerBundles()
    {
        return [
            new DanskernesDigitaleBibliotekAgencyAuthBundle(),
        ];
    }

    /** {@inheritdoc} */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $containerBuilder) {
            $containerBuilder->register(HttpClientInterface::class, CurlHttpClient::class);
        });
    }
}
