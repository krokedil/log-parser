<?php
namespace Krokedil\LogParser\ResultHandlers;

use Krokedil\LogParser\Interfaces\LogResultHandlerInterface;
use Krokedil\LogParser\Interfaces\OutputLoggerInterface;

class StringResultHandler implements LogResultHandlerInterface {
    private array $all_lines = [];
    private OutputLoggerInterface $logger;

    public function __construct(OutputLoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function initialize(array $config = []): void {
        $this->all_lines = [];
        $this->logger->log("StringResultHandler initialized.");
    }

    public function handle_sorted_batch(array $lines, array $batch_context): void {
        if (!empty($lines)) {
            $this->logger->log("StringResultHandler received batch " . $batch_context['batch_id'] . " with " . count($lines) . " lines.");
            $this->all_lines = array_merge($this->all_lines, $lines);
        }
    }

    public function finalize(): void {
        $this->logger->log("StringResultHandler finalized. Total lines collected: " . count($this->all_lines));
    }

    public function get_result(): string {
        return implode("", $this->all_lines);
    }
}
