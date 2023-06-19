# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v2.1.1] - 2023-06-19

### Fixed

- The configuration for the Laravel AWS SDK service provider is now passed to the AWS SDK credential provider. This
  fixes the issue of the STS client always using the `us-east-1` regional endpoint.

[v2.1.1]: https://github.com/HealthEngineAU/laravel-easy-aws/compare/v2.1.0...v2.1.1
