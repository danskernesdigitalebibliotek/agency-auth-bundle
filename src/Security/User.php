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
    private ?string $password;
    private ?\DateTime $expires;
    private string $agency;
    private string $authType;
    private string $clientId;

    /**
     * Get this users "password" expire date.
     */
    public function getExpires(): ?\DateTime
    {
        return $this->expires;
    }

    /**
     * Set this users "password" expire date.
     */
    public function setExpires(\DateTime $expires): void
    {
        $this->expires = $expires;
    }

    /**
     * Get the users agency.
     */
    public function getAgency(): string
    {
        return $this->agency;
    }

    /**
     * Set the users agency.
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
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password): void
    {
        $this->password = $password;
    }

    /**
     * {@inheritdoc}
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername(): string
    {
        return $this->agency;
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
    public function eraseCredentials(): ?string
    {
        return null;
    }
}
