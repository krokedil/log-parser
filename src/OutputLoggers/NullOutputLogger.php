<?php
namespace Krokedil\LogParser\OutputLoggers;

use Krokedil\LogParser\Interfaces\OutputLoggerInterface;


class NullOutputLogger implements OutputLoggerInterface {
    public function log(string $message): void {
        // No operation performed
    }
}
