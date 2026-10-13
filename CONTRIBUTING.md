# Contributing to WilyCode Database Model

Thank you for your interest in contributing.

## Setup

1. Install PHP 8.4+ and Composer 2.
2. Run `composer install`.
3. Run `composer validate --strict` and `composer test`.

## Guidelines

- Preserve public APIs and keep the model layer independent of database drivers and query executors.
- Add tests for changes to metadata validation, hydration, relationships, casting, or persistence snapshots.
- Keep README examples executable and update README and CHANGELOG when behavior changes.
- Use `declare(strict_types=1)` in PHP source and tests.
- Avoid adding runtime dependencies unless they are essential to this library's responsibility.
- Do not commit environment secrets, `vendor/`, local IDE files, or generated caches.

## Pull requests

Explain why the change is needed, how it affects compatibility, and which tests cover it. Open an issue before proposing significant changes to model mapping conventions.

This package is part of the [WilyCode PHP Ecosystem](https://github.com/wilycode/wilycode).
