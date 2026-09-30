<?php
namespace Krokedil\LogParser\Interfaces;

interface LogDataProviderInterface {
	/**
	 * Get an iterable of log lines.
	 *
	 * @return iterable<string>
	 */
	public function get_log_lines(): iterable;
}
