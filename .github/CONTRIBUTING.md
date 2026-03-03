# Contributing to querri/embed

Thanks for your interest in contributing! This guide will help you get started.

## Getting started

1. Fork and clone the repository
2. Install dependencies:
   ```sh
   composer install
   ```
3. Run tests:
   ```sh
   composer test
   ```
4. Run static analysis:
   ```sh
   composer analyse
   ```

## Project structure

```
src/
  Exceptions/   Exception hierarchy (ApiException, RateLimitException, etc.)
  Http/         HTTP client and retry strategy
  Resources/    API resource classes (Users, Embed, Policies)
  Session/      getSession() orchestration and result DTO
```

- **Tests**: [PHPUnit](https://phpunit.de/) runs the test suite
- **Static Analysis**: [PHPStan](https://phpstan.org/) (if configured)
- **Code Style**: [PHP-CS-Fixer](https://cs.symfony.com/) (if configured)

## Submitting changes

1. Create a branch from `main`
2. Make your changes with clear, descriptive commits
3. Ensure CI passes: `composer test`
4. Open a pull request against `main`

## Reporting bugs

Please use the [bug report template](https://github.com/Querri-inc/querri-phpembed/issues/new?template=bug_report.md) when filing issues.

## Code of conduct

Be respectful and constructive. We're building something together — treat others the way you'd like to be treated.
