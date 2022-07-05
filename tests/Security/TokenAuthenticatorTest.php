<?php

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests\Security;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\UnsupportedCredentialsTypeException;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\TokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class TokenAuthenticatorTest extends TestCase
{
    public function testSupports()
    {
        $tokenAuthenticator = new TokenAuthenticator();

        $request1 = $this->createMock(Request::class);
        $request1->headers = $this->createMock(HeaderBag::class);
        $request1->headers->method('has')->willReturn(true);

        $this->assertTrue($tokenAuthenticator->supports($request1));

        $request2 = $this->createMock(Request::class);
        $request2->headers = $this->createMock(HeaderBag::class);
        $request2->headers->method('has')->willReturn(false);

        $this->assertFalse($tokenAuthenticator->supports($request2));
    }

    public function testAuthenticateFailsForBadHeader(): void
    {
        $tokenAuthenticator = new TokenAuthenticator();

        $request = $this->createMock(Request::class);
        $request->headers = $this->createMock(HeaderBag::class);
        $request->headers->method('has')->willReturn(true);
        $request->headers->method('get')->willReturn('');

        $this->expectException(UnsupportedCredentialsTypeException::class);

        $tokenAuthenticator->authenticate($request);
    }

    public function testAuthenticate(): void
    {
        $tokenAuthenticator = new TokenAuthenticator();

        $request = $this->createMock(Request::class);
        $request->headers = $this->createMock(HeaderBag::class);
        $request->headers->method('has')->willReturn(true);
        $request->headers->method('get')->willReturn('Bearer 12345678');

        $passport = $tokenAuthenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals('12345678', $userBadge->getUserIdentifier());
    }

    public function testOnAuthenticationSuccess(): void
    {
        $tokenAuthenticator = new TokenAuthenticator();

        $request = $this->createMock(Request::class);
        $token = $this->createMock(TokenInterface::class);

        $this->assertNull($tokenAuthenticator->onAuthenticationSuccess($request, $token, 'firewall'));
    }

    public function testOnAuthenticationFailure(): void
    {
        $tokenAuthenticator = new TokenAuthenticator();

        $request = $this->createMock(Request::class);
        $exception = new AuthenticationException('test message');

        $response = $tokenAuthenticator->onAuthenticationFailure($request, $exception);

        $this->assertInstanceOf(JsonResponse::class, $response);

        $content = $response->getContent();
        $this->assertJson($content);
        $this->assertJsonStringEqualsJsonString('{"message":"Authentication failed: test message"}', $content);
    }
}
