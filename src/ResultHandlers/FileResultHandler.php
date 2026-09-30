<?php
namespace Krokedil\LogParser\ResultHandlers;

use Exception;
use Krokedil\LogParser\Interfaces\LogResultHandlerInterface;
use Krokedil\LogParser\Interfaces\OutputLoggerInterface;

class FileResultHandler implements LogResultHandlerInterface {
    private string $output_directory;
    private OutputLoggerInterface $logger;
    private string $base_filename_stem;
    private int $files_written = 0;

    public function __construct(string $output_directory, OutputLoggerInterface $logger) {
        $this->output_directory = rtrim($output_directory, '/\\');
        $this->logger = $logger;
    }

    public function initialize(array $config = []): void {
        $this->base_filename_stem = $config['base_filename_stem'] ?? 'results_unknown_' . date('Y-m-d_H-i-s');
        $this->logger->log("FileResultHandler initialized. Output directory: {$this->output_directory}, Base stem: {$this->base_filename_stem}");

        if (!is_dir($this->output_directory)) {
            if (!mkdir($this->output_directory, 0755, true)) {
                throw new Exception("Could not create output directory: {$this->output_directory}");
            }
            $this->logger->log("Created output directory: {$this->output_directory}");
        }
    }

    public function handle_sorted_batch(array $lines, array $batch_context): void {
        if (empty($lines)) {
            return;
        }

        $filename_part = $this->base_filename_stem;
        if (!$batch_context['is_only_batch']) {
            // Append batch ID if it's not the only batch, or if it is but part of a larger potential set
            $filename_part .= '.' . $batch_context['batch_id'];
        }

        $output_filepath = $this->output_directory . DIRECTORY_SEPARATOR . $filename_part . '.log';

        $this->logger->log("Writing " . count($lines) . " lines to: {$output_filepath}");

        $handle = @fopen($output_filepath, 'w');
        if (!$handle) {
            // Log error and potentially throw, or handle more gracefully
            $this->logger->log("Error: Could not open file for writing: {$output_filepath}");
            throw new Exception("Could not open file for writing: {$output_filepath}");
        }

        try {
            foreach ($lines as $line) {
                fwrite($handle, $line);
            }
        } finally {
            fclose($handle);
            $this->files_written++;
        }
    }

    public function finalize(): void {
        $this->logger->log("FileResultHandler finalized. Total files/batches written: {$this->files_written}");
    }

    public function get_result() {
        // File handler doesn't typically return a string result, but path(s) or status
        return "{$this->files_written} file(s) written to {$this->output_directory}.";
    }
}
