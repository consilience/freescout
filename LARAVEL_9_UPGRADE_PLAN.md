# Laravel 9.x Upgrade Plan

## Overview
Upgrade from Laravel 8.83.29 → 9.x

**Estimated Time:** 30-60 minutes
**PHP Requirement:** 8.0.2+ (Currently: 8.4.15 ✓)

## Key Breaking Changes

### 1. Flysystem 3.x Migration (HIGH IMPACT)
- Migrated from Flysystem 1.x → 3.x
- Write operations now overwrite by default
- Failed writes return `false` instead of throwing exceptions
- Missing file reads return `null` instead of throwing
- No more cached adapters
- Custom drivers must return `FilesystemAdapter` directly

### 2. Symfony Mailer Replaces SwiftMailer (HIGH IMPACT)
- SwiftMailer unmaintained since December 2021
- Method renames: `withSwiftMessage()` → `withSymfonyMessage()`
- `send()` now returns `SentMessage` instead of `void`
- Message API changed significantly
- No custom Message IDs via `mime.idgenerator.idright`

### 3. PHP Return Types Required
Methods must implement proper return types:
- `count(): int`
- `getIterator(): Traversable`
- `jsonSerialize(): array`
- `offsetExists($key): bool`
- `offsetGet($key): mixed`
- `offsetSet($key, $value): void`
- `offsetUnset($key): void`

SessionHandlerInterface methods need return types too.

## Composer Dependencies

### Update
```json
{
    "require": {
        "php": "^8.0.2",
        "laravel/framework": "^9.0"
    },
    "require-dev": {
        "spatie/laravel-ignition": "^1.0",
        "nunomaduro/collision": "^6.1"
    }
}
```

### Remove
- `facade/ignition`
- `fideloper/proxy` (if exists)
- `wildbit/swiftmailer-postmark` (if exists)

### Install Flysystem Drivers
```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
composer require league/flysystem-ftp "^3.0"
composer require league/flysystem-sftp-v3 "^3.0"
```

### Install Mailer Drivers
```bash
composer require symfony/mailgun-mailer symfony/http-client
composer require symfony/postmark-mailer symfony/http-client
```

## Configuration Changes

### Database (config/database.php)
- Rename `schema` → `search_path` in Postgres config

### Filesystem
- Rename env var: `FILESYSTEM_DRIVER` → `FILESYSTEM_DISK`
- Remove `cache` keys from disk configurations
- Remove `cloud` disk if unused

### Mail (config/mail.php)
- Update for Symfony Mailer
- SMTP stream options: move from under `stream` key to direct config
- Remove `auth_mode` (auto-negotiates now)

## Code Changes

### TrustProxies Middleware
Update `app/Http/Middleware/TrustProxies.php`:
```php
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

protected $headers =
    Request::HEADER_X_FORWARDED_FOR |
    Request::HEADER_X_FORWARDED_HOST |
    Request::HEADER_X_FORWARDED_PORT |
    Request::HEADER_X_FORWARDED_PROTO |
    Request::HEADER_X_FORWARDED_AWS_ELB;
```

### Exception Handler
Change visibility: `protected function ignore()` → `public function ignore()`

### Validation
- Rename `password` rule → `current_password`
- Unvalidated array keys excluded from validated data

### Testing
- Rename `assertDeleted()` → `assertModelMissing()`

### Collections
- Remove `reduceWithKeys()` → use `reduce()`
- Rename `reduceMany()` → `reduceSpread()`

### Session
- `Request::getSession()` returns `SessionInterface` or throws exception

### Queue
- Replace `opis/closure` with `laravel/serializable-closure`

### Language Files
- Move `resources/lang/` → `lang/` (root directory)

### Blade Templates
- May conflict with Vue: `@selected`, `@checked`, `@disabled`
- Escape with `@@selected` if needed

## Override File Concerns

### Critical Override Files to Check
1. **SwiftMailer overrides** - Must be replaced with Symfony Mailer equivalents
2. **Flysystem overrides** - Must be updated for v3.x API
3. **Session handlers** - Need return type declarations
4. **Collections/ArrayAccess** - Need return type declarations
5. **Custom filesystem drivers** - Must return `FilesystemAdapter`

## Upgrade Steps

1. ✓ Review Laravel 9.x upgrade guide
2. Update composer.json dependencies
3. Run composer update
4. Fix override file conflicts (expect many)
5. Update TrustProxies middleware
6. Update configuration files
7. Update validation rules
8. Update test assertions
9. Run tests and fix failures
10. Test core functionality

## Risk Assessment

**High Risk:**
- SwiftMailer → Symfony Mailer migration (if email system heavily customized)
- Flysystem 3.x migration (if custom storage drivers exist)
- Override system conflicts with new return types

**Medium Risk:**
- Configuration file changes
- Middleware updates

**Low Risk:**
- Helper method renames
- Test assertion renames

## Notes

- PHP 8.4 deprecation warnings expected (Laravel 9 targets PHP 8.0-8.1)
- Override system adds significant complexity
- May need to patch/update many override files
- SwiftMailer removal is the biggest breaking change
