<?php
namespace Krokedil\LogParser\Interfaces;

interface OutputLoggerInterface {
    /**
     * Log a message.
     *
     * @param string $message The message to log.
     */
    public function log(string $message): void;
}
