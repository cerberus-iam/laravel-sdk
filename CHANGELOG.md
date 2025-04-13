# Release Notes

## [Unreleased](https://github.com/cerberus-iam/cerberus-iam-sdk/compare/v0.0.16...0.0.x)

## [v0.0.16](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.15...v0.0.16) - 2025-04-13

### Added

- Add primary key documentation for `Client` resource
- Add method to set client ID and secret using a client model; include `Client` resource in `ResolvesResources`
- Add client ID and secret override properties with setter method
- Add method to find user by email address
- Add error handling to `validateCredentials` method in `CerberusUserProvider`

### Changed

- Refactor `Client` class to define mass assignable attributes in `fillable` property
- Refactor `Cerberus` class to handle API configurations
- Refactor authentication to support client ID and secret overrides in access token requests
- Update HTTP headers to use API key and secret names for client ID and secret overrides
- Refactor onboarding method in `Organisation` resource to use new instance creation
- Refactor onboarding method in `Organisation` resource to handle response and error checking
- Refactor response handling in `Resource` and `ResourceBuilder` classes

### Fixed

- Fix onboarding method to return JSON response after successful organisation onboarding
- Implement `revoke` method in `Client` class to handle client revocation by ID or instance

## [v0.0.15](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.14...v0.0.15) - 2025-04-10

- Fix invalid usage of Fetch\Response class

## [v0.0.14](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.13...v0.0.14) - 2025-04-10

- Refactor `User` and `Organisation` fillable fields

## [v0.0.13](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.12...v0.0.13) - 2025-04-10

### Added

- Tests for exception handling in user notifications and role management.
- Additional test scenarios in `ResourceTest`, including attribute setting, dirty state checks, and JSON conversion.

### Changed

- Refactored `ResourceTest` to use PHPUnit mocks instead of Mockery for improved readability and compatibility.
- Streamlined `UserTest`, enhancing role assignment and ability checks with properly mocked HTTP responses.

### Fixed

- Removed unnecessary dependencies from tests.
- Improved test organization and structure for better maintainability and clarity.

## [v0.0.12](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.11...v0.0.12) - 2025-04-03

- Refactor `update` and `create` method to send all attributes to API.
- Change `exists` method to properly detect if a resource exists in the API database.

## [v0.0.11](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.10...v0.0.11) - 2025-04-03

- Refactor the `Resource` class to more closely emulate Laravel's Eloquent model behavior.

## [v0.0.10](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.9...v0.0.10) - 2025-04-03

- Refactor `Resource` class to act more like Laravel's Eloquent model

## [v0.0.8](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.7...v0.0.8) - 2025-04-03

- Add `hasRoles` method to check if the authenticated user has specific roles

## [v0.0.7](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.6...v0.0.7) - 2025-04-02

- Add caching mechanism to store and retrieve authenticated user

## [v0.0.6](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.5...v0.0.6) - 2025-04-02

- Refactor resource model to closely match Laravel's Eloquent model

## [v0.0.5](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.4...v0.0.5) - 2025-04-01

- Fix `Resource` `find` method to set attributes from response data properly.

## [v0.0.4](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.3...v0.0.4) - 2025-04-01

- Add `convertSingleRecordAudToString` method to convert single record aud to string format

## [v0.0.3](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.2...v0.0.3) - 2025-04-01

- Register and properly use TokenStorage

## [v0.0.2](https://github.com/cerberus-iam/laravel-sdk/compare/v0.0.1...v0.0.2) - 2025-04-01

- Update API base URL to dev server

## v0.0.1 - 2025-02-12

Initial test release.
