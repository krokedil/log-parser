<?php
namespace Krokedil\LogParser\Interfaces;

interface LineMaskerInterface {
	/**
	 * Mask the sensitive data in a single log line.
	 *
	 * @param string $line The raw log line, including its line ending.
	 * @return string The masked line, with the same line ending.
	 */
	public function mask( string $line ): string;
}
