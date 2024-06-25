<?php
namespace Krokedil\LogParser;

use DateTime;
use Exception;

class LogParser {

	/**
	 * The path to the logs folder.
	 *
	 * @var string
	 */
	private $logs_path;

	/**
	 * The path to the output folder.
	 *
	 * @var string
	 */
	private $output_path;

	/**
	 * The terms to search for.
	 *
	 * @var array
	 */
	private $terms;

	/**
	 * The output file.
	 *
	 * @var string
	 */
	private $output_file;

	/**
	 * Whether to search for all terms or any term.
	 *
	 * @var bool
	 */
	private $inclusive;

	/**
	 * Whether to output verbose information.
	 *
	 * @var bool
	 */
	private $verbose;

	/**
	 * Parses the logs and gets all rows that contain either any or all of the terms.
	 *
	 * @param string $logs_path   The path to the logs folder.
	 * @param string $output_path The path to the output folder.
	 * @param array  $terms      The terms to search for.
	 * @param bool   $inclusive  Whether to search for all terms or any term. Default false.
	 * @param bool   $verbose    Whether to output verbose information. Default false.
	 */
	public function __construct( $logs_path, $output_path, $terms, $inclusive = false, $verbose = false ) {
		$this->logs_path   = rtrim( $logs_path, '/' ) . '/*.log';
		$this->output_path = rtrim( $output_path, '/' );
		$this->terms       = $terms;
		$this->inclusive   = $inclusive;
		$this->verbose     = $verbose;

		$date_time    = date( 'Y-m-d_H-i-s' );
		$terms_string = implode( '_', $terms );
		// Clear the output file name from any special characters.
		$terms_string      = preg_replace( '/[^A-Za-z0-9_]/', '', $terms_string );
		$this->output_file = "$this->output_path/results_{$terms_string}_{$date_time}";

		$this->verbose( 'Searching for terms: ' . implode( ', ', $terms ) );
		$this->verbose( 'Inclusive search: ' . ( $inclusive ? 'yes' : 'no' ) );
		$this->verbose( "Output file: $this->output_file" );
	}

	/**
	 * Find all rows from any .log file that contains any of the terms.
	 *
	 * @param array $terms The terms to search for.
	 * @throws Exception If the directory cannot be opened.
	 */
	public function parse() {
		$fileNr = 0;
		$files  = glob( $this->logs_path );
		$result = array();

		foreach ( $files as $file ) {
			$handle = fopen( $file, 'r' );
			if ( ! $handle ) {
				continue;
			}

			$lines = $this->get_lines( $handle );

			if ( ! empty( $lines ) ) {
				$result = array_merge( $result, $lines );
			}

			// If we have more than 1000 results, print the results to the output file and clear the array.
			if ( count( $result ) > 1000 ) {
				// Maybe sort the results by date and time before writing them to the file.
				$this->sort_results( $result );
				$this->verbose( "Writing results to file: {$this->output_file}.{$fileNr}" );
				$this->write_results( "{$this->output_file}.{$fileNr}", $result );
				$result = array();
				++$fileNr;
			}
		}

		// If we have any results left, print them to the output file.
		if ( ! empty( $result ) ) {
			// Maybe sort the results by date and time before writing them to the file.
			$this->sort_results( $result );
			$this->verbose( "Writing results to file: {$this->output_file}.{$fileNr}" );
			$filename = $fileNr > 0 ? "{$this->output_file}.{$fileNr}" : $this->output_file;
			$this->write_results( $filename, $result );
		} else {
			$this->verbose( 'No results found.' );
		}
	}

	/**
	 * Sort the results by date and time before writing them to the file.
	 * WooCommerce logs start with "m-d-Y @ H:i:s" and then the message.
	 *
	 * @param array $result The results to sort.
	 */
	protected function sort_results( &$result ) {
		usort(
			$result,
			function ( $a, $b ) {
				// Get either the old or new pattern of the datetime in the WooCommerce logs. Either 'm-d-Y @ H:i:s' or 'Y-m-dTH:i:s'.
				$pattern = '/(\d{2}-\d{2}-\d{4} @ \d{2}:\d{2}:\d{2})|(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/';
				preg_match( $pattern, $a, $matches_a );
				preg_match( $pattern, $b, $matches_b );

				// If we can't find a date, return 0.
				if ( empty( $matches_a ) || empty( $matches_b ) ) {
					return 0;
				}

				$date_a = DateTime::createFromFormat( 'm-d-Y @ H:i:s', $matches_a[0] ?? '' )
					?: DateTime::createFromFormat( 'Y-m-d\TH:i:s', $matches_a[0] ?? '' );
				$date_b = DateTime::createFromFormat( 'm-d-Y @ H:i:s', $matches_b[0] ?? '' )
					?: DateTime::createFromFormat( 'Y-m-d\TH:i:s', $matches_b[0] ?? '' );

				if ( ! $date_a || ! $date_b ) {
					return 0;
				}

				return $date_a <=> $date_b;
			}
		);
	}

	/**
	 * Get all lines from a file handle that either contains any or all terms.
	 *
	 * @param resource $handle The file handle.
	 * @return array
	 */
	protected function get_lines( $handle ) {
		$lines = array();
		while ( ( $line = fgets( $handle ) ) !== false ) {
			$found = $this->inclusive ? $this->contains_all_terms( $line ) : $this->contains_any_term( $line );
			if ( $found ) {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * Check if a line contains any of the terms.
	 *
	 * @param string $line The line to check.
	 * @return bool
	 */
	protected function contains_any_term( $line ) {
		foreach ( $this->terms as $term ) {
			if ( strpos( $line, $term ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a line contains all of the terms.
	 *
	 * @param string $line The line to check.
	 * @return bool
	 */
	protected function contains_all_terms( $line ) {
		foreach ( $this->terms as $term ) {
			if ( strpos( $line, $term ) === false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Write the parsed lines to a file.
	 *
	 * @param string $file  The file to write to.
	 * @param array  $lines The lines to write.
	 * @throws Exception If the file cannot be opened.
	 */
	protected function write_results( $file, $lines ) {
		// Ensure the output directory exists.
		if ( ! is_dir( $this->output_path ) ) {
			mkdir( $this->output_path, 0755, true );
		}

		$handle = fopen( $file . '.log', 'w' );
		if ( ! $handle ) {
			throw new Exception( "Could not open file: $file" );
		}

		foreach ( $lines as $line ) {
			fwrite( $handle, $line );
		}

		fclose( $handle );
	}

	/**
	 * Output verbose information.
	 *
	 * @param string $message The message to output.
	 */
	protected function verbose( $message ) {
		if ( $this->verbose ) {
			echo $message . "\n";
		}
	}
}
