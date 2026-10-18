# Contributing to VezoLabs Login Tracker

Thank you for your interest in contributing to VezoLabs Login Tracker for Pimcore 12 Studio!

## Development Guidelines

1. **Architecture Integrity**:
   - The bundle targets Pimcore 12 and the Pimcore Studio UI.
   - Do NOT use legacy ExtJS administration APIs or deprecated Pimcore 10/11 classes.
   - All management interfaces must be built for Pimcore Studio using React 18, TypeScript, and Module Federation.

2. **Security & Privacy by Default**:
   - Never log passwords, authentication tokens, session cookies, secret headers, or raw request credentials.
   - Respect IP anonymization and privacy configuration settings.
   - All CSV exports must neutralize spreadsheet formula injection characters (`=`, `+`, `-`, `@`, `\t`, `\r`, `%`).
   - All REST API endpoints must strictly verify Pimcore admin permissions.
   - Authentication tracking must never interrupt or fail legitimate user logins.

3. **Code Style & Standards**:
   - Follow PSR-12 coding standards and PHP 8.2+ strict typing (`declare(strict_types=1);`).
   - Follow Symfony and Pimcore conventions for bundle structure and service registration.

## Running Tests

To run the automated PHPUnit test suite:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

## Building Frontend Assets

Frontend assets are built using Rsbuild and Module Federation:

```bash
cd assets
npm install
npm run build
```

The compiled output will be written to `public/build/pimcore-login-tracker/`.

## Submitting Pull Requests

1. Fork the repository and create your feature branch: `git checkout -b feature/my-new-feature`.
2. Commit your changes with clear, concise commit messages.
3. Ensure all tests pass and there are no lint or type errors.
4. Push to the branch and open a Pull Request.
