# Contributing to Hillmeet

1. **Fork** the repository and create a branch from `main` (or `master`).
2. **Code style:** Follow PSR-12. Use strict types (`declare(strict_types=1);`) in PHP files.
3. **Security:** Use the `e()` helper for any user-derived output. Use prepared statements for all DB access. Include CSRF tokens on forms that change state.
4. **Tests:** Add or update tests under `tests/` as needed. Run `composer test` (or `phpunit`).
5. **Commit:** Use clear, concise messages.
6. **Open a pull request** against the default branch. Describe the change and reference any issues.

By contributing, you agree that your contributions will be licensed under the project’s MIT License.
