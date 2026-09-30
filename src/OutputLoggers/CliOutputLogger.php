<?php
namespace Krokedil\LogParser\OutputLoggers;

use Krokedil\LogParser\Interfaces\OutputLoggerInterface;


class CliOutputLogger implements OutputLoggerInterface {
    public function log(string $message): void {
        echo "$message\n";
    }
}
