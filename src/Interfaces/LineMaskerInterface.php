<?php
namespace Krokedil\LogParser\Interfaces;

interface LineMaskerInterface {
	/**
	 * Mask the sensitive data in a log entry, which can span several lines.
	 *
	 * @param string $line The raw log entry, including its line ending.
	 * @return string The masked line, with the same line ending.
	 */
	public function mask( string $line ): string;
}
