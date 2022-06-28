<?php

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests\Openplatform;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\OpenPlatformException;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Openplatform\OpenplatformOauthApiClient;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Utils\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OpenplatformOauthApiClientTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Logger $logger;

    /**
     * Setup mocks.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(Logger::class);
    }

    /**
     * Test that introspection endpoint is called correctly.
     */
    public function testTokenCallToOpenPlatform(): void
    {
        $openplatformOauthApiClient = $this->getOpenplatformOauthApiClient('');

        $response = $this->getMockUserResponse(200, true, 'now + 2 days', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);
        $this->httpClient->expects($this->once())->method('request')->with('POST', 'https://auth.test?access_token=12345678', [
            'auth_basic' => ['id', 'secret'],
        ]);

        $openplatformOauthApiClient->getUser('12345678');
    }

    /**
     * Test that exception is thrown if Open Platform does not return HTTP 200.
     */
    public function testRequestNot200(): void
    {
        $openplatformOauthApiClient = $this->getOpenplatformOauthApiClient('');

        $response = $this->getMockUserResponse(401, true, 'now + 2 days', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(OpenPlatformException::class);
        $this->expectExceptionMessage('Http call to Open Platform returned status: 401');

        $user = $openplatformOauthApiClient->getUser('Bearer 12345678');
    }

    /**
     * Test that exception is thrown if Open Platform does not return valid expire date.
     */
    public function testRequestInvalidExpire(): void
    {
        $openplatformOauthApiClient = $this->getOpenplatformOauthApiClient('');

        $response = $this->getMockUserResponse(200, true, 'invalid date', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(\Exception::class);
        //$this->expectExceptionMessage('Http call to Open Platform returned status: 401');

        $user = $openplatformOauthApiClient->getUser('Bearer 12345678');
    }

    /**
     * Test that correct exception is thrown if request throws exception.
     */
    public function testRequestException(): void
    {
        $openplatformOauthApiClient = $this->getOpenplatformOauthApiClient('');

        $response = $this->getMockUserResponse(401, true, 'now + 2 days', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willThrowException(new ClientException($response));

        $this->expectException(OpenPlatformException::class);
        $this->expectExceptionMessage('HTTP 401 returned for "https://auth.test".');

        $user = $openplatformOauthApiClient->getUser('Bearer 12345678');
    }

    /**
     * Test that exception is thrown if we receive an error from Open Platform.
     *
     * @throws Exception
     */
    public function testErrorToken(): void
    {
        $openplatformOauthApiClient = $this->getOpenplatformOauthApiClient('');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $json = '{
            "error":"Invalid client and/or secret"
        }';
        $response->method('getContent')->willReturn($json);

        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(OpenPlatformException::class);

        $user = $openplatformOauthApiClient->getUser('Bearer 12345678');
    }

    /**
     * Test that exception is thrown if we receive invalid json from Open Platform.
     *
     * @throws Exception
     */
    public function testInvalidJsonToken(): void
    {
        $openplatformOauthApiClient = $this->getOpenplatformOauthApiClient('');

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

        $this->expectException(OpenPlatformException::class);

        $user = $openplatformOauthApiClient->getUser('Bearer 12345678');
    }

    /**
     * Test that 'active' is set correctly for User.
     *
     * @throws OpenPlatformException
     */
    public function testActiveUser(): void
    {
        $openplatformOauthApiClient = $this->getOpenplatformOauthApiClient('');

        $response = $this->getMockUserResponse(200, true, 'now + 2 days', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $user = $openplatformOauthApiClient->getUser('12345678');
        $this->assertTrue($user->isActive(), 'User should be active');
    }

    /**
     * Test that 'active' is set correctly for User.
     *
     * @throws OpenPlatformException
     */
    public function testNotActiveUser(): void
    {
        $openplatformOauthApiClient = $this->getOpenplatformOauthApiClient('');

        $response = $this->getMockUserResponse(200, false, 'now + 2 days', 'client-id-hash', 'anonymous');
        $this->httpClient->method('request')->willReturn($response);

        $user = $openplatformOauthApiClient->getUser('12345678');
        $this->assertFalse($user->isActive(), 'User should not be active');
    }

    /**
     * Helper function to set up OpenplatformOauthApiClient with/without allowed clients.
     *
     * @param string $allowedClients
     *   Allow list of client id's. Supply an empty array to allow all.
     *
     * @return OpenplatformOauthApiClient
     *   A configured OpenplatformOauthApiClient
     */
    private function getOpenplatformOauthApiClient(string $allowedClients): OpenplatformOauthApiClient
    {
        return new OpenplatformOauthApiClient('id', 'secret', 'https://auth.test', $this->httpClient, $this->logger);
    }

    /**
     * Helper function to get mock user response.
     *
     * @param int $httpStatus
     *   The http status code for the response
     * @param bool $active
     *   Is the user active in Open Platform
     * @param string $expiresStr
     *   The expiry time of the token
     * @param string $clientId
     *   The users' client id
     * @param string $type
     *   The users type
     *
     * @return ResponseInterface
     *   A mock response with a json user object as content
     *
     * @throws \Exception
     */
    private function getMockUserResponse(int $httpStatus, bool $active, string $expiresStr, string $clientId, string $type): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($httpStatus);

        $map = [
            ['http_code', $httpStatus],
            ['url', 'https://auth.test'],
            ['response_headers', []],
        ];

        $response->method('getInfo')->willReturn($this->returnValueMap($map));
        try {
            $expires = new \DateTime($expiresStr, new \DateTimeZone('UTC'));
            $expiresStr = $expires->format('Y-m-d\TH:i:s.u\Z');
        } catch (\Exception $e) {
            // Allow the raw inout for testing invalid date strings
        }
        $active = $active ? 'true' : 'false';
        $json = '{
            "active": '.$active.',
            "clientId": "'.$clientId.'",
            "expires": "'.$expiresStr.'",
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
