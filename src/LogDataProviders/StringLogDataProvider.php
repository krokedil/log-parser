<?php
namespace Krokedil\LogParser\LogDataProviders;

use Krokedil\LogParser\Interfaces\LogDataProviderInterface;

/**
 * Reads log lines from a string.
 */
class StringLogDataProvider implements LogDataProviderInterface {
	/**
	 * The lines, each ending with a newline.
	 *
	 * @var string[]
	 */
	private array $lines;

	/**
	 * Constructor for StringLogDataProvider.
	 *
	 * @param string $log_content The full log content as a single string.
	 */
	public function __construct( string $log_content ) {
		// Normalize line endings and split into an array.
		$log_content = str_replace( [ "\r\n", "\r" ], "\n", $log_content );
		$this->lines = explode( "\n", $log_content );
		// Filter out any empty lines that might result from multiple newlines.
		$this->lines = array_filter(
			$this->lines,
			function ( $line ) {
				return '' !== $line;
			}
		);
		// Re-add newline characters to each line to mimic fgets behavior.
		$this->lines = array_map(
			function ( $line ) {
				return $line . "\n";
			},
			$this->lines
		);
	}

	/**
	 * Get the lines.
	 *
	 * @return iterable<string>
	 */
	public function get_log_lines(): iterable {
		foreach ( $this->lines as $line ) {
			yield $line;
		}
	}
}
