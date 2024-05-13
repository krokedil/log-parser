<?php
require_once 'vendor/autoload.php';

use GetOpt\GetOpt;
use GetOpt\Command;
use GetOpt\Operand;
use GetOpt\Option;

$termsOperand = Operand::create( 'terms', Operand::MULTIPLE );

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
			Option::create( 'h', 'help', Getopt::NO_ARGUMENT )->setDescription( 'Show help text' ),
		)
	)
	->addOperand( $termsOperand )
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

function parse( $flags, $terms ) {
	global $getopt;
	$help = isset( $flags['help'] );

	if ( $help ) {
		echo $getopt->getHelpText();
		return;
	}

	$verbose   = isset( $flags['verbose'] );
	$inclusive = isset( $flags['inclusive'] );

	if ( $verbose ) {
		echo "Verbose mode\n";
	}

	$logs_dir   = $flags['logs'] ?? __DIR__ . '/logs';
	$output_dir = $flags['output'] ?? __DIR__ . '/output';

	$log_parser = new \Krokedil\LogParser\LogParser( $logs_dir, $output_dir, $terms, $inclusive, $verbose );
	$log_parser->parse();
}
