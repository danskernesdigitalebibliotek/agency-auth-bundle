<?php

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Security;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\NotImplementedException;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\OpenPlatformException;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Openplatform\OpenplatformOauthApiClient;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Utils\Logger;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class OpenPlatformUserProvider implements UserProviderInterface
{
    private const USER_CACHE_MAX_LIFETIME = 'P1D';

    private array $allowedClients = [];

    /**
     * OpenPlatformUserProvider constructor.
     *
     * @param string $openplatformAllowedClients
     *   Allow list of client id's. Supply an empty array to allow all
     * @param OpenplatformOauthApiClient $openplatformOauthApiClient
     *   Openplatform introspection api client
     * @param AdapterInterface|null $cache
     *   Cache Adapter for caching tokens/users
     * @param Logger $logger
     *   Logger for error logging
     */
    public function __construct(
        string $openplatformAllowedClients,
        private readonly OpenplatformOauthApiClient $openplatformOauthApiClient,
        private readonly Logger $logger,
        private readonly ?AdapterInterface $cache = null
    )
    {
        $this->allowedClients = empty($openplatformAllowedClients) ? [] : array_map('trim', explode(',', $openplatformAllowedClients));
    }

    /**
     * {@inheritDoc}
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $token = $identifier;

        try {
            $user = $this->getCachedUser($token);

            if (null === $user) {
                $user = $this->openplatformOauthApiClient->getUser($token);
                $this->cacheUser($token, $user);
            }

            // Token expired
            $now = new \DateTime();
            if ($user->getExpires() < $now) {
                throw new UserNotFoundException('User expired. User loaded from cache for agency id: '.$identifier);
            }

            // Confirm that user's client id is allowed.
            if (!empty($this->allowedClients) && !in_array($user->getClientId(), $this->allowedClients)) {
                throw new UserNotFoundException('User client id is not on allow client list: '.$user->getClientId());
            }

            // Token not active at the introspection end-point.
            if (!$user->isActive()) {
                throw new UserNotFoundException('User/Token not active at the introspection end-point: '.$identifier);
            }

            // Unknown format/token type
            if ('anonymous' !== $user->getAuthType()) {
                $message = 'Token call to Open Platform returned unknown type: '.$user->getAuthType();
                $this->logger->logError(self::class, $message);

                throw new UserNotFoundException($message);
            }

            return $user;
        } catch (OpenPlatformException $e) {
            throw new UserNotFoundException('No user found for agency id: '.$identifier, $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        // If your firewall is "stateless: true" (for a pure API), this
        // method is not called.
        throw new NotImplementedException('Method "refreshUser" not implemented. Please only use the OpenPlatformUserProvider in a stateless firewall');
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * Cache user if cache is configured.
     *
     * @param string $token
     *   The auth token to use as cache key
     * @param User $user
     *   The user object to cache
     */
    private function cacheUser(string $token, User $user): void
    {
        if ($this->cache) {
            // Try getting item from cache.
            try {
                $item = $this->cache->getItem($token);

                // If the default expire for token cache (1 day) is shorter than the tokens remaining
                // expire use the tokens expire timestamp.
                $endOfLife = new \DateTime();
                $endOfLife->add(new \DateInterval(self::USER_CACHE_MAX_LIFETIME));

                if ($user->getExpires() < $endOfLife) {
                    $item->expiresAt($user->getExpires());
                } else {
                    $item->expiresAt($endOfLife);
                }

                // Store access token in local cache.
                $item->set($user);
                $this->cache->save($item);
            } catch (InvalidArgumentException $e) {
                $this->logger->logException($e);
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
                    $user = $item->get();
                    \assert($user instanceof User, new OpenPlatformException('Unknown User class returned form cache'));

                    return $user;
                }
            } catch (InvalidArgumentException $e) {
                $this->logger->logException($e);
            }
        }

        return null;
    }
}
