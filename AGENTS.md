# AI Agent Instructions for Cerberus IAM Laravel Package

## Overview

This document provides comprehensive guidance for AI agents working on the Cerberus IAM Laravel Bridge package. The package provides seamless authentication and user management integration with the Cerberus IAM platform.

## Project Context

- **Package**: cerberus/laravel-iam
- **Purpose**: Laravel authentication bridge for Cerberus IAM
- **Architecture**: OAuth2 with PKCE, session-based token storage
- **Testing**: Pest framework with comprehensive test suite
- **Quality**: PSR-12, PHPStan level 8, automated CI/CD

## Code Standards

### PHP Standards

- PSR-12 coding standards
- Laravel naming conventions
- Strict type declarations
- Comprehensive PHPDoc documentation
- Error handling without exposing sensitive data

### Architecture Principles

- SOLID design principles
- Dependency injection via Laravel service container
- Interface-based design for testability
- Separation of concerns
- Graceful error handling

## Development Workflow

### Before Starting Work

1. Review existing code for similar implementations
2. Understand requirements and impact
3. Plan test coverage additions
4. Check for breaking changes

### During Development

1. Follow established patterns
2. Write tests concurrently with code
3. Run quality checks frequently
4. Update documentation as needed

### Before Committing

1. Execute full test suite with coverage
2. Verify static analysis passes
3. Ensure code formatting compliance
4. Update CHANGELOG for changes

## Testing Strategy

### Test Categories

- Unit tests for individual components
- Feature tests for complete workflows
- Integration tests for component interaction

### Testing Standards

- Pest framework for all test writing
- Mock external dependencies appropriately
- Test success and failure scenarios
- Maintain minimum coverage requirements

## Security Considerations

### OAuth2 Implementation

- PKCE (Proof Key for Code Exchange) required
- State parameter validation for CSRF protection
- Secure token storage and automatic refresh
- HTTPS-only external communications

### Best Practices

- Never log sensitive information
- Validate all input parameters
- Sanitize data appropriately
- Handle errors without information disclosure

## File Structure

```
src/
├── Auth/              # Guards and user providers
├── Contracts/         # Interface definitions
├── Facades/           # Laravel facades
├── Http/Clients/      # API client implementations
├── Middleware/        # Route protection
├── Repositories/      # Data access layer
└── Support/           # Utility classes

tests/
├── Feature/           # Integration tests
└── Fixtures/          # Test data and mocks
```

## Key Components

- **CerberusGuard**: Laravel authentication guard
- **CerberusUserProvider**: User resolution from IAM API
- **CerberusClient**: HTTP client for IAM communication
- **EnsureCerberusAuthenticated**: Route protection middleware

## Configuration

Environment variables prefixed with `CERBERUS_IAM_`:

- API endpoints and credentials
- OAuth2 client configuration
- HTTP client settings
- Session and security options

## Dependencies

- **Laravel**: ^10.0|^11.0 (framework integration)
- **jerome/fetch-php**: ^3.2 (HTTP client)
- **jerome/filterable**: ^2.0 (query filtering)

## Quality Assurance

### Automated Checks

- PHPStan static analysis (level 8)
- Laravel Pint code formatting
- Pest test execution
- Code coverage analysis

### Manual Reviews

- Security vulnerability assessment
- Performance impact evaluation
- Documentation completeness
- API compatibility verification

## Common Tasks

### Adding Features

1. Define interface contracts
2. Implement functionality following patterns
3. Create comprehensive tests
4. Update configuration if needed
5. Document changes in README and CHANGELOG

### Bug Fixes

1. Reproduce the issue
2. Write failing test case
3. Implement fix
4. Verify test passes
5. Ensure no regressions

### Security Updates

1. Review security implications
2. Update dependencies to secure versions
3. Test thoroughly for compatibility
4. Document security changes

## Communication Standards

### Commit Messages

- Use conventional commit format
- Reference issue numbers when applicable
- Provide clear, descriptive messages
- Keep commits focused and atomic

### Pull Requests

- Include comprehensive descriptions
- Reference related issues
- Ensure test coverage
- Mark breaking changes clearly
- Provide testing instructions

## Success Criteria

Work is considered complete when:

- All CI checks pass successfully
- Code follows established patterns
- Test coverage meets requirements
- Documentation is current and accurate
- Security standards are maintained
- Performance impact is acceptable
- Code is maintainable by other developers

## Emergency Procedures

### Build Failures

1. Stop additional changes
2. Revert to working state
3. Analyze root cause
4. Implement targeted fixes
5. Verify all tests pass

### Security Issues

1. Do not commit locally
2. Report through appropriate channels
3. Implement secure solution
4. Test fix thoroughly
5. Document security measures

## Important Notes

This is a production package used by real applications. All changes impact actual users and businesses. Maintain the highest standards of quality, security, and professionalism in all work.
