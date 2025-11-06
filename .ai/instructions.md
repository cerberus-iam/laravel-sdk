# AI Agent Instructions for Cerberus IAM Laravel Package

## Project Overview

This is a Laravel package that provides authentication and user management integration with the Cerberus IAM platform. It implements OAuth2 flows, user providers, guards, and HTTP clients for seamless integration.

## Code Standards

- **PSR-12**: Follow PHP Standards Recommendations
- **Laravel Conventions**: Use Laravel naming and structure patterns
- **Type Safety**: Use strict types and comprehensive type hints
- **Documentation**: PHPDoc blocks for all public methods and complex logic

## Architecture Principles

- **SOLID**: Single responsibility, open/closed, Liskov substitution, interface segregation, dependency inversion
- **Dependency Injection**: Use Laravel's service container
- **Interface Segregation**: Define contracts for testability
- **Error Handling**: Graceful degradation, meaningful error messages

## Testing Requirements

- **Pest Framework**: Use Pest for all tests
- **Coverage**: Maintain >80% test coverage
- **Mocking**: Mock external dependencies (HTTP calls, sessions)
- **Scenarios**: Test success paths, failures, and edge cases

## Security Considerations

- **OAuth2 Security**: Proper PKCE, state validation, token handling
- **Input Validation**: Validate all inputs, never trust external data
- **Token Storage**: Secure token storage, automatic refresh
- **Logging**: Never log sensitive information (passwords, tokens, secrets)

## Development Workflow

1. **Branch**: Create feature branches from `main`
2. **Code**: Write code following standards
3. **Test**: Add comprehensive tests
4. **Lint**: Run `composer ci` (lint + analyse + test)
5. **Commit**: Use descriptive commit messages
6. **PR**: Create focused pull requests with descriptions

## Common Tasks

- **New Feature**: Add interface → implementation → tests → documentation
- **Bug Fix**: Reproduce → fix → add regression test → update docs
- **Security**: Audit code → update dependencies → test thoroughly
- **Performance**: Profile → optimize → benchmark → document

## File Structure

```text
src/
├── Auth/           # Authentication components
├── Contracts/      # Interface definitions
├── Facades/        # Laravel facades
├── Http/Clients/   # HTTP client implementations
├── Middleware/     # Laravel middleware
├── Repositories/   # Data access layer
└── Support/        # Utility classes

tests/
├── Feature/        # Integration tests
└── Fixtures/       # Test data and mocks
```

## Key Components

- **CerberusGuard**: Laravel authentication guard
- **CerberusUserProvider**: User resolution from IAM
- **CerberusClient**: HTTP client for IAM API
- **EnsureCerberusAuthenticated**: Route protection middleware

## Configuration

Environment variables prefixed with `CERBERUS_IAM_` for:

- API endpoints, OAuth credentials, timeouts, retry settings

## Dependencies

- **Laravel**: Framework integration (^10.0|^11.0)
- **jerome/fetch-php**: HTTP client (^3.2)
- **jerome/filterable**: Query filtering (^2.0)

## Quality Gates

- **PHPStan**: Level 8 static analysis (no errors)
- **Pint**: Code formatting (no violations)
- **Pest**: All tests pass
- **Coverage**: >80% in CI pipeline

## Key Components

- **CerberusGuard**: Laravel authentication guard
- **CerberusUserProvider**: User resolution from IAM
- **CerberusClient**: HTTP client for IAM API
- **EnsureCerberusAuthenticated**: Route protection middleware

## Environment Configuration

Environment variables prefixed with `CERBERUS_IAM_` for:

- API endpoints, OAuth credentials, timeouts, retry settings

## Package Dependencies

- **Laravel**: Framework integration (^10.0|^11.0)
- **jerome/fetch-php**: HTTP client (^3.2)
- **jerome/filterable**: Query filtering (^2.0)

## Quality Assurance

- **PHPStan**: Level 8 static analysis (no errors)
- **Pint**: Code formatting (no violations)
- **Pest**: All tests pass
- **Coverage**: >80% in CI pipeline
