<?php

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests\Security;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\NotImplementedException;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Openplatform\OpenplatformOauthApiClient;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\OpenPlatformUserProvider;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\User;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Utils\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class OpenPlatformUserProviderTest extends TestCase
{
    private Logger $logger;
    private OpenplatformOauthApiClient $client;
    private AdapterInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(Logger::class);
        $this->client = $this->createMock(OpenplatformOauthApiClient::class);
        $this->cache = new ArrayAdapter();
    }

    /**
     * Test that access granted if user is valid Open Platform token supplied.
     *
     * @throws Exception
     */
    public function testActiveUserAllowed(): void
    {
        $openPlatformUserProvider = $this->getOpenplatformUserProvider();

        $user = $this->getUser(active: true);
        $user->setToken('mock');
        $this->client->method('getUser')->willReturn($user);

        $loadedUser = $openPlatformUserProvider->loadUserByIdentifier('12345678');
        $this->assertEquals($user, $loadedUser, 'Provider should return user from client');
    }

    /**
     * Test that access denied if user non 'active' in Open Platform.
     *
     * @throws Exception
     */
    public function testNonActiveUserDenied(): void
    {
        $openPlatformUserProvider = $this->getOpenplatformUserProvider();

        $user = $this->getUser(active: false);
        $this->client->method('getUser')->willReturn($user);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User/Token not active at the introspection end-point: 1234567');

        $openPlatformUserProvider->loadUserByIdentifier('12345678');
    }

    /**
     * Test that access denied if user not 'anonymous' e.g. unknown user type in Open Platform.
     *
     * @throws Exception
     */
    public function testNonAnonymousTokenTypeDenied(): void
    {
        $openPlatformUserProvider = $this->getOpenplatformUserProvider();

        $user = $this->getUser(type: 'test');
        $this->client->method('getUser')->willReturn($user);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('Token call to Open Platform returned unknown type: test');

        $openPlatformUserProvider->loadUserByIdentifier('12345678');
    }

    /**
     * Test that access denied if token is expired.
     *
     * @throws Exception
     */
    public function testExpiredTokenIsDenied(): void
    {
        $openPlatformUserProvider = $this->getOpenplatformUserProvider();

        $user = $this->getUser(expire: '- 1 day');
        $this->client->method('getUser')->willReturn($user);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User expired. User loaded from cache for agency id: 12345678');

        $openPlatformUserProvider->loadUserByIdentifier('12345678');
    }

    /**
     * Test that a user is returned when client is on client allow list.
     */
    public function testAgencyShouldBeAllowed(): void
    {
        $openPlatformUserProvider = $this->getOpenplatformUserProvider('allowed');

        $user = $this->getUser(active: true);
        $user->setClientId('allowed');
        $this->client->method('getUser')->willReturn($user);

        $loadedUser = $openPlatformUserProvider->loadUserByIdentifier('12345678');
        $this->assertEquals($user, $loadedUser, 'Provider should return user from client when client id allowed');
    }

    /**
     * Test that an exception is thrown when client is not on client allow list.
     */
    public function testAgencyShouldNotBeAllowed(): void
    {
        $openPlatformUserProvider = $this->getOpenplatformUserProvider('allowed');

        $user = $this->getUser(active: true);
        $user->setClientId('not-allowed');
        $this->client->method('getUser')->willReturn($user);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User client id is not on allow client list: not-allowed');

        $loadedUser = $openPlatformUserProvider->loadUserByIdentifier('12345678');
    }

    /**
     * Test that 'refreshUser()' throws correct exception.
     */
    public function testRefreshUser(): void
    {
        $openPlatformUserProvider = $this->getOpenplatformUserProvider('');
        $user = new User();

        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('Method "refreshUser" not implemented. Please only use the OpenPlatformUserProvider in a stateless firewall');

        $openPlatformUserProvider->refreshUser($user);
    }

    /**
     * Test supportsClass.
     */
    public function testSupportsClass(): void
    {
        $openPlatformUserProvider = $this->getOpenplatformUserProvider('');

        $supports = $openPlatformUserProvider->supportsClass(User::class);
        $this->assertTrue($supports);

        $supports = $openPlatformUserProvider->supportsClass(\http\Client\Curl\User::class);
        $this->assertFalse($supports);
    }

    /**
     * Helper function to set up users.
     *
     * @throws \Exception
     */
    private function getUser(bool $active = true, string $type = 'anonymous', string $expire = '+ 1 day'): User
    {
        $expire = new \DateTime($expire);

        $user = new User();
        $user->setActive($active);
        $user->setAuthType($type);
        $user->setExpires($expire);

        return $user;
    }

    /**
     * Helper function to set up OpenPlatformUserProvider with/without allowed clients.
     *
     * @param string $allowedClients
     *   Allow list of client id's. Supply an empty array to allow all.
     *
     * @return OpenPlatformUserProvider
     *   A configured OpenPlatformUserProvider
     */
    private function getOpenplatformUserProvider(string $allowedClients = ''): OpenPlatformUserProvider
    {
        return new OpenPlatformUserProvider($allowedClients, $this->client, $this->logger, $this->cache);
    }
}
