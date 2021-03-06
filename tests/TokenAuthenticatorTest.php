<?php
/**
 * @file
 * Integration test for Token Authenticator
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\TokenAuthenticator;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\User;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class TokenAuthenticatorTest.
 */
class TokenAuthenticatorTest extends TestCase
{
    private $httpClient;
    private $cache;
    private $item;
    private $userProvider;
    private $tokenAuthenticator;
    private $logger;

    /**
     * Setup mocks.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->cache = $this->createMock(AdapterInterface::class);
        $this->item = $this->createMock(ItemInterface::class);
        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * Tests functions.
     */
    public function testTokenAuthenticatorFunctions(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $request = new Request();
        $this->assertFalse($this->tokenAuthenticator->supports($request), 'Token authenticator should not support requests without authorization');
        $request->headers->set('authorization', '1234');
        $this->assertTrue($this->tokenAuthenticator->supports($request), 'Token authenticator should support authorization requests');
        $this->assertEquals('1234', $this->tokenAuthenticator->getCredentials($request), 'Token authenticator should return token');
    }

    /**
     * Test that cached tokens does not trigger call to Open Platform.
     */
    public function testCachedTokensAreReturnedFromCache(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->cache->expects($this->once())->method('getItem')->with('12345678');

        $this->item->method('isHit')->willReturn(true);
        $user = new User();
        $user->setAgency('123456');
        $user->setExpires(new \DateTime('+1day'));
        $this->item->method('get')->willReturn($user);
        $this->item->expects($this->once())->method('get');

        $this->httpClient->expects($this->never())->method('request');

        $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
    }

    /**
     * Test that introspection endpoint is called correctly.
     */
    public function testTokenCallToOpenPlatform(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->getMockUserResponse(200, true, 'now + 2 days', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);
        $this->httpClient->expects($this->once())->method('request')->with('POST', 'https://auth.test?access_token=12345678', [
            'auth_basic' => ['id', 'secret'],
        ]);

        $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
    }

    /**
     * Test that access is denied if Open Platform does not return HTTP 200.
     */
    public function testAccessDeniedIfRequestNot200(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->getMockUserResponse(401, true, 'now + 2 days', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNull($user, 'TokenAuthenticator should return null (access denied) if it receives a non 200 response code');
    }

    /**
     * Test that access is denied if request throws exception.
     */
    public function testAccessDeniedIfRequestException(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $this->httpClient->method('request')->willThrowException(new HttpException(500));

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNull($user, 'TokenAuthenticator should return null (access denied) if request throws exception');
    }

    /**
     * Test that access denied if user non 'active' in Open Platform.
     *
     * @throws Exception
     */
    public function testNonActiveUserDenied(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->getMockUserResponse(200, false, 'now + 2 days', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNull($user, 'TokenAuthenticator should return null (access denied) if user not active');
    }

    /**
     * Test that access denied if user not 'anonymous' e.g. unknown user type in Open Platform.
     *
     * @throws Exception
     */
    public function testNonAnonymousTokenTypeDenied(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->getMockUserResponse(200, true, 'now + 2 days', 'client-id-hash', 'user');
        $this->httpClient->method('request')->willReturn($response);

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNull($user, 'TokenAuthenticator should return null (access denied) if user not active');
    }

    /**
     * Test that access denied if token is expired.
     *
     * @throws Exception
     */
    public function testExpiredTokenIsDenied(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->getMockUserResponse(200, false, 'now - 2 days', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNull($user, 'TokenAuthenticator should return null (access denied) if token expired');
    }

    /**
     * Test that access denied if we receive an error from Open Platform.
     *
     * @throws Exception
     */
    public function testErrorTokenIsDenied(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $json = '{
            "error":"Invalid client and/or secret"
        }';
        $response->method('getContent')->willReturn($json);

        $this->httpClient->method('request')->willReturn($response);

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNull($user, 'TokenAuthenticator should return null (access denied) if error received from Open Platform');
    }

    /**
     * Test that access denied if we receive invalid json from Open Platform.
     *
     * @throws Exception
     */
    public function testInvalidJsonTokenIsDenied(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $expires = new \DateTime('now + 2 days', new \DateTimeZone('UTC'));

        // Test is for invalid json, hence "active": true,error
        $json = '{
            "active": true,error
            "clientId": "client-id-hash",
            "expires": "'.$expires->format('Y-m-d\TH:i:s.u\Z').'",
            "agency": "123456",
            "uniqueId": null,
            "search": {
                "profile": "abcd",
                "agency": "123456"
            },
            "type": "anonymous",
            "name": "DDB CMS",
            "contact": {
                "owner": {
                    "name": "Hans Hansen",
                    "email": "hans@hansen.dk",
                    "phone": "11 22 33 44"
                }
            }
        }';
        $response->method('getContent')->willReturn($json);

        $this->httpClient->method('request')->willReturn($response);

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNull($user, 'TokenAuthenticator should return null (access denied) if invalid json received from Open Platform');
    }

    /**
     * Test that access granted if user is valid Open Platform token supplied.
     *
     * @throws Exception
     */
    public function testActiveUserAllowed(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->getMockUserResponse(200, true, 'now + 2 days', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertInstanceOf(User::class, $user, 'TokenAuthenticator should return a "User" object for valid tokens');
        $this->assertEquals('123456', $user->getAgency());
        $this->assertEquals('client-id-hash', $user->getClientId());
        $this->assertEquals('123456', $user->getUsername());
        $this->assertEquals('12345678', $user->getPassword());
    }

    /**
     * Test that access allowed for tokens with lifetime shorter than TOKEN_CACHE_MAX_LIFETIME.
     *
     * @throws Exception
     */
    public function testShortTtlTokenAllowed(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->getMockUserResponse(200, true, 'now + 2 hours', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertInstanceOf(User::class, $user, 'TokenAuthenticator should return a "User" object for valid tokens');
        $this->assertEquals('123456', $user->getAgency());
        $this->assertEquals('client-id-hash', $user->getClientId());
        $this->assertEquals('123456', $user->getUsername());
        $this->assertEquals('12345678', $user->getPassword());
    }

    /**
     * Test that cached tokens with allowed client id returns user.
     */
    public function testCachedTokensClientIsAllowed(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('allowed-client-id-1, allowed-client-id-2, allowed-client-id-3');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->cache->expects($this->once())->method('getItem')->with('12345678');

        $this->item->method('isHit')->willReturn(true);
        $user = new User();
        $user->setClientId('allowed-client-id-2');
        $expires = new \DateTime('now + 2 days', new \DateTimeZone('UTC'));
        $user->setExpires($expires);
        $this->item->method('get')->willReturn($user);
        $this->item->expects($this->once())->method('get');

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNotNull($user, 'A valid user should be returned when the client is allowed');
    }

    /**
     * Test that cached tokens with expired tokens returns null.
     */
    public function testCachedExpiredTokensNotAllowed(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('allowed-client-id');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->cache->expects($this->once())->method('getItem')->with('12345678');

        $this->item->method('isHit')->willReturn(true);
        $user = new User();
        $user->setClientId('not-allowed-client-id');
        $user->setExpires(new \DateTime('-1min'));
        $this->item->method('get')->willReturn($user);
        $this->item->expects($this->once())->method('get');

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNull($user, 'Null should be returned when the cached user is expired');
    }

    /**
     * Test that cached tokens with not-allowed client id returns null.
     */
    public function testCachedTokensClientIsNotAllowed(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('allowed-client-id');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->cache->expects($this->once())->method('getItem')->with('12345678');

        $this->item->method('isHit')->willReturn(true);
        $user = new User();
        $user->setClientId('not-allowed-client-id');
        $user->setExpires(new \DateTime('+1day'));
        $this->item->method('get')->willReturn($user);
        $this->item->expects($this->once())->method('get');

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNull($user, 'Null should be returned when the agency is not allowed');
    }

    /**
     * Test that null tokens returns null.
     */
    public function testNullTokensIsNotAllowed(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->expects($this->never())->method('getItem');

        $user = $this->tokenAuthenticator->getUser(null, $this->userProvider);
        $this->assertNull($user, 'Null should be returned when token is null');
    }

    /**
     * Test that invalid tokens returns null.
     */
    public function testInvalidTokensIsNotAllowed(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');

        $this->cache->expects($this->never())->method('getItem');

        $user = $this->tokenAuthenticator->getUser(12345678, $this->userProvider);
        $this->assertNull($user, 'Null should be returned when token is invalid');
    }

    /**
     * Test that a user is returned when client is on client allow list.
     *
     * @throws Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testAgencyShouldBeAllowed(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('allowed-client-id');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->getMockUserResponse(200, true, 'now + 2 days', 'allowed-client-id', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNotNull($user, 'TokenAuthenticator should return user when client is allowed');
    }

    /**
     * Test that null is returned when client is NOT on client allow list.
     *
     * @throws Exception
     */
    public function testAgencyShouldNotBeAllowed(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('allowed-client-id');

        $this->cache->method('getItem')->willReturn($this->item);
        $this->item->method('isHit')->willReturn(false);

        $response = $this->getMockUserResponse(200, true, 'now + 2 days', 'not-allowed-client-id', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $user = $this->tokenAuthenticator->getUser('Bearer 12345678', $this->userProvider);
        $this->assertNull($user, 'TokenAuthenticator should return null when client is not on allow list');
    }

    /**
     * Test that checkCredentials always return true.
     */
    public function testCheckCredentials(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');
        $user = $this->createMock(UserInterface::class);
        // In case of a token, no credential check is needed.
        // Return `true` to cause authentication success
        $credentials = $this->tokenAuthenticator->checkCredentials([], $user);
        $this->assertTrue($credentials, 'checkCredentials should always return true');
    }

    /**
     * Test that onAuthenticationSuccess always return null.
     */
    public function testOnAuthenticationSuccess(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');
        $request = $this->createMock(Request::class);
        $token = $this->createMock(TokenInterface::class);
        $result = $this->tokenAuthenticator->onAuthenticationSuccess($request, $token, 'key');
        $this->assertNull($result, 'onAuthenticationSuccess should always return null');
    }

    /**
     * Test that onAuthenticationFailure always return JsonResponse status 401.
     */
    public function testOnAuthenticationFailure(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');
        $request = $this->createMock(Request::class);
        $e = $this->createMock(AuthenticationException::class);
        $response = $this->tokenAuthenticator->onAuthenticationFailure($request, $e);
        $this->assertInstanceOf(JsonResponse::class, $response, 'checkCredentials should always return a JsonResponse');
        $this->assertEquals(401, $response->getStatusCode(), 'onAuthenticationFailure should return 401');
    }

    /**
     * Test that start() always return JsonResponse status 401.
     */
    public function testStart(): void
    {
        $this->tokenAuthenticator = $this->getTokenAuthenticator('');
        $request = $this->createMock(Request::class);
        $e = $this->createMock(AuthenticationException::class);
        $response = $this->tokenAuthenticator->start($request, $e);
        $this->assertInstanceOf(JsonResponse::class, $response, 'checkCredentials should always return a JsonResponse');
        $this->assertEquals(401, $response->getStatusCode(), 'onAuthenticationFailure should return 401');
    }

    /**
     * Helper function to setup TokenAuthenticator with/without allowed clients.
     *
     * @param string $allowedClients
     *   An allow list of client id's. Supply an empty array to allow all.
     *
     * @return TokenAuthenticator
     *   A configured TokenAuthenticator
     */
    private function getTokenAuthenticator(string $allowedClients)
    {
        return new TokenAuthenticator('id', 'secret', 'https://auth.test', $allowedClients, $this->httpClient, $this->cache, $this->logger);
    }

    /**
     * Helper function to get mock user response.
     *
     * @param int $httpStatus
     *   The http status code for the response
     * @param bool $active
     *   Is the user active in Open Platform
     * @param string $expiresStr
     *   The expire time of the token
     * @param string $clientId
     *   The users client id
     * @param string $type
     *   The users type
     *
     * @return ResponseInterface
     *   A mock response with a json user object as content
     *
     * @throws Exception
     */
    private function getMockUserResponse(int $httpStatus, bool $active, string $expiresStr, string $clientId, string $type): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($httpStatus);
        $expires = new \DateTime($expiresStr, new \DateTimeZone('UTC'));
        $active = $active ? 'true' : 'false';
        $json = '{
            "active": '.$active.',
            "clientId": "'.$clientId.'",
            "expires": "'.$expires->format('Y-m-d\TH:i:s.u\Z').'",
            "agency": "123456",
            "uniqueId": null,
            "search": {
                "profile": "abcd",
                "agency": "123456"
            },
            "type": "'.$type.'",
            "name": "DDB CMS",
            "contact": {
                "owner": {
                    "name": "Hans Hansen",
                    "email": "hans@hansen.dk",
                    "phone": "11 22 33 44"
                }
            }
        }';
        $response->method('getContent')->willReturn($json);

        return $response;
    }
}
