# 🤖 AI Agent Instructions for Cerberus IAM Laravel Package

## Welcome, AI Agent

You are working on the **Cerberus IAM Laravel Bridge** - a professional Laravel package that provides seamless authentication and user management integration with the Cerberus IAM platform.

## 🎯 Your Mission

Help maintain and improve this production-ready Laravel package by:

- Writing clean, well-tested, and documented code
- Following Laravel and PHP best practices
- Ensuring security and performance standards
- Maintaining comprehensive test coverage
- Keeping documentation up-to-date

## 📋 Code Standards

### PHP & Laravel Standards

- **PSR-12**: Strict adherence to PHP Standards Recommendations
- **Laravel Conventions**: Use Laravel naming patterns and structure
- **Type Safety**: Comprehensive type hints and return types
- **Strict Types**: Always use `declare(strict_types=1)`

### Documentation Standards

- **PHPDoc**: Complete documentation for all public methods
- **Inline Comments**: Explain complex business logic
- **README Updates**: Keep installation and usage docs current
- **Changelog**: Document all changes appropriately

## 🏗️ Architecture Guidelines

### SOLID Principles

- **Single Responsibility**: Each class has one reason to change
- **Open/Closed**: Open for extension, closed for modification
- **Liskov Substitution**: Subtypes are substitutable for base types
- **Interface Segregation**: Client-specific interfaces
- **Dependency Inversion**: Depend on abstractions, not concretions

### Laravel Integration

- **Service Container**: Use dependency injection
- **Contracts**: Define interfaces for testability
- **Facades**: Use judiciously for developer experience
- **Middleware**: Follow Laravel middleware patterns

## 🧪 Testing Strategy

### Test Categories

- **Unit Tests**: Test individual classes and methods
- **Feature Tests**: Test complete user journeys
- **Integration Tests**: Test component interactions

### Testing Standards

- **Pest Framework**: Use Pest for all test writing
- **Coverage**: Maintain >80% test coverage
- **Mocking**: Mock external dependencies (HTTP, sessions)
- **Edge Cases**: Test success, failure, and error scenarios

## 🔒 Security First

### OAuth2 Security

- **PKCE**: Always use Proof Key for Code Exchange
- **State Validation**: Prevent CSRF attacks
- **Token Handling**: Secure storage and automatic refresh
- **Input Validation**: Never trust external data

### Best Practices

- **No Secrets in Logs**: Never log passwords, tokens, or secrets
- **HTTPS Only**: All external API calls must use HTTPS
- **Input Sanitization**: Validate and sanitize all inputs
- **Error Handling**: Don't expose sensitive information in errors

## 🚀 Development Workflow

### Before Starting Work

1. **Understand Requirements**: Read issues/PRs thoroughly
2. **Check Existing Code**: Look for similar implementations
3. **Plan Changes**: Consider impact on existing functionality
4. **Update Tests**: Plan test additions/modifications

### During Development

1. **Write Code**: Follow established patterns
2. **Add Tests**: Write tests as you develop
3. **Run Quality Checks**: Use `composer ci` frequently
4. **Document Changes**: Update docs as needed

### Before Committing

1. **Run Full Suite**: `composer ci` (lint + analyse + test)
2. **Check Coverage**: Ensure test coverage remains high
3. **Update Docs**: README, CHANGELOG, PHPDoc as needed
4. **Clean Code**: Remove debug statements and TODOs

## 🛠️ Common Tasks

### Adding a New Feature

1. **Define Interface**: Add contract in `src/Contracts/`
2. **Implement Class**: Create implementation following patterns
3. **Add Tests**: Comprehensive test coverage
4. **Update Config**: Add configuration if needed
5. **Document**: Update README and add PHPDoc

### Fixing a Bug

1. **Reproduce Issue**: Understand the problem
2. **Write Test**: Create failing test first
3. **Fix Code**: Implement the solution
4. **Verify Fix**: Ensure test passes and no regressions

### Security Updates

1. **Audit Code**: Review for security implications
2. **Update Dependencies**: Use latest secure versions
3. **Test Thoroughly**: Ensure no breaking changes
4. **Document Changes**: Update security notes

## 📁 Project Structure

```
src/
├── Auth/              # Authentication guards and providers
├── Contracts/         # Interface definitions
├── Facades/           # Laravel facades
├── Http/Clients/      # HTTP client implementations
├── Middleware/        # Route protection middleware
├── Repositories/      # Data access layer
└── Support/           # Utility classes and stores

tests/
├── Feature/           # Integration tests
└── Fixtures/          # Test mocks and data

.github/
├── workflows/         # CI/CD pipelines
└── ISSUE_TEMPLATE/    # Issue templates
```

## 🔧 Quality Gates

### Automated Checks

- **PHPStan**: Level 8 static analysis (must pass)
- **Pint**: Code formatting (must pass)
- **Pest**: All tests pass (must pass)
- **Coverage**: >80% in CI (must pass)

### Manual Reviews

- **Security Review**: Check for vulnerabilities
- **Performance Review**: Consider performance impact
- **Documentation Review**: Ensure docs are complete
- **API Review**: Check for breaking changes

## 📞 Communication

### Commit Messages

- Use conventional commits: `feat:`, `fix:`, `docs:`, `refactor:`
- Be descriptive but concise
- Reference issues: `fix: resolve user lookup issue (#123)`

### Pull Requests

- **Title**: Clear, descriptive title
- **Description**: Explain what and why
- **Tests**: Include test coverage
- **Breaking Changes**: Clearly marked
- **Screenshots**: For UI changes (if applicable)

## 🎉 Success Criteria

Your work is successful when:

- ✅ All CI checks pass
- ✅ Code follows established patterns
- ✅ Tests provide good coverage
- ✅ Documentation is complete and accurate
- ✅ Security standards are maintained
- ✅ Performance is not negatively impacted
- ✅ Other developers can easily understand and maintain the code

## 🚨 Emergency Procedures

### If You Break the Build

1. **Stop**: Don't commit more changes
2. **Revert**: Go back to working state
3. **Analyze**: Understand what went wrong
4. **Fix**: Make targeted fixes
5. **Test**: Ensure everything works

### If You Find a Security Issue

1. **Don't Commit**: Keep it local
2. **Report**: Use appropriate security channels
3. **Fix**: Implement secure solution
4. **Test**: Verify the fix works
5. **Document**: Update security documentation

---

**Remember**: This is a production package used by real applications. Your changes impact real users and businesses. Take pride in your work and maintain the highest standards of quality and security! 🚀
