<?php
namespace Krokedil\LogParser\ResultHandlers;

use Krokedil\LogParser\Interfaces\LogResultHandlerInterface;
use Krokedil\LogParser\Interfaces\OutputLoggerInterface;

/**
 * Collects the matched lines into one string.
 */
class StringResultHandler implements LogResultHandlerInterface {
	/**
	 * The lines collected so far.
	 *
	 * @var string[]
	 */
	private array $all_lines = [];

	/**
	 * Logger for progress messages.
	 *
	 * @var OutputLoggerInterface
	 */
	private OutputLoggerInterface $logger;

	/**
	 * Constructor for StringResultHandler.
	 *
	 * @param OutputLoggerInterface $logger Logger for progress messages.
	 */
	public function __construct( OutputLoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Reset the collected lines.
	 *
	 * @param array $config Not used.
	 * @return void
	 */
	public function initialize( array $config = [] ): void {
		$this->all_lines = [];
		$this->logger->log( 'StringResultHandler initialized.' );
	}

	/**
	 * Add a batch of lines.
	 *
	 * @param array $lines         The sorted lines.
	 * @param array $batch_context Uses 'batch_id'.
	 * @return void
	 */
	public function handle_sorted_batch( array $lines, array $batch_context ): void {
		if ( ! empty( $lines ) ) {
			$this->logger->log( 'StringResultHandler received batch ' . $batch_context['batch_id'] . ' with ' . count( $lines ) . ' lines.' );
			$this->all_lines = array_merge( $this->all_lines, $lines );
		}
	}

	/**
	 * Log how many lines were collected.
	 *
	 * @return void
	 */
	public function finalize(): void {
		$this->logger->log( 'StringResultHandler finalized. Total lines collected: ' . count( $this->all_lines ) );
	}

	/**
	 * The collected lines as one string.
	 *
	 * @return string
	 */
	public function get_result(): string {
		return implode( '', $this->all_lines );
	}
}
