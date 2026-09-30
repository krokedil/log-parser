<?php
/**
 * CLI entry point: composer parse "term1" "term2".
 *
 * @package Krokedil\LogParser
 */

use GetOpt\GetOpt;
use GetOpt\Command;
use GetOpt\Operand;
use GetOpt\Option;

use Krokedil\LogParser\LogDataProviders\FileSystemLogDataProvider;
use Krokedil\LogParser\LogParser;
use Krokedil\LogParser\Masking\NullLineMasker;
use Krokedil\LogParser\OutputLoggers\CliOutputLogger;
use Krokedil\LogParser\ResultHandlers\FileResultHandler;

require_once 'vendor/autoload.php';

$terms_operand = Operand::create( 'terms', Operand::MULTIPLE );

$getopt = new Getopt();

$getopt->addCommand(
	Command::create(
		'parse',
		'parse',
		array(
			Option::create( 'l', 'logs', Getopt::REQUIRED_ARGUMENT )->setDescription( 'Logs folder path' ),
			Option::create( 'o', 'output', Getopt::REQUIRED_ARGUMENT )->setDescription( 'Output folder path' ),
			Option::create( 'v', 'verbose', Getopt::NO_ARGUMENT )->setDescription( 'Verbose mode' ),
			Option::create( 'i', 'inclusive', Getopt::NO_ARGUMENT )->setDescription( 'Inclusive mode, will only get lines that contain all the terms passed.' ),
			Option::create( 'c', 'case-insensitive', Getopt::NO_ARGUMENT )->setDescription( 'Match the terms regardless of case.' ),
			Option::create( null, 'no-mask', Getopt::NO_ARGUMENT )->setDescription( 'Output the matched rows without masking personal data and credentials. Only for local use.' ),
			Option::create( 'h', 'help', Getopt::NO_ARGUMENT )->setDescription( 'Show help text' ),
		)
	)
	->addOperand( $terms_operand )
	->setDescription( "Parse the logs and get all rows that contain either any or all of the terms.\nExample:\n   php index.php parse term1 term2 term3" )
	->setShortDescription( 'Parse logs' )
);

try {
	$getopt->process();
} catch ( \GetOpt\ArgumentException $exception ) {
	echo $exception->getMessage() . PHP_EOL;
}

$command = $getopt->getCommand();
if ( ! $command ) {
	$getopt->getHelpText();
} else {
	call_user_func( $command->getHandler(), $getopt->getOptions(), $getopt->getOperands() );
}

/**
 * Run the parse command.
 *
 * @param array $flags The options passed.
 * @param array $terms The terms to search for.
 * @return void
 */
function parse( $flags, $terms ) {
	global $getopt;
	$help = isset( $flags['help'] );

	if ( $help ) {
		echo $getopt->getHelpText();
		return;
	}

	$verbose        = isset( $flags['verbose'] );
	$inclusive      = isset( $flags['inclusive'] );
	$no_mask        = isset( $flags['no-mask'] );
	$case_sensitive = ! isset( $flags['case-insensitive'] );

	if ( $verbose ) {
		echo "Verbose mode\n";
	}

	$logs_dir   = $flags['logs'] ?? __DIR__ . '/logs/*.log';
	$output_dir = $flags['output'] ?? __DIR__ . '/output';

	$logger = new CliOutputLogger(); // For console output.

	$data_provider  = new FileSystemLogDataProvider( $logs_dir, $logger );
	$result_handler = new FileResultHandler( $output_dir, $logger );

	$parser = new LogParser(
		$data_provider,
		$result_handler,
		$logger,
		$terms,
		$inclusive,
		1000,
		$no_mask ? new NullLineMasker() : null,
		$case_sensitive
	);

	try {
		$parser->parse();
		$logger->log( 'CLI parsing complete. Result: ' . $result_handler->get_result() );
	} catch ( Exception $e ) {
		$logger->log( 'An error occurred: ' . $e->getMessage() );
	}
}
