<?php
namespace Krokedil\LogParser\LogDataProviders;

use Exception;
use Krokedil\LogParser\Interfaces\LogDataProviderInterface;
use Krokedil\LogParser\Interfaces\OutputLoggerInterface;

class FileSystemLogDataProvider implements LogDataProviderInterface {
    private string $logs_path_pattern;
    private OutputLoggerInterface $logger;

    public function __construct(string $logs_path_pattern, OutputLoggerInterface $logger) {
        $this->logs_path_pattern = $logs_path_pattern;
        $this->logger = $logger;
    }

    public function get_log_lines(): iterable {
        $files = glob($this->logs_path_pattern);
        if ($files === false) {
            $this->logger->log("Error: Could not glob log path pattern: {$this->logs_path_pattern}");
            return; // Return empty iterable
        }
        if (empty($files)) {
            $this->logger->log("No log files found matching pattern: {$this->logs_path_pattern}");
            return;
        }

        $this->logger->log("Found files to process: " . implode(', ', $files));

        foreach ($files as $file_path) {
            $this->logger->log("Processing file: {$file_path}");
            $handle = @fopen($file_path, 'r');
            if (!$handle) {
                $this->logger->log("Warning: Could not open file {$file_path} for reading. Skipping.");
                continue;
            }

            try {
                while (($line = fgets($handle)) !== false) {
                    yield $line;
                }
            } finally {
                fclose($handle);
            }
        }
    }
}
