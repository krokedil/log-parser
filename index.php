<?php // phpcs:disable
/**
 * Loop all files in the logs folder and find the passed term.
 *
 * @param string $term The term to search for.
 *
 * @return array
 */
function find_files_with_transaction_id( $term ) {
	echo "Searching for term: $term\n";
	$files  = glob( __DIR__ . '/logs/*.log' );
	$result = array();
	foreach ( $files as $file ) {
		$content = file_get_contents( $file );
		if ( strpos( $content, $term ) !== false ) {
			echo "Found term $term in file: $file\n";
			$result[] = $file;
		}
	}
	return $result;
}

/**
 * Parse the log files and return each row that contains the term.
 *
 * @param string $term The term to search for.
 *
 * @return array
 */
function parse_log( $term ) {
	$files  = find_files_with_transaction_id( $term );
	$result = array();
	foreach ( $files as $file ) {
		$result[] = "From file: $file";
		$content  = file_get_contents( $file );
		$rows     = explode( "\n", $content );
		foreach ( $rows as $row ) {
			if ( strpos( $row, $term ) !== false ) {
				$result[] = $row;
			}
		}
	}
	return $result;
}

/**
 * Save all rows to a new file in the output folder.
 *
 * @param array $terms The arguments passed to the script.
 *
 * @return string
 */
function save_output( $terms ) {
	$rows = array();
	foreach ( $terms as $term ) {
		$rows = array_unique( array_merge( $rows, parse_log( $term ) ) );
	}

	// Create the output folder if it doesn't exist.
	if ( ! is_dir( 'output' ) ) {
		mkdir( 'output' );
	}
	$filename = implode( '-', $terms );
	// Strip any unwanted characters from the filename.
	$filename = preg_replace( '/[^a-z0-9-]/', '', strtolower( $filename ) );

	$file = __DIR__ . '/output/' . $filename . '.log';

	// Save the rows to the file.
	file_put_contents( $file, implode( "\n", $rows ) );

	return $file;
}

/**
 * Run the script.
 *
 * @param array $terms The arguments passed to the script.
 *
 * @return void
 */
function run( $terms ) {
	// Unset the filename from the arguments.
	unset( $terms[0] );

	$file = save_output( $terms );
	echo "The output has been saved to: $file\n";
}

// Run the script.
run( $argv );
