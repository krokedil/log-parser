<?php
namespace Krokedil\LogParser;

use DateTime;
use Krokedil\LogParser\Interfaces\LineMaskerInterface;
use Krokedil\LogParser\Interfaces\LogDataProviderInterface;
use Krokedil\LogParser\Interfaces\LogResultHandlerInterface;
use Krokedil\LogParser\Interfaces\OutputLoggerInterface;
use Krokedil\LogParser\Masking\WcLogLineMasker;

class LogParser {
    /**
     * The timestamp a log entry starts with, in the current and the pre 8.6 WooCommerce format.
     */
    const ENTRY_START = '/^(?:\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}|\d{2}-\d{2}-\d{4} @ \d{2}:\d{2}:\d{2})/';

    private LogDataProviderInterface $log_data_provider;
    private LogResultHandlerInterface $result_handler;
    private OutputLoggerInterface $output_logger;
    private array $terms;
    private bool $inclusive;
    private int $batch_size = 1000; // Configurable batch size for processing
    private LineMaskerInterface $line_masker;
    private bool $case_sensitive;

    /**
     * Constructor for LogParser.
     *
     * @param LogDataProviderInterface $log_data_provider Provider for log data.
     * @param LogResultHandlerInterface $result_handler Handler for parsed results.
     * @param OutputLoggerInterface $output_logger Logger for verbose output.
     * @param array $terms The terms to search for. Empty to return every line.
     * @param bool $inclusive Whether to search for all terms (true) or any term (false).
     * @param int $batch_size How many lines to collect before processing a batch.
     * @param LineMaskerInterface|null $line_masker Masks each matched line. Defaults to masking with WcLogLineMasker.
     * @param bool $case_sensitive Whether the terms must match the case of the log.
     */
    public function __construct(
        LogDataProviderInterface $log_data_provider,
        LogResultHandlerInterface $result_handler,
        OutputLoggerInterface $output_logger,
        array $terms,
        bool $inclusive = false,
        int $batch_size = 1000,
        ?LineMaskerInterface $line_masker = null,
        bool $case_sensitive = true
    ) {
        $this->log_data_provider = $log_data_provider;
        $this->result_handler    = $result_handler;
        $this->output_logger     = $output_logger;
        $this->terms             = $terms;
        $this->inclusive         = $inclusive;
        $this->batch_size        = $batch_size;
        $this->line_masker       = $line_masker ?? new WcLogLineMasker();
        $this->case_sensitive    = $case_sensitive;

        // Initial log messages
        $this->output_logger->log('LogParser initialized.');
        $this->output_logger->log('Searching for terms: ' . implode(', ', $this->terms));
        $this->output_logger->log('Inclusive search: ' . ($this->inclusive ? 'yes' : 'no'));
        $this->output_logger->log('Case sensitive: ' . ($this->case_sensitive ? 'yes' : 'no'));
    }

    /**
     * Generates a base filename stem using terms and current datetime.
     * Example: results_term1_term2_2025-05-16_10-30-00
     *
     * @return string The base filename stem.
     */
    private function generate_base_filename_stem(): string {
        $date_time    = date('Y-m-d_H-i-s');
        $terms_string = implode('_', $this->terms);
        $terms_string = preg_replace('/[^A-Za-z0-9_]/', '', $terms_string);
        return "results_{$terms_string}_{$date_time}";
    }

    /**
     * Parses logs using the configured provider and handler.
     */
    public function parse() {
        $handler_config = [];
        // If the handler might need a base filename (like FileResultHandler)
        // We generate it here and pass it during initialization.
        // Specific handlers can pick what they need from the config.
        $handler_config['base_filename_stem'] = $this->generate_base_filename_stem();

        $this->result_handler->initialize($handler_config);
        $this->output_logger->log('Starting log parsing...');

        $collected_lines = [];
        $batch_counter   = 0;
        $total_lines_matched_and_batched = 0;

        foreach ($this->get_entries() as $entry) {
            // Without search terms, every entry matches.
            $found = empty($this->terms) || ($this->inclusive ? $this->contains_all_terms($entry) : $this->contains_any_term($entry));
            // Match against the raw entry, so a search for an email or a token still finds it.
            if ($found) {
                $collected_lines[] = $this->line_masker->mask($entry);
            }

            if (count($collected_lines) >= $this->batch_size) {
                $this->process_and_handle_batch($collected_lines, $batch_counter, false);
                $total_lines_matched_and_batched += count($collected_lines);
                $collected_lines = []; // Reset for the next batch
                $batch_counter++;
            }
        }

        // Process any remaining lines after the loop
        if (!empty($collected_lines)) {
            $this->process_and_handle_batch($collected_lines, $batch_counter, true, ($total_lines_matched_and_batched === 0));
            $total_lines_matched_and_batched += count($collected_lines);
        }

        if ($total_lines_matched_and_batched === 0) {
            $this->output_logger->log('No results found matching the criteria.');
        }

        $this->result_handler->finalize();
        $this->output_logger->log('Log parsing finished.');
    }

    /**
     * Groups the provider's lines into log entries. A line that does not start with a timestamp
     * continues the entry before it, like the message and CONTEXT lines of a multi-line entry.
     *
     * @return iterable<string> Each entry, ending with a newline.
     */
    private function get_entries(): iterable {
        $entry = '';
        foreach ($this->log_data_provider->get_log_lines() as $line) {
            if ($entry !== '' && preg_match(self::ENTRY_START, $line)) {
                yield $entry;
                $entry = '';
            }

            // The last line of a file can lack its newline.
            if ($entry !== '' && substr($entry, -1) !== "\n") {
                $entry .= "\n";
            }
            $entry .= $line;
        }

        if ($entry !== '') {
            yield substr($entry, -1) === "\n" ? $entry : $entry . "\n";
        }
    }

    /**
     * Sorts, then sends a batch of lines to the result handler.
     *
     * @param array $lines The lines to process.
     * @param int $batch_counter The current batch number.
     * @param bool $is_final_batch_of_input True if this is the last batch from the input.
     * @param bool|null $is_only_batch True if this is the only batch processed for the entire input.
     */
    private function process_and_handle_batch(array &$lines, int $batch_counter, bool $is_final_batch_of_input, ?bool $is_only_batch = null) {
        if (empty($lines)) {
            return;
        }

        if ($is_only_batch === null) {
            // Determine if it's the only batch if not explicitly passed.
            // This happens if batch_counter is 0 AND it's the final batch from input.
            $is_only_batch = $batch_counter === 0 && $is_final_batch_of_input;
        }

        $this->output_logger->log("Preparing batch {$batch_counter} with " . count($lines) . " lines.");
        $this->sort_results($lines);

        $batch_context = [
            'batch_id' => $batch_counter,
            'is_final_input_batch' => $is_final_batch_of_input,
            'is_only_batch' => $is_only_batch,
            // base_filename_stem is already passed in initialize, but can be included if needed per batch
        ];

        $this->result_handler->handle_sorted_batch($lines, $batch_context);
    }

    /**
     * Sort the results by date and time.
     * WooCommerce logs start with "m-d-Y @ H:i:s" or "Y-m-dTH:i:s".
     *
     * @param array &$result The results to sort.
     */
    protected function sort_results(array &$result): void {
        usort(
            $result,
            function ($a, $b) {
                $pattern = '/(\d{2}-\d{2}-\d{4} @ \d{2}:\d{2}:\d{2})|(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/';
                preg_match($pattern, (string)$a, $matches_a);
                preg_match($pattern, (string)$b, $matches_b);

                if (empty($matches_a[0]) || empty($matches_b[0])) {
                    // If one or both lines don't have a recognizable timestamp, don't change their order relative to each other.
                    // Lines without timestamps might sort inconsistently relative to lines with timestamps.
                    return 0;
                }

                // Determine format and create DateTime objects
                $date_a_str = $matches_a[0];
                $date_b_str = $matches_b[0];

                $date_a = DateTime::createFromFormat('m-d-Y @ H:i:s', $date_a_str) ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $date_a_str);
                $date_b = DateTime::createFromFormat('m-d-Y @ H:i:s', $date_b_str) ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $date_b_str);

                if (!$date_a || !$date_b) {
                    return 0; // Should not happen if preg_match found something and formats are correct
                }

                return $date_a <=> $date_b;
            }
        );
    }

    /**
     * Check if a line contains any of the terms.
     *
     * @param string $line The line to check.
     * @return bool
     */
    protected function contains_any_term(string $line): bool {
        foreach ($this->terms as $term) {
            if ($this->contains_term($line, $term)) {
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
    protected function contains_all_terms(string $line): bool {
        foreach ($this->terms as $term) {
            if (!$this->contains_term($line, $term)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if a line contains a term, matching case unless the search is case insensitive.
     *
     * @param string $line The line to check.
     * @param string $term The term.
     * @return bool
     */
    protected function contains_term(string $line, string $term): bool {
        return ($this->case_sensitive ? strpos($line, $term) : mb_stripos($line, $term)) !== false;
    }
}
