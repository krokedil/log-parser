<?php
namespace Krokedil\LogParser\Interfaces;

interface LogResultHandlerInterface {
    /**
     * Initialize the handler with necessary configuration.
     *
     * @param array $config Configuration options for the handler.
     */
    public function initialize(array $config = []): void;

    /**
     * Handle a batch of sorted log lines.
     *
     * @param array $lines The sorted lines to handle.
     * @param array $batch_context Contextual information about the batch (e.g., id, if it's the only batch).
     */
    public function handle_sorted_batch(array $lines, array $batch_context): void;

    /**
     * Finalize the handling process.
     * This is called after all batches have been processed.
     */
    public function finalize(): void;

    /**
     * Get the final result, if applicable (e.g., a string from StringResultHandler).
     *
     * @return mixed
     */
    public function get_result();
}
