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
    
    public function testUser(): void
    {
        $user = new User();
        
        $this->assertFalse($user->isActive());
        
        $expires = new \DateTime('now');
        $agency = 'agency';
        $authType = 'anonymous';
        $clientId = '1234';
        $token = 'auth1234';
        
        $user->setExpires($expires);
        $user->setAgency($agency);
        $user->setActive(true);
        $user->setAuthType($authType);
        $user->setClientId($clientId);
        $user->setToken($token);
        
        $this->assertSame($expires, $user->getExpires());
        $this->assertSame($agency, $user->getAgency());
        $this->assertTrue($user->isActive());
        $this->assertSame($authType, $user->getAuthType());
        $this->assertSame($clientId, $user->getClientId());
        $this->assertSame($token, $user->getToken());
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
