<?php
/**
 * @file
 * Token authentication using 'adgangsplatform' introspection end-point.
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Security;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\UnsupportedCredentialsTypeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Class TokenAuthenticator.
 */
class TokenAuthenticator extends AbstractAuthenticator
{
    private const AUTH_HEADER = 'authorization';

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::AUTH_HEADER);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedCredentialsTypeException
     */
    public function authenticate(Request $request): Passport
    {
        $token = $this->getToken($request);

        return new SelfValidatingPassport(new UserBadge($token));
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => 'Authentication failed: '.$exception->getMessage(),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Get the bearer token from credentials.
     *
     * @param Request $request
     *   The http request
     *
     * @return string
     *   Token string
     *
     * @throws UnsupportedCredentialsTypeException
     */
    private function getToken(Request $request): string
    {
        $credentials = $request->headers->get(self::AUTH_HEADER);

        // Parse token information from the bearer authorization header.
        if (is_string($credentials) && 1 === preg_match('/Bearer\s(\w+)/', $credentials, $matches)) {
            return $matches[1];
        }

        throw new UnsupportedCredentialsTypeException('Only credentials of type string (e.g. bearer authorization header) supported');
    }
}
