<?php
/**
 * @file
 * User with information obtained during authentication.
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class User.
 */
class User implements UserInterface
{
    private ?\DateTime $expires;
    private string $agency;
    private bool $active = false;
    private string $authType;
    private string $clientId;
    private ?string $token;

    /**
     * Get the users "password" expire date.
     */
    public function getExpires(): ?\DateTime
    {
        return $this->expires;
    }

    /**
     * Set the users "password" expire date.
     */
    public function setExpires(\DateTime $expires): void
    {
        $this->expires = $expires;
    }

    /**
     * Get the user's agency.
     */
    public function getAgency(): string
    {
        return $this->agency;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * Set the user's agency.
     */
    public function setAgency(string $agency): void
    {
        $this->agency = $agency;
    }

    /**
     * Get users authentication type.
     */
    public function getAuthType(): string
    {
        return $this->authType;
    }

    /**
     * Set users authentication type.
     */
    public function setAuthType(string $authType): void
    {
        $this->authType = $authType;
    }

    /**
     * Get users client id.
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Set users client id.
     */
    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles(): array
    {
        return ['ROLE_OPENPLATFORM_AGENCY'];
    }

    /**
     * {@inheritdoc}
     */
    public function getUserIdentifier(): string
    {
        return $this->agency;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials(): void
    {
        $this->token = null;
    }

    /**
     * @return ?string
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @param ?string $token
     */
    public function setToken(?string $token): void
    {
        $this->token = $token;
    }
}
