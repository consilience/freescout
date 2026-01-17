# Laravel 5.7 → 8.x Jump Upgrade Plan

**Date:** 2025-11-22
**Current Version:** Laravel 5.7.29
**Target Version:** Laravel 8.x (latest)
**Strategy:** Direct jump while addressing all intermediate breaking changes

---

## Executive Summary

This document consolidates **all breaking changes** from Laravel 5.7 through 8.x by reviewing upgrade guides for:
- Laravel 5.7 → 5.8 (Estimated: 1 hour)
- Laravel 5.8 → 6.x (Estimated: 1 hour)
- Laravel 6.x → 7.x (Estimated: 15 minutes)
- Laravel 7.x → 8.x (Estimated: 15 minutes)

**Total Estimated Effort:** 15-20 hours (including override system refactoring)

---

## PHP & Symfony Version Requirements

| Version | PHP Minimum | Symfony |
|---------|-------------|---------|
| Laravel 5.7 | PHP 7.1.3+ | Symfony 4.x |
| Laravel 5.8 | PHP 7.1.3+ | Symfony 4.x |
| Laravel 6.x | **PHP 7.2+** | Symfony 4.x |
| Laravel 7.x | **PHP 7.2.5+** | **Symfony 5.x** |
| Laravel 8.x | **PHP 7.3+** | Symfony 5.x |

**Our Environment:** PHP 8.4.15 ✅ (Compatible)

**Action Required:** Update `composer.json` minimum PHP version to 7.3 (we have 8.4, so we're good)

---

## Critical Breaking Changes (Cumulative)

### 1. Cache TTL Changed to Seconds (5.8)

**Impact:** VERY HIGH - Affects all cache operations

**Change:** Cache methods expect TTL in **seconds** instead of **minutes**

**Affected Methods:**
- `Cache::put()`
- `Cache::putMany()`
- `Cache::add()`
- `Cache::remember()`
- `setDefaultCacheTime()`

**Action Required:**

```php
// OLD (5.7 - minutes):
Cache::put('key', 'value', 60); // 60 minutes

// NEW (5.8+ - seconds):
Cache::put('key', 'value', 3600); // 3600 seconds = 60 minutes

// SAFE (works in both):
Cache::put('key', 'value', now()->addMinutes(60));
```

**Files to Check:**
- Search codebase for `Cache::` calls
- Search for `->remember(` calls
- Check Rememberable trait usage

### 2. String & Array Helpers Removed (6.x)

**Impact:** VERY HIGH - Affects all `str_*` and `array_*` helper usage

**Change:** All string/array helpers moved to separate `laravel/helpers` package and removed from framework

**Options:**
1. Install `laravel/helpers` package (maintains old syntax)
2. Migrate to `Illuminate\Support\Str` and `Illuminate\Support\Arr` classes

**Action Required:**

```php
// OLD:
str_limit($string, 100)
array_get($array, 'key')

// NEW (Option 1 - Install laravel/helpers):
composer require laravel/helpers

// NEW (Option 2 - Use classes):
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

Str::limit($string, 100)
Arr::get($array, 'key')
```

**Recommendation:** Use classes (Option 2) for better performance and forward compatibility

**Files to Check:**
- Global search for `str_*(`
- Global search for `array_*(`
- Blade views using these helpers

### 3. Model Factories Complete Rewrite (8.x)

**Impact:** VERY HIGH - Entire factory system redesigned

**Change:** Database factories moved from closures to dedicated factory classes

**Old System (5.7-7.x):**
```php
// database/factories/UserFactory.php
$factory->define(App\User::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
    ];
});
```

**New System (8.x+):**
```php
// database/factories/UserFactory.php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
        ];
    }
}
```

**Options:**
1. Install `laravel/legacy-factories` to keep old system
2. Migrate to new class-based factories

**Action Required:**
- Install `laravel/legacy-factories` OR migrate all factories
- Update seeder calls from `factory(User::class, 10)->create()` to `User::factory()->count(10)->create()`

**Recommendation:** Install legacy-factories initially, migrate later

### 4. Seeders Namespace & Location (8.x)

**Impact:** HIGH - All seeders must be moved and namespaced

**Change:** Seeders moved from `database/seeds/` to `database/seeders/` with namespace

**Action Required:**

```php
// OLD: database/seeds/DatabaseSeeder.php
class DatabaseSeeder extends Seeder { }

// NEW: database/seeders/DatabaseSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder { }
```

**composer.json Update:**
```json
"autoload": {
    "psr-4": {
        "Database\\Factories\\": "database/factories/",
        "Database\\Seeders\\": "database/seeders/"
    }
}
```

### 5. Password Minimum Length (5.8)

**Impact:** HIGH - Affects authentication and password validation

**Change:** Default password minimum increased from **6 to 8 characters**

**Action Required:**
- Update validation rules: `'password' => 'required|string|min:8'`
- Update password reset views
- Update user setup forms
- Inform users of new requirement

**Files to Check:**
- All validation rules with password fields
- Blade views with password inputs
- User documentation

### 6. Pagination Defaults Changed to Tailwind (8.x)

**Impact:** HIGH - UI will break without fix

**Change:** Default pagination views switched from Bootstrap to Tailwind CSS

**Action Required:**

Add to `AppServiceProvider::boot()`:
```php
use Illuminate\Pagination\Paginator;

Paginator::useBootstrap();
```

### 7. Date Serialization Format (7.x)

**Impact:** HIGH - API responses change format

**Change:** Dates serialize to ISO-8601 format instead of MySQL format

**Old:** `2019-12-02 20:01:00`
**New:** `2019-12-02T20:01:00.283041Z`

**Action Required (if old format needed):**

```php
// In models that serialize dates
protected function serializeDate(DateTimeInterface $date)
{
    return $date->format('Y-m-d H:i:s');
}
```

### 8. Symfony 5 Upgrade (7.x)

**Impact:** HIGH - Affects exception handling, console, HTTP

**Change:** Laravel 7 uses Symfony 5.x series (from 4.x)

**Action Required:**
- Update `ExceptionHandler` methods to accept `Throwable` instead of `Exception`
- Update console commands to return integers (0 = success)
- Review custom exception handling

```php
// OLD:
public function report(Exception $exception) { }

// NEW:
public function report(Throwable $exception) { }
```

### 9. Authorization Policies viewAny Method (6.x)

**Impact:** MEDIUM - Index pages will fail authorization

**Change:** Policies using `authorizeResource` must define `viewAny` method

**Action Required:**

```php
// Add to all policies
public function viewAny(User $user)
{
    return true; // Or your authorization logic
}
```

**Files to Check:**
- `app/Policies/ConversationPolicy.php`
- `app/Policies/MailboxPolicy.php`
- `app/Policies/UserPolicy.php`

### 10. Email Verification Route Changes (6.x)

**Impact:** MEDIUM - Existing verification links break

**Change:**
- Verification resend changed from GET to POST (CSRF protection)
- URL structure: `/email/verify/{id}` → `/email/verify/{id}/{hash}`

**Action Required:**
- Previous verification emails become invalid
- Update routes if customized
- Test entire email verification flow

### 11. Queue Method Renames (8.x)

**Impact:** MEDIUM - Queue jobs need updates

**Changes:**
- `retryAfter` → `backoff`
- `timeoutAt` → `retryUntil`
- `allOnQueue()` and `allOnConnection()` removed from chains

**Action Required:**

```php
// OLD:
public $retryAfter = 60;
public $timeoutAt = '2025-12-01 00:00:00';

// NEW:
public $backoff = 60;
public $retryUntil = '2025-12-01 00:00:00';
```

**Files to Check:**
- All job classes in `app/Jobs/`

---

## Composer Dependency Updates

### Required Framework Updates

```json
{
    "require": {
        "php": "^7.3|^8.0",
        "laravel/framework": "^8.0",

        // First-party Laravel packages
        "laravel/tinker": "^2.5",
        "laravel/ui": "^3.0",

        // Testing
        "phpunit/phpunit": "^9.0",
        "nunomaduro/collision": "^5.0",
        "facade/ignition": "^2.5",
        "fakerphp/faker": "^1.9.1",

        // HTTP client
        "guzzlehttp/guzzle": "^7.0.1",

        // Optional helpers
        "laravel/legacy-factories": "^1.0",

        // Carbon
        "nesbot/carbon": "^2.0"
    }
}
```

### Package Migrations

| Old Package | New Package | Notes |
|-------------|-------------|-------|
| `fzaninotto/faker` | `fakerphp/faker` | Faker abandoned, use new package |
| N/A | `laravel/legacy-factories` | Optional: Keep old factory syntax |
| `predis/predis` | `predis/predis` OR use `phpredis` extension | Default changed to phpredis |

### Removed Features

- **Mandrill mail driver** - Use Mailgun, SES, etc.
- **SparkPost mail driver** - Use alternatives
- **Nexmo notification channel** - Update to new integrations
- **String & array helpers** - Install `laravel/helpers` or migrate to classes
- **`elixir()` helper** - Use Laravel Mix
- **`sendNow()` mail method** - Use `send()`

---

## Configuration File Updates

### 1. `app/Exceptions/Handler.php`

**Change method signatures:**

```php
use Throwable;

public function report(Throwable $exception)
{
    parent::report($exception);
}

public function shouldReport(Throwable $exception)
{
    return parent::shouldReport($exception);
}

public function render($request, Throwable $exception)
{
    return parent::render($request, $exception);
}

protected function renderHttpException(HttpExceptionInterface $e)
{
    // Update if overridden
}
```

### 2. `app/Providers/AppServiceProvider.php`

**Add pagination default:**

```php
use Illuminate\Pagination\Paginator;

public function boot()
{
    // Keep Bootstrap pagination
    Paginator::useBootstrap();

    // Existing code...
    Schema::defaultStringLength(191);
}
```

### 3. `app/Providers/RouteServiceProvider.php`

**Update namespace (8.x change):**

```php
// OLD:
protected $namespace = 'App\Http\Controllers';

// NEW (8.x - namespace now null by default):
protected $namespace = 'App\Http\Controllers'; // Keep for backward compat

// Routes remain the same
Route::middleware('web')
    ->namespace($this->namespace)
    ->group(base_path('routes/web.php'));
```

### 4. `config/session.php`

**Update secure cookie fallback:**

```php
'secure' => env('SESSION_SECURE_COOKIE', null),
```

### 5. `config/mail.php`

**Rename MAIL_DRIVER:**

```php
// In .env:
// OLD: MAIL_DRIVER=smtp
// NEW: MAIL_MAILER=smtp

'default' => env('MAIL_MAILER', 'smtp'),
```

### 6. `config/database.php`

**Update Redis client (if using Redis):**

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'), // Changed from 'predis'

    // Or to keep predis:
    'client' => env('REDIS_CLIENT', 'predis'),
],
```

### 7. `config/queue.php`

**Add UUID support for failed jobs:**

```php
'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'failed_jobs',
],
```

### 8. Database Migration for Failed Jobs

**Add UUID column:**

```bash
php artisan queue:failed-table
php artisan migrate
```

Update `failed_jobs` migration:

```php
Schema::create('failed_jobs', function (Blueprint $table) {
    $table->id();
    $table->string('uuid')->unique(); // ADD THIS
    $table->text('connection');
    $table->text('queue');
    $table->longText('payload');
    $table->longText('exception');
    $table->timestamp('failed_at')->useCurrent();
});
```

### 9. `public/index.php`

**Add maintenance mode check:**

```php
// After autoloader:
$app = require_once __DIR__.'/../bootstrap/app.php';

// ADD THIS:
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

$kernel = $app->make(Kernel::class);
```

---

## Application Code Changes

### 1. Update All Job Classes

**File Pattern:** `app/Jobs/*.php`

**Changes:**

```php
// Rename properties
protected $retryAfter = 60;  → public $backoff = 60;
protected $timeoutAt;        → public $retryUntil;

// Update failed method signature
public function failed(Throwable $exception)
{
    // Error handling
}
```

### 2. Update Cache Calls

**Global Search:** `Cache::`

**Update TTL from minutes to seconds:**

```php
// Find all instances like:
Cache::remember('key', 60, function() { });

// Change to:
Cache::remember('key', 3600, function() { }); // 60 min = 3600 sec

// Or use Carbon:
Cache::remember('key', now()->addMinutes(60), function() { });
```

**Check Rememberable Trait:**

FreeScout uses `watson/rememberable` - verify if it handles TTL correctly in 8.x.

### 3. Migrate String/Array Helpers

**Option 1: Install Helper Package**

```bash
composer require laravel/helpers
```

**Option 2: Replace with Classes**

```php
// Add imports
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

// Replace calls
str_limit()      → Str::limit()
str_slug()       → Str::slug()
str_contains()   → Str::contains()
array_get()      → Arr::get()
array_set()      → Arr::set()
array_has()      → Arr::has()
```

**Blade Views:**

```blade
{{-- OLD: --}}
{{ str_limit($text, 100) }}

{{-- NEW with helper package: --}}
{{ str_limit($text, 100) }}

{{-- NEW with class: --}}
{{ \Str::limit($text, 100) }}
```

**Recommendation:** Install `laravel/helpers` initially for quick upgrade, then migrate gradually.

### 4. Update Policies

**Add `viewAny` to all policies:**

```php
// app/Policies/ConversationPolicy.php
public function viewAny(User $user)
{
    // Return true if user can view any conversations
    return true;
}
```

Repeat for:
- `MailboxPolicy`
- `UserPolicy`
- `FolderPolicy`
- `ThreadPolicy`

### 5. Update Console Commands

**Return integers instead of booleans:**

```php
// OLD:
public function handle()
{
    // ...
    return true; // Success
}

// NEW:
public function handle()
{
    // ...
    return 0; // Success (0 = success, non-zero = error)
}
```

**Files to Check:**
- All classes in `app/Console/Commands/`

### 6. Update Password Validation

**Global search:** `'password'` in validation rules

```php
// OLD:
'password' => 'required|string|min:6'

// NEW:
'password' => 'required|string|min:8'
```

**Files to Check:**
- Controllers with registration/password reset
- Validation request classes

### 7. Fix Route Namespacing (8.x)

**Laravel 8 prefers explicit class imports:**

```php
// OLD (still works with namespace property):
Route::get('/users', 'UserController@index');

// NEW (preferred):
use App\Http\Controllers\UserController;

Route::get('/users', [UserController::class, 'index']);
```

**Action:** Keep old syntax initially (works with namespace property), migrate later if desired.

### 8. Move & Namespace Seeders

**Steps:**

```bash
# Create new directory
mkdir -p database/seeders

# Move seeders
mv database/seeds/* database/seeders/

# Remove old directory
rmdir database/seeds
```

**Update each seeder:**

```php
<?php

namespace Database\Seeders; // ADD THIS

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Update seeder calls if using class-based factories
        \App\Models\User::factory(10)->create();
    }
}
```

**Update `composer.json`:**

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Database\\Factories\\": "database/factories/",
        "Database\\Seeders\\": "database/seeders/"
    }
}
```

Run: `composer dump-autoload`

### 9. Handle Factories

**Option A: Legacy Factories (Recommended Initially)**

```bash
composer require laravel/legacy-factories --dev
```

Keep existing factory files as-is.

**Option B: Migrate to Class-Based Factories**

Create factory classes for each model:

```php
// database/factories/UserFactory.php
namespace Database\Factories;

use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => bcrypt('password'),
            'role' => User::ROLE_USER,
        ];
    }
}
```

Add `HasFactory` trait to models:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory;
}
```

### 10. Update Test Assertions

**Changes:**

```php
// assertExactJson now available
$response->assertExactJson(['name' => 'Taylor']);

// decodeResponseJson() visibility changed
// Update if calling directly in tests
```

---

## Override System Considerations

**Critical:** FreeScout has **279 override files** that modify core Laravel/Symfony packages.

### Overrides Affected by This Upgrade

1. **Illuminate\Foundation\Application** - `environment()` signature already partially fixed
2. **Illuminate\Container\Container** - May need updates for 8.x
3. **Symfony components** - Moving from Symfony 4.x → 5.x
4. **SwiftMailer** - Still used in 8.x but deprecated (removed in 9.x)

### Override Strategy for This Upgrade

**Phase 1 (This Upgrade - Get to 8.x):**
1. Update critical overrides for compatibility
2. Document which overrides are blocking
3. Create workarounds for most critical issues

**Phase 2 (Post-8.x):**
1. Systematic override reduction (279 → <50)
2. Refactor to proper Laravel patterns
3. Contribute fixes upstream where possible

### Known Override Issues

From previous attempt at 5.7 → 5.8:

- `Application::environment()` - Already updated to variadic in override
- `Container` class - May have compatibility issues
- Many Symfony classes - Need review for Symfony 5.x compatibility

**Action Required:**
1. After composer update, check for fatal errors
2. Review each error and update override accordingly
3. Consider temporarily disabling non-critical overrides
4. Document which overrides must be fixed vs. can be removed

---

## Step-by-Step Upgrade Process

### Step 1: Prepare

```bash
# 1. Create branch (already done)
git checkout claude/laravel-upgrade-plan-01C6YSfLUtgS6JmhvQ8eoBVU

# 2. Backup database
mysqldump freescout > backup_pre_laravel8.sql

# 3. Commit current state
git add -A
git commit -m "Pre-Laravel 8.x upgrade checkpoint"
git push
```

### Step 2: Update composer.json

```json
{
    "require": {
        "php": "^7.3|^8.0",
        "laravel/framework": "^8.75",
        "guzzlehttp/guzzle": "^7.0.1",
        "laravel/tinker": "^2.5",

        "fakerphp/faker": "^1.9.1",
        "nesbot/carbon": "^2.0",

        "laravel/helpers": "^1.4",
        "laravel/legacy-factories": "^1.0"
    },
    "require-dev": {
        "facade/ignition": "^2.5",
        "nunomaduro/collision": "^5.0",
        "phpunit/phpunit": "^9.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    }
}
```

### Step 3: Run Composer Update

```bash
composer update --ignore-platform-reqs -W
```

**Expected:** Errors from override files

### Step 4: Fix Override Conflicts

**Process:**
1. Note each fatal error
2. Open override file causing error
3. Compare with vendor file in Laravel 8.x
4. Update override to match new signature/structure
5. Test
6. Repeat

**Common Fixes:**

```php
// Update exception type hints
Exception → Throwable

// Update Application contract methods
public function environment()
→ public function environment(...$environments)

// Update queue properties
$retryAfter → $backoff
$timeoutAt → $retryUntil
```

### Step 5: Update Configuration Files

```bash
# Run in sequence:

# 1. Update exception handler
# Edit app/Exceptions/Handler.php (see above)

# 2. Update app service provider
# Edit app/Providers/AppServiceProvider.php (see above)

# 3. Update route service provider
# Edit app/Providers/RouteServiceProvider.php (see above)

# 4. Update config files
# Edit config/session.php, config/queue.php, etc.

# 5. Update .env
# MAIL_DRIVER → MAIL_MAILER
# Add REDIS_CLIENT=predis (if using predis)
```

### Step 6: Move Seeders

```bash
mkdir -p database/seeders
mv database/seeds/* database/seeders/ 2>/dev/null || true
rmdir database/seeds 2>/dev/null || true

# Update each seeder file with namespace
# (see above)

composer dump-autoload
```

### Step 7: Update Application Code

```bash
# 1. Update job classes
# app/Jobs/*.php - rename properties, update failed()

# 2. Update policies
# app/Policies/*.php - add viewAny()

# 3. Update commands
# app/Console/Commands/*.php - return integers

# 4. Update cache calls
# Search for Cache:: - update TTL

# 5. Update password validation
# Search for password min:6 - change to min:8
```

### Step 8: Update Maintenance Mode Check

```php
// Edit public/index.php
// Add after autoloader (see above)
```

### Step 9: Run Migrations

```bash
# Generate failed_jobs migration with UUID
php artisan queue:failed-table

# Check the migration, ensure UUID column exists
# database/migrations/xxxx_create_failed_jobs_table.php

# Run migrations
php artisan migrate --force
```

### Step 10: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

composer dump-autoload
```

### Step 11: Test Core Functionality

**Manual Testing Checklist:**

- [ ] Application loads without fatal errors
- [ ] Login/logout works
- [ ] Dashboard displays
- [ ] Can view mailbox
- [ ] Can view conversation
- [ ] Can reply to conversation (creates thread)
- [ ] Can create new conversation
- [ ] Email fetching works (check logs)
- [ ] Queue processing works
- [ ] Module system loads
- [ ] Settings pages load
- [ ] User management works

**Command Testing:**

```bash
# Test artisan commands
php artisan list

# Test email fetch (dry run if possible)
php artisan freescout:fetch-emails

# Test queue worker
php artisan queue:work --once

# Check for errors
tail -f storage/logs/laravel-*.log
```

### Step 12: Run Automated Tests

```bash
# Run test suite
php artisan test

# Or phpunit
vendor/bin/phpunit
```

**Expected:** Some tests may fail due to framework changes. Update as needed.

### Step 13: Commit & Push

```bash
git add -A
git commit -m "$(cat <<'EOF'
Upgrade to Laravel 8.x (jumped from 5.7)

BREAKING CHANGES ADDRESSED:
- Updated composer.json to Laravel 8.75
- Updated PHP minimum to 7.3
- Updated all first-party packages
- Migrated to Symfony 5.x
- Installed laravel/helpers for str_*/array_* functions
- Installed laravel/legacy-factories for old factory syntax
- Updated exception handler to use Throwable
- Added Paginator::useBootstrap() for Bootstrap pagination
- Moved seeders to database/seeders/ with namespace
- Updated all job classes (retryAfter → backoff)
- Added viewAny() to all policies
- Updated password validation to min:8
- Updated config files for Laravel 8
- Added UUID to failed_jobs table
- Updated maintenance mode check in public/index.php
- Fixed override files for Laravel 8 compatibility

INTERMEDIATE VERSION CHANGES APPLIED:
- 5.7→5.8: Cache TTL to seconds, password min 8
- 5.8→6.x: String/array helpers, viewAny policies
- 6.x→7.x: Symfony 5, date serialization, Throwable
- 7.x→8.x: Model factories, seeders, pagination

TESTING:
- Core functionality verified
- Email system tested
- Queue processing tested
- Module system loaded

See LARAVEL_8_UPGRADE_PLAN.md for complete documentation.
EOF
)"

git push -u origin claude/laravel-upgrade-plan-01C6YSfLUtgS6JmhvQ8eoBVU
```

---

## Post-Upgrade Optimization

### 1. Gradually Migrate from Helpers

Replace `str_*` / `array_*` with `Str::` / `Arr::` classes:

```bash
# Find usage
grep -r "str_limit\|str_slug\|array_get" app/

# Replace gradually
# Update one file at a time, test, commit
```

### 2. Migrate to Class-Based Factories

Create factory classes and remove `laravel/legacy-factories`:

```bash
php artisan make:factory UserFactory --model=User
```

### 3. Update Route Syntax

Migrate to class-based route syntax:

```php
use App\Http\Controllers\ConversationController;

Route::get('/conversation/{id}', [ConversationController::class, 'view']);
```

### 4. Review Override System

Systematically reduce overrides from 279 to <50:

1. List all overrides
2. Categorize by purpose
3. Determine if still needed
4. Refactor to proper Laravel patterns
5. Remove obsolete overrides

---

## Rollback Plan

If upgrade fails:

```bash
# 1. Checkout previous commit
git log --oneline
git checkout <commit-hash-before-upgrade>

# 2. Restore database
mysql freescout < backup_pre_laravel8.sql

# 3. Reinstall dependencies
composer install --ignore-platform-reqs

# 4. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

---

## Success Criteria

- [ ] Application loads without errors
- [ ] All core functionality works
- [ ] Email fetching/sending works
- [ ] Queue processing works
- [ ] Module system loads
- [ ] No critical errors in logs
- [ ] Performance acceptable
- [ ] Tests pass (or updated to pass)

---

## Next Steps After Laravel 8.x

Once stable on Laravel 8.x:

1. **Laravel 8 → 9** (Major: Symfony Mailer replaces SwiftMailer)
2. **Laravel 9 → 10**
3. **Laravel 10 → 11**
4. **Laravel 11 → 12**

Each should be significantly easier than the 5.7 → 8.x jump.

---

**Document Version:** 1.0
**Last Updated:** 2025-11-22
**Status:** Ready for Implementation
