<?php
namespace Krokedil\LogParser\Masking;

use Krokedil\LogParser\Interfaces\LineMaskerInterface;

/**
 * Returns every line as it is. Only for local use, where the output never leaves the machine.
 */
class NullLineMasker implements LineMaskerInterface {
	/**
	 * Return the line unchanged.
	 *
	 * @param string $line The raw log line.
	 * @return string
	 */
	public function mask( string $line ): string {
		return $line;
	}
}
