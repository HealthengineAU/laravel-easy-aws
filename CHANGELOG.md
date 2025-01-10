# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v3.0.2] - 2025-01-10

### Changed

- Decreased SQS client `connect_timeout` from 60 to 5.

## [v3.0.1] - 2024-08-29

### Added

- Added support for Laravel 11.

## [v3.0.0] - 2023-09-07

### Added

- Added CircleCI pipeline to run tests.
- Added PHP-CS-Fixer as code style linter for project.
- Added support for Laravel 10.

### Removed

- Removed PHP_CodeSniffer as code style linter for project.
- Removed support for Laravel 5.
- Removed support for Laravel 6.
- Removed support for Laravel 7.
- Removed support for Laravel 8.
- Removed support for Laravel 9.

## [v2.1.1] - 2023-06-19

### Fixed

- The configuration for the Laravel AWS SDK service provider is now passed to the AWS SDK credential provider. This
  fixes the issue of the STS client always using the `us-east-1` regional endpoint.

[v3.0.1]: https://github.com/HealthengineAU/laravel-easy-aws/compare/v3.0.0...v3.0.1
[v3.0.0]: https://github.com/HealthengineAU/laravel-easy-aws/compare/v2.1.2...v3.0.0
[v2.1.1]: https://github.com/HealthengineAU/laravel-easy-aws/compare/v2.1.0...v2.1.1
