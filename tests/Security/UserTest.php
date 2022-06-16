<?php

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests\Security;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\User;
use PHPUnit\Framework\TestCase;

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
     * Test erase credentials.
     */
    public function testEraseCredentials(): void
    {
        $user = new User();
        $user->setToken('1234');
        $user->eraseCredentials();

        $this->assertNull($user->getToken());
    }
}
