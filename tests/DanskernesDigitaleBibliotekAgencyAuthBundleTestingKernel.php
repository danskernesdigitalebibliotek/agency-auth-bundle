<?php
/**
 * @file
 * Minimal kernel for testing
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\DanskernesDigitaleBibliotekAgencyAuthBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class DanskernesDigitaleBibliotekAgencyAuthBundleTestingKernel.
 */
class DanskernesDigitaleBibliotekAgencyAuthBundleTestingKernel extends Kernel
{
    /**
     * {@inheritdoc}
     */
    public function registerBundles()
    {
        return [
            new DanskernesDigitaleBibliotekAgencyAuthBundle(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $containerBuilder) {
            $containerBuilder->register(HttpClientInterface::class, CurlHttpClient::class);
        });
    }
}
