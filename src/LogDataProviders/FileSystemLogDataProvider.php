<?php
namespace Krokedil\LogParser\LogDataProviders;

use Exception;
use Krokedil\LogParser\Interfaces\LogDataProviderInterface;
use Krokedil\LogParser\Interfaces\OutputLoggerInterface;

/**
 * Reads log lines from the files matching a glob pattern.
 */
class FileSystemLogDataProvider implements LogDataProviderInterface {
	/**
	 * The glob pattern for the log files.
	 *
	 * @var string
	 */
	private string $logs_path_pattern;

	/**
	 * Logger for progress messages.
	 *
	 * @var OutputLoggerInterface
	 */
	private OutputLoggerInterface $logger;

	/**
	 * Constructor for FileSystemLogDataProvider.
	 *
	 * @param string                $logs_path_pattern The glob pattern for the log files.
	 * @param OutputLoggerInterface $logger            Logger for progress messages.
	 */
	public function __construct( string $logs_path_pattern, OutputLoggerInterface $logger ) {
		$this->logs_path_pattern = $logs_path_pattern;
		$this->logger            = $logger;
	}

	/**
	 * Get the lines of every matching file, one file after the other.
	 *
	 * @return iterable<string>
	 */
	public function get_log_lines(): iterable {
		$files = glob( $this->logs_path_pattern );
		if ( false === $files ) {
			$this->logger->log( "Error: Could not glob log path pattern: {$this->logs_path_pattern}" );
			return; // Return empty iterable.
		}
		if ( empty( $files ) ) {
			$this->logger->log( "No log files found matching pattern: {$this->logs_path_pattern}" );
			return;
		}

		$this->logger->log( 'Found files to process: ' . implode( ', ', $files ) );

		foreach ( $files as $file_path ) {
			$this->logger->log( "Processing file: {$file_path}" );
			$handle = @fopen( $file_path, 'r' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The failure is logged below.
			if ( ! $handle ) {
				$this->logger->log( "Warning: Could not open file {$file_path} for reading. Skipping." );
				continue;
			}

			try {
				while ( ! feof( $handle ) ) {
					$line = fgets( $handle );
					if ( false !== $line ) {
						yield $line;
					}
				}
			} finally {
				fclose( $handle );
			}
		}
	}
}
