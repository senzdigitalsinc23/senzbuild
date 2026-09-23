# Contributing to SENZ Framework

Thank you for considering contributing to the SENZ Framework!

## Code of Conduct

This project adheres to the Contributor Covenant. Be respectful, inclusive, and constructive.

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- MySQL / PostgreSQL
- Redis (optional, for queue/cache)

### Installation

```bash
git clone https://github.com/senzdigitals/framework.git
cd framework
composer install
cp .env.example .env
php bin/console migrate
php bin/console serve
```

## Development Workflow

1. **Fork** the repository and create a feature branch (`git checkout -b feature/amazing-feature`)
2. **Write tests** for your changes — all new features must have test coverage
3. **Run the test suite**: `php vendor/bin/phpunit`
4. **Run static analysis**: `php vendor/bin/phpstan analyse` and `php vendor/bin/psalm`
5. **Run the linter**: `php vendor/bin/phpcs --standard=PSR12 App/ Core/ Database/`
6. **Commit** using conventional commits: `feat: add OAuth2 server`, `fix: resolve N+1 query`
7. **Push** and open a Pull Request

## Commit Message Format

```
<type>(<scope>): <description>

fix(auth): resolve JWT token refresh issue
feat(queue): add Redis queue driver
docs: update README with deployment guide
```

Types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`

## Adding New Features

1. Follow PSR-12 coding standards
2. Add PHPDoc comments for all public methods
3. Write PHPUnit tests (aim for 80%+ coverage)
4. Update `CHANGELOG.md` under `[Unreleased]`
5. If adding a new config option, document it in `config/` and `.env.example`

## Testing

```bash
# Run all tests
php vendor/bin/phpunit

# Run specific test suite
php vendor/bin/phpunit tests/unit/Core/

# Run with coverage
php vendor/bin/phpunit --coverage-html storage/logs/coverage/
```

## Architecture Guidelines

- **Core classes** go in `Core/` — framework-wide functionality
- **Middleware** goes in `App/Middleware/`
- **Controllers** go in `App/Controllers/Api/` or `App/Controllers/Web/`
- **Models** go in `App/Models/` extending `Database\ORM\Model`
- **Service Providers** go in `App/Providers/`
- **DTOs** go in `App/DTOs/`
- **Repositories** go in `Repositories/`

## Security

If you discover a security vulnerability, please email senzdigitals.inc23@gmail.com instead of opening an issue.

## License

MIT License — see [LICENSE](LICENSE) for details.
