<?php
/**
 * @file
 * Bundle definition file
 */

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\DependencyInjection\DanskernesDigitaleBibliotekAgencyAuthExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class DanskernesDigitaleBibliotekAgencyAuthBundle.
 */
class DanskernesDigitaleBibliotekAgencyAuthBundle extends Bundle
{
    /**
     * {@inheritdoc}
     *
     * Overridden to allow for the custom extension alias.
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new DanskernesDigitaleBibliotekAgencyAuthExtension();
        }

        return $this->extension;
    }
}
