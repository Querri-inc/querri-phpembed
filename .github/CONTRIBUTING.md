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
  Resources/    API resource classes (Users, Embed, Policies, Dashboards, Projects, Chats, Data, Sources, Files, Keys, Audit, Usage, Sharing)
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

## Releasing

Releases publish to Packagist automatically via the `packagist.org/api/github` webhook on this repo. To cut a new version:

1. Bump the version (updates `src/Config.php`, commits, and tags):
   ```sh
   composer version patch   # or: minor, major, or an explicit X.Y.Z
   ```
2. Push the commit and the tag:
   ```sh
   git push && git push --tags
   ```
3. The new version appears on <https://packagist.org/packages/querri/embed> within ~60 seconds.

## Code of conduct

Be respectful and constructive. We're building something together — treat others the way you'd like to be treated.
