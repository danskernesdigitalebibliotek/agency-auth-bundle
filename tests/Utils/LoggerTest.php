<?php

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests\Utils;

use DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\OpenPlatformException;
use DanskernesDigitaleBibliotek\AgencyAuthBundle\Utils\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LoggerTest extends TestCase
{
    public function testLogError(): void
    {
        $loggerInterfaceMock = $this->createMock(LoggerInterface::class);
        $loggerInterfaceMock->expects($this->once())->method('error')->with('DanskernesDigitaleBibliotek\AgencyAuthBundle\Tests\Utils\LoggerTest test');

        $logger = new Logger($loggerInterfaceMock);

        $logger->logError(self::class, 'test');
    }

    public function testLogException(): void
    {
        $loggerInterfaceMock = $this->createMock(LoggerInterface::class);
        $loggerInterfaceMock->expects($this->once())->method('error')->with('DanskernesDigitaleBibliotek\AgencyAuthBundle\Exception\OpenPlatformException Testing');

        $logger = new Logger($loggerInterfaceMock);

        $logger->logException(new OpenPlatformException('Testing', 1));
    }
}
