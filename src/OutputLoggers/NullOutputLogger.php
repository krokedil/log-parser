<?php
namespace Krokedil\LogParser\OutputLoggers;

use Krokedil\LogParser\Interfaces\OutputLoggerInterface;

/**
 * Discards progress messages.
 */
class NullOutputLogger implements OutputLoggerInterface {
	/**
	 * Discard a message.
	 *
	 * @param string $message The message.
	 * @return void
	 */
	public function log( string $message ): void {
		// No operation performed.
	}
}
