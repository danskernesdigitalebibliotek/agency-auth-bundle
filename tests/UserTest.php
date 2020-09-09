<?php
/**
 * @file
 * User class tests
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\User;
use PHPUnit\Framework\TestCase;

/**
 * Class BundleTest.
 */
class UserTest extends TestCase
{
    /**
     * Test auth type.
     */
    public function testAuthType(): void
    {
        $user = new User();
        $user->setAuthType('anonymous');

        $this->assertSame('anonymous', $user->getAuthType());
    }

    /**
     * Test that correct role is returned.
     */
    public function testRoles(): void
    {
        $user = new User();

        $this->assertEquals(['ROLE_OPENPLATFORM_AGENCY'], $user->getRoles());
    }

    /**
     * Test get salt.
     */
    public function testSalt(): void
    {
        $user = new User();

        $this->assertNull($user->getSalt());
    }

    /**
     * Test erase credentials.
     */
    public function testEraseCredentials(): void
    {
        $user = new User();

        $this->assertNull($user->eraseCredentials());
    }
}
