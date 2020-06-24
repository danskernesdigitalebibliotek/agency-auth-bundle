<?php
/**
 * @file
 * Token authentication using 'adgangsplatform' introspection end-point.
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Security;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\UnsupportedCredentialsTypeException;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class TokenAuthenticator.
 */
class TokenAuthenticator extends AbstractGuardAuthenticator
{
    private $client;
    private $cache;
    private $clientId;
    private $clientSecret;
    private $allowedClients;
    private $endPoint;
    private $logger;

    private const TOKEN_CACHE_MAX_LIFETIME = 'P1D';

    /**
     * TokenAuthenticator constructor.
     *
     * @param string $openplatformId
     *   Open Platform id
     * @param string $openplatformSecret
     *   Open Platform secret
     * @param string $openplatformIntrospectionUrl
     *   Open Platform introspection URL
     * @param array $openplatformAllowedClients
     *   An allow list of client id's. Supply an empty array to allow all.
     * @param HttpClientInterface $httpClient
     *   Http client for calls to Open Platform
     * @param AdapterInterface|null $tokenCache
     *   Cache Adapter for caching tokens/users
     * @param LoggerInterface|null $logger
     *   Logger for error logging
     */
    public function __construct(string $openplatformId, string $openplatformSecret, string $openplatformIntrospectionUrl, array $openplatformAllowedClients, HttpClientInterface $httpClient, AdapterInterface $tokenCache = null, LoggerInterface $logger = null)
    {
        $this->clientId = $openplatformId;
        $this->clientSecret = $openplatformSecret;
        $this->endPoint = $openplatformIntrospectionUrl;
        $this->allowedClients = $openplatformAllowedClients;

        $this->client = $httpClient;
        $this->cache = $tokenCache;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request)
    {
        return $request->headers->has('authorization');
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(Request $request)
    {
        return $request->headers->get('authorization');
    }

    /**
     * {@inheritdoc}
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $token = $this->getToken($credentials);
        $user = $this->getCachedUser($token);

        if (null === $user) {
            $userData = $this->fetchUserData($token);
            if (null === $userData) {
                return null;
            }
            $user = $this->createUser($userData, $token);
            $this->cacheUser($user, $token);
        }

        // Token expired
        $now = new \DateTime();
        if ($user->getExpires() < $now) {
            return null;
        }

        // Confirm that user's client id is allowed.
        if (!empty($this->allowedClients) && !in_array($user->getClientId(), $this->allowedClients)) {
            return null;
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        // In case of a token, no credential check is needed.
        // Return `true` to cause authentication success
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $data = [
            'message' => 'Authentication failed',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * {@inheritdoc}
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = [
            'message' => 'Authentication Required',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsRememberMe()
    {
        return false;
    }

    /**
     * Get the bearer token from credentials.
     *
     * @param mixed $credentials
     *   Request credentials
     *
     * @return string|null
     *   Token string if found, null if no token or empty credentials
     *
     * @throws UnsupportedCredentialsTypeException
     */
    private function getToken($credentials): ?string
    {
        if (null === $credentials) {
            // The token header was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            return null;
        }

        // Parse token information from the bearer authorization header.
        if (is_string($credentials) && 1 === preg_match('/Bearer\s(\w+)/', $credentials, $matches)) {
            if (2 !== count($matches)) {
                return null;
            }

            return $matches[1];
        }

        throw new UnsupportedCredentialsTypeException('Only credentials of type string (e.g. bearer authorization header) supported');
    }

    /**
     * Cache user if cache is configured.
     *
     * @param User $user
     *   The user object to cache
     * @param string $token
     *   The auth token to use as cache key
     */
    private function cacheUser(User $user, string $token): void
    {
        if ($this->cache) {
            // Try getting item from cache.
            try {
                $item = $this->cache->getItem($token);

                // If the default expire for token cache (1 day) is shorter than the tokens remaining
                // expire use the tokens expire timestamp.
                $endOfLife = new \DateTime();
                $endOfLife->add(new \DateInterval(self::TOKEN_CACHE_MAX_LIFETIME));

                if ($user->getExpires() < $endOfLife) {
                    $item->expiresAt($user->getExpires());
                } else {
                    $item->expiresAt($endOfLife);
                }

                // Store access token in local cache.
                $item->set($user);
                $this->cache->save($item);
            } catch (InvalidArgumentException $e) {
                $this->logException($e);
            }
        }
    }

    /**
     * Get user from cache.
     *
     * @param string $token
     *   The auth token
     *
     * @return User|null
     *   The cached User or null
     */
    private function getCachedUser(string $token): ?User
    {
        if ($this->cache) {
            // Try getting item from cache.
            try {
                $item = $this->cache->getItem($token);

                if ($item->isHit()) {
                    /* @var User $user */
                    $user = $item->get();

                    return $user;
                }
            } catch (InvalidArgumentException $e) {
                $this->logException($e);
            }
        }

        return null;
    }

    /**
     * Get user data from introspection endpoint.
     *
     * @param string $token
     *   The auth token
     *
     * @return \stdClass|null
     *   A stdClass object with user data or null
     */
    private function fetchUserData(string $token): ?\stdClass
    {
        try {
            $response = $this->client->request(
                'POST',
                $this->endPoint.'?access_token='.$token,
                [
                    'auth_basic' => [$this->clientId, $this->clientSecret],
                ]
            );

            if (200 !== $response->getStatusCode()) {
                $this->logError('Http call to Open Platform returned status: '.$response->getStatusCode());

                return null;
            }

            $content = $response->getContent();
            $data = json_decode($content, false, 512, JSON_THROW_ON_ERROR);

            // Error from Open Platform
            if (isset($data->error)) {
                $this->logError('Token call to Open Platform returned error: '.$data->error);

                return null;
            }

            // Unknown format/token type
            if (isset($data->type) && 'anonymous' !== $data->type) {
                $this->logError('Token call to Open Platform returned unknown type: '.$data->type);

                return null;
            }

            // Token not active at the introspection end-point.
            if (isset($data->active) && false === $data->active) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            $this->logException($e);

            return null;
        }
    }

    /**
     * Create user object from introspection data.
     *
     * @param \stdClass $userData
     *   A stdClass object with user data
     * @param string $token
     *   The auth token
     *
     * @return User
     *   A User object
     *
     * @throws \Exception
     */
    private function createUser(\stdClass $userData, string $token): User
    {
        // E.g. "expires": "2020-07-04T05:36:24.083Z",
        $tokenExpireDataTime = new \DateTime($userData->expires);

        $user = new User();
        $user->setPassword($token);
        $user->setExpires($tokenExpireDataTime);
        $user->setAgency($userData->agency);
        $user->setAuthType($userData->type);
        $user->setClientId($userData->clientId);

        return $user;
    }

    /**
     * Log error if a logger is configured.
     *
     * @param string $message
     *   The message to log
     */
    private function logError(string $message): void
    {
        if ($this->logger) {
            $this->logger->error(self::class.' '.$message);
        }
    }

    /**
     * Log exception.
     *
     * @param \Exception $e
     *   The Exception to log
     */
    private function logException(\Exception $e): void
    {
        $message = get_class($e).' '.$e->getMessage();
        $this->logError($message);
    }
}
