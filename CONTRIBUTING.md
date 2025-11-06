# Contributing to Cerberus IAM Laravel Bridge

Thank you for considering contributing to the Cerberus IAM Laravel Bridge. This document outlines the process and guidelines for contributing.

## Code of Conduct

We are committed to providing a welcoming and inclusive environment. Please be respectful and professional in all interactions.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When creating a bug report, include:

- A clear and descriptive title
- Steps to reproduce the behavior
- Expected vs. actual behavior
- Your environment (Laravel version, PHP version, package version)
- Any relevant error messages or logs

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, include:

- A clear and descriptive title
- Detailed description of the proposed functionality
- Use cases and rationale
- Potential implementation approach (if applicable)

### Pull Requests

1. Fork the repository and create your branch from `main`
2. Follow the existing code style and conventions
3. Write or update tests as needed
4. Ensure the test suite passes
5. Update documentation for any changed functionality
6. Reference any related issues in your PR description

## Development Setup

1. Clone your fork:
   ```bash
   git clone https://github.com/your-username/laravel-iam.git
   cd laravel-iam
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run tests:
   ```bash
   vendor/bin/pest
   ```

## Coding Standards

- Follow PSR-12 coding standards
- Use meaningful variable and method names
- Add PHPDoc blocks for classes and public methods
- Keep methods focused and concise
- Write tests for new functionality

## Testing

- All new features must include tests
- Bug fixes should include regression tests
- Ensure all tests pass before submitting a PR
- Aim for high code coverage on new code

## Commit Messages

- Use clear and descriptive commit messages
- Follow conventional commits format when possible
- Reference issue numbers when applicable

Example:
```
feat: add support for token refresh middleware

Add middleware to automatically refresh expired access tokens
before they are used in API requests.

Closes #123
```

## Documentation

- Update the README.md if you change functionality
- Add or update PHPDoc blocks for new/changed code
- Update CHANGELOG.md following Keep a Changelog format
- Add usage examples for new features

## Release Process

Maintainers will handle releases following semantic versioning:

- MAJOR version for incompatible API changes
- MINOR version for backwards-compatible functionality additions
- PATCH version for backwards-compatible bug fixes

## Questions?

Feel free to open an issue for questions or reach out to the maintainers.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
