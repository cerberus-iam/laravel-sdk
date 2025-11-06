# Changelog

All notable changes to the Cerberus IAM Laravel Bridge will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial package scaffolding
- CerberusGuard for stateful authentication with Cerberus sessions
- CerberusUserProvider for user resolution via IAM API
- CerberusUser model implementing Laravel's Authenticatable contract
- EnsureCerberusAuthenticated middleware for route protection
- CerberusClient HTTP client for IAM API communication
- UserDirectoryRepository for user directory operations
- UserDirectoryFilter for query parameter translation
- CerberusCallbackController for OAuth2 callback handling
- Configuration file with customizable endpoints and credentials
- Service provider with automatic discovery support
- Pest test suite with Orchestra Testbench integration

[Unreleased]: https://github.com/cerberus-iam/laravel-iam/compare/v0.1.0...HEAD
