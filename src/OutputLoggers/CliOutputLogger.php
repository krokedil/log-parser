<?php
namespace Krokedil\LogParser\OutputLoggers;

use Krokedil\LogParser\Interfaces\OutputLoggerInterface;

/**
 * Writes progress messages to the console.
 */
class CliOutputLogger implements OutputLoggerInterface {
	/**
	 * Write a message to the console.
	 *
	 * @param string $message The message.
	 * @return void
	 */
	public function log( string $message ): void {
		echo "$message\n";
	}
}
