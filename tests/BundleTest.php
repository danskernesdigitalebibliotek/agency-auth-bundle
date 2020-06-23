<?php
/**
 * @file
 * Integration test for bundle service wiring
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\TokenAuthenticator;
use PHPUnit\Framework\TestCase;

/**
 * Class BundleTest.
 */
class BundleTest extends TestCase
{
    /**
     * Test service wiring.
     */
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
