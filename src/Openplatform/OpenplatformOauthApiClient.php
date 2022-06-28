<?php

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Openplatform;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\OpenPlatformException;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\User;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Utils\Logger;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenplatformOauthApiClient
{
    /**
     * OpenplatformOauthApiClient constructor.
     *
     * @param string $clientId
     *   Open Platform id
     * @param string $clientSecret
     *   Open Platform secret
     * @param string $endPoint
     *   Open Platform introspection URL
     * @param HttpClientInterface $client
     *   Http client for calls to Open Platform
     * @param Logger $logger
     *   Bundle logger service
     */
    public function __construct(private readonly string $clientId, private readonly string $clientSecret, private readonly string $endPoint, private readonly HttpClientInterface $client, private readonly Logger $logger)
    {
    }

    /**
     * Get user data from Openplatform.
     *
     * @param string $token
     *   The users access token
     *
     * @return User
     *   The User object
     *
     * @throws OpenPlatformException
     */
    public function getUser(string $token): User
    {
        $userData = $this->fetchUserData($token);

        return $this->createUser($userData, $token);
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
     * @throws OpenPlatformException
     */
    private function createUser(\stdClass $userData, string $token): User
    {
        $user = new User();
        $user->setToken($token);

        // E.g. "expires": "2020-07-04T05:36:24.083Z",
        try {
            $tokenExpireDataTime = new \DateTime((string) $userData->expires);
            $user->setExpires($tokenExpireDataTime);
        } catch (\Exception $e) {
            throw new OpenPlatformException('Exception from \DateTime(): '.$e->getMessage(), (int) $e->getCode(), $e);
        }

        if (isset($userData->clientId) && is_string($userData->clientId)) {
            $user->setClientId($userData->clientId);
        }

        if (isset($userData->agency) && is_string($userData->agency)) {
            $user->setAgency($userData->agency);
        }

        if (isset($userData->type) && is_string($userData->type)) {
            $user->setAuthType($userData->type);
        }

        if (isset($userData->active) && is_bool($userData->active)) {
            $user->setActive($userData->active);
        }

        return $user;
    }

    /**
     * Get user data from introspection endpoint.
     *
     * @param string $token
     *   The auth token
     *
     * @return \stdClass
     *   A stdClass object with user data or null
     *
     * @throws OpenPlatformException
     */
    private function fetchUserData(string $token): \stdClass
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
                $message = 'Http call to Open Platform returned status: '.$response->getStatusCode();
                $this->logger->logError(self::class, $message);

                throw new OpenPlatformException($message);
            }

            $content = $response->getContent();
            $data = \json_decode($content, false, 512, JSON_THROW_ON_ERROR);
            \assert($data instanceof \stdClass, new OpenPlatformException('json_decode returned data of unknown type'));

            // Error from Open Platform
            if (isset($data->error)) {
                $message = 'Token call to Open Platform returned error: '.(string) $data->error;
                $this->logger->logError(self::class, $message);

                throw new OpenPlatformException($message);
            }

            return $data;
        } catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|\JsonException $e) {
            $this->logger->logException($e);

            throw new OpenPlatformException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
