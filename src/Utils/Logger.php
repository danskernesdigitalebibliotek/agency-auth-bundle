<?php

namespace DanskernesDigitaleBibliotek\AgencyAuthBundle\Utils;

use Psr\Log\LoggerInterface;

class Logger
{
    /**
     * Logger constructor.
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(private readonly ?LoggerInterface $logger)
    {
    }

    /**
     * Log error if a logger is configured.
     *
     * @param string $message
     *   The message to log
     */
    public function logError(string $message): void
    {
        $this->logger?->error(self::class.' '.$message);
    }

    /**
     * Log exception.
     *
     * @param \Throwable $e
     *   The Exception to log
     */
    public function logException(\Throwable $e): void
    {
        $message = get_class($e).' '.$e->getMessage();
        $this->logError($message);
    }
}
