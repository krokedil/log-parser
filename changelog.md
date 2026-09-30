# Changelog

All notable changes of krokedil/log-parser are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

* Matched rows are masked before they are output, so logs written before the plugins masked their own logs can be shared. Uses `FieldMasker` and `KeyMasker` from `krokedil/wp-api`.
* Added `LineMaskerInterface`, `WcLogLineMasker`, `NullLineMasker` and `MaskingProfile`.
* Added the optional `$line_masker` argument to `LogParser`. It defaults to `WcLogLineMasker`.
* Added CLI option `--no-mask`.
* Added PHPUnit tests, run with `composer test`.

------------------

## [1.0.0] - 2024-05-15

### Added

* Initial release of the package.
* Added `LogParser` class.
* Added CLI command `parse` with options `--logs`, `--output`, `--verbose`, `--inclusive` and `--help`.
* Added composer script `parse` to run the CLI command in the index.php file.
