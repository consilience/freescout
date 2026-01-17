# Laravel Upgrade Tasks: v5.5 → v12.x

**Project:** FreeScout Helpdesk
**Current Version:** Laravel 5.5.40 (PHP 7.1+)
**Target Version:** Laravel 12.x (PHP 8.3/8.4)
**Started:** 2025-11-22
**Status:** Planning Complete - Ready to Begin

---

## Executive Summary

This document tracks the stepwise upgrade of FreeScout from Laravel 5.5 to Laravel 12, spanning 7 major versions. The upgrade includes:

- **Laravel:** 5.5 → 5.6 → 5.7 → 5.8 → 6.x → 7.x → 8.x → 9.x → 10.x → 11.x → 12.x
- **PHP:** 7.1+ → 8.3/8.4
- **Testing:** Run existing tests after each step, add new tests
- **Dependencies:** Update 40+ packages to modern versions

### Critical Challenges

1. **279 Override Files** (3.9MB) - Custom modifications to core Laravel/Symfony files
2. **Minimal Test Coverage** - Only 6 test files for 32,000+ lines of code
3. **String-Based Routes** - Need conversion to class-based syntax
4. **Deprecated Patterns** - Auth routes, middleware signatures, helpers
5. **Module System** - nwidart/laravel-modules v2.7 needs major update

---

## PHP Version Compatibility Notice

**Current Environment:** PHP 8.4.15
**Challenge:** Laravel 5.5-7.x were designed for PHP 7.x and are not compatible with PHP 8.x

**Upgrade Strategy:**
- Phases 1-5 (Laravel 5.5 → 7.x): Will use `--ignore-platform-reqs` for installation
  - Limited testing capability due to PHP incompatibilities
  - Focus on code updates and dependency management
  - Full testing deferred until PHP 8-compatible Laravel reached

- Phase 6+ (Laravel 8.x+): Full PHP 8.4 compatibility
  - PHP 8.0+ support starts with Laravel 8.x
  - Full testing capability restored
  - Comprehensive test suite execution

**Alternative:** If production environment has PHP 7.4, consider:
1. Testing phases 1-5 on PHP 7.4 environment
2. Upgrading to PHP 8.x after reaching Laravel 8.x

---

## Upgrade Roadmap

### Phase 0: Preparation ✓

- [x] Codebase analysis complete
- [x] Current state documented
- [x] Upgrade plan created
- [x] Examined composer.json (extensive override system discovered)
- [x] Reviewed existing test suite (6 test files, minimal coverage)
- [x] Documented override system (279 files, 3.9MB - see OVERRIDE_SYSTEM_ANALYSIS.md)
- [x] Attempted dev dependency installation (failed - PHP 8.4 incompatible with Laravel 5.5)
- [x] Attempted test execution (failed - cannot run Laravel 5.5 tests on PHP 8.4)
- [ ] Create baseline test suite (deferred - will create during upgrade)
- [ ] Backup current database schema (if database exists)

**Phase 0 Status:** COMPLETE with critical findings documented

**Key Finding:** Cannot run Laravel 5.5 on PHP 8.4. Must proceed with upgrade to reach PHP 8.4 compatibility.

### Phase 1: Laravel 5.5 → 5.6

**PHP Requirement:** 7.1.3+ (Current env: PHP 8.4.15 - compatibility limited)
**Status:** MOSTLY COMPLETE - Core upgrade done, PHP 8.4 issues present

#### Tasks

- [x] Update composer.json: `laravel/framework: 5.6.*`
- [x] Update dependencies:
  - [x] `symfony/*: ^4.0` (upgraded to 4.4.x)
  - [x] `fideloper/proxy: ^4.0`
  - [x] `laravel/tinker: ^1.0`
- [x] Handle package issues:
  - [x] Removed codedge/laravel-selfupdater (repository access issues)
  - [x] Added GitHub repository override
  - [x] Fixed rap2hpoutre/laravel-log-viewer classmap issue
- [x] Fix model compatibility:
  - [x] Added `getQueueableRelations()` method to Subscription model
- [ ] ⚠️ **PHP 8.4 Compatibility Issues:**
  - [ ] Logger.php deprecation warnings (implicit nullable parameters)
  - [ ] Artisan commands partially working with warnings
  - [ ] Override system conflicts with Symfony 4/PHP 8.4
- [ ] Review override files for 5.6 compatibility (deferred)
- [ ] Database changes (deferred - no database in environment)
- [ ] Run tests (blocked by PHP 8.4 compatibility)
- [ ] Manual testing (blocked by runtime environment)
- [x] Commit changes

**Packages Updated:**
- laravel/framework: v5.5.40 → v5.6.16 ✓
- symfony/*: v3.4.x → v4.4.x ✓
- swiftmailer: v6.1 → v6.3.0 ✓
- dragonmantank/cron-expression: v2.3.1 (replaces mtdowling) ✓

**Packages Removed:**
- codedge/laravel-selfupdater
- symfony/polyfill-php70

**Breaking Changes Encountered:**
- QueueableEntity interface requires `getQueueableRelations()` method
- Symfony 4 updates (mostly compatible through override system)
- PHP 8.4 deprecation warnings in Logger

**Known Issues:**
1. PHP 8.4 deprecation warnings prevent clean artisan execution
2. Override system needs review for Symfony 4 compatibility
3. May need to add `getQueueableRelations()` to other models

**Next Steps:** Continue to Laravel 5.7 to progress toward PHP 8-compatible versions

---

### Phase 2: Laravel 5.6 → 5.7

**PHP Requirement:** 7.1.3+
**Status:** Not Started

#### Tasks

- [ ] Update composer.json: `laravel/framework: ^5.7`
- [ ] Update email verification (if used)
- [ ] Update `config/app.php` localization options
- [ ] Notification changes:
  - [ ] Review notification channels
  - [ ] Update notification routes
- [ ] Update dependencies:
  - [ ] `phpunit/phpunit: ^7.0`
  - [ ] Check compatibility
- [ ] Update error handling:
  - [ ] Review Exceptions/Handler.php
- [ ] Run tests and fix failures
- [ ] Manual testing
- [ ] Commit changes

**Breaking Changes:**
- Asset URL generation changes
- Resource controllers naming
- Email verification scaffolding

---

### Phase 3: Laravel 5.7 → 5.8

**PHP Requirement:** 7.1.3+
**Status:** Not Started

#### Tasks

- [ ] Update composer.json: `laravel/framework: ^5.8`
- [ ] Update deprecated methods:
  - [ ] Replace `hasTooManyLoginAttempts` with `hasTooManyAttempts`
  - [ ] Update policy authorization
- [ ] Database changes:
  - [ ] Update `database/factories` structure
  - [ ] Fix factory definitions
- [ ] Upgrade Artisan commands:
  - [ ] Update command signatures
  - [ ] Review Console/Kernel.php
- [ ] Update middleware:
  - [ ] Verify middleware priorities
- [ ] Cache changes:
  - [ ] Update cache key handling
- [ ] Run tests and fix failures
- [ ] Manual testing
- [ ] Commit changes

**Breaking Changes:**
- Database factory changes
- Cache changes
- Carbon upgrade

---

### Phase 4: Laravel 5.8 → 6.x (LTS)

**PHP Requirement:** 7.2+
**Status:** Not Started

#### Tasks

- [ ] **PHP VERSION UPGRADE TO 7.2+**
- [ ] Update composer.json:
  - [ ] `laravel/framework: ^6.0`
  - [ ] Add `facade/ignition: ^1.0` (replaces whoops)
  - [ ] Remove deprecated packages
- [ ] Update string helpers:
  - [ ] Replace deprecated `str_*` and `array_*` helpers
  - [ ] Use `Illuminate\Support\Str` class
  - [ ] Use `Illuminate\Support\Arr` class
- [ ] Authorization changes:
  - [ ] Update Gate/Policy definitions
- [ ] Lazy collections:
  - [ ] Identify opportunities to use lazy collections
- [ ] Job middleware:
  - [ ] Review job classes
  - [ ] Update job signatures
- [ ] Update password confirmation:
  - [ ] Add password confirmation routes/views if needed
- [ ] Run tests and fix failures
- [ ] Manual testing
- [ ] Commit changes

**Breaking Changes:**
- String/Array helpers removed from global namespace
- Carbon 2.0 upgrade
- Authorization gate changes
- Remove deprecated framework methods

---

### Phase 5: Laravel 6.x → 7.x

**PHP Requirement:** 7.2.5+
**Status:** Not Started

#### Tasks

- [ ] Update composer.json: `laravel/framework: ^7.0`
- [ ] Update dependencies:
  - [ ] `symfony/*: ^5.0`
  - [ ] `phpunit/phpunit: ^8.5|^9.0`
  - [ ] `facade/ignition: ^2.0`
  - [ ] `laravel/tinker: ^2.0`
  - [ ] Update nwidart/laravel-modules compatibility
- [ ] Route model binding changes:
  - [ ] Update custom route binding
- [ ] Update `Blade::component` syntax
- [ ] CORS configuration:
  - [ ] Add `config/cors.php`
  - [ ] Update middleware
- [ ] Date serialization:
  - [ ] Review date serialization in models
  - [ ] Update `serializeDate` if needed
- [ ] Run tests and fix failures
- [ ] Manual testing
- [ ] Commit changes

**Breaking Changes:**
- Symfony 5.0
- Date serialization format changes
- Factory methods namespace changes

---

### Phase 6: Laravel 7.x → 8.x (LTS)

**PHP Requirement:** 7.3+
**Status:** Not Started

#### Tasks

- [ ] **PHP VERSION UPGRADE TO 7.3+**
- [ ] Update composer.json: `laravel/framework: ^8.0`
- [ ] **Convert string routes to class-based routes:**
  - [ ] Update routes/web.php (128 lines)
  - [ ] Update routes/channels.php
  - [ ] Import all controller classes
  - [ ] Example: `'Auth\LoginController@show'` → `[LoginController::class, 'show']`
- [ ] Update model factories:
  - [ ] Convert to class-based factories
  - [ ] Move to `database/factories`
  - [ ] Use new Factory syntax
- [ ] Update pagination:
  - [ ] Views may need updates
  - [ ] Tailwind by default (or bootstrap)
- [ ] Maintenance mode:
  - [ ] Update maintenance mode secret
- [ ] Update middleware:
  - [ ] Update app/Http/Kernel.php
  - [ ] Review middleware signatures
- [ ] Remove deprecated:
  - [ ] `Auth::routes()` - replace with explicit routes
  - [ ] Update event discovery (auto-discovery enabled)
- [ ] Seeder namespace:
  - [ ] Update DatabaseSeeder class
  - [ ] Add `Database\Seeders` namespace
- [ ] Update dependencies:
  - [ ] `facade/ignition: ^2.3.6`
  - [ ] `fideloper/proxy` → `fruitcake/laravel-cors`
  - [ ] Remove deprecated packages
- [ ] Run tests and fix failures
- [ ] Manual testing
- [ ] Commit changes

**Breaking Changes:**
- Model factories complete rewrite
- Pagination view changes
- Route namespace changes
- Seeder namespacing
- Auto-discovery changes

---

### Phase 7: Laravel 8.x → 9.x

**PHP Requirement:** 8.0+
**Status:** Not Started

#### Tasks

- [ ] **PHP VERSION UPGRADE TO 8.0+**
  - [ ] Update PHP syntax (named arguments, attributes, etc.)
  - [ ] Fix deprecated warnings
  - [ ] Update all type hints
- [ ] Update composer.json: `laravel/framework: ^9.0`
- [ ] Update dependencies:
  - [ ] `symfony/*: ^6.0`
  - [ ] `phpunit/phpunit: ^9.5`
  - [ ] `facade/ignition: ^2.17`
  - [ ] `spatie/*` packages to v9-compatible versions
  - [ ] `nwidart/laravel-modules: ^9.0` (if available)
- [ ] Flysystem 3:
  - [ ] Update all filesystem operations
  - [ ] Update storage config
  - [ ] Test file uploads/downloads
- [ ] Update Blade:
  - [ ] Test anonymous components
  - [ ] Update Blade syntax where needed
- [ ] Update Eloquent:
  - [ ] Review accessor/mutator changes
  - [ ] Update to new attribute casting
- [ ] Scout changes (if used):
  - [ ] Update configuration
- [ ] Remove deprecated code:
  - [ ] `dates` property on models → use `casts`
  - [ ] Old accessor/mutator syntax
- [ ] Run tests and fix failures
- [ ] Manual testing
- [ ] Commit changes

**Breaking Changes:**
- PHP 8.0 required (major syntax changes)
- Symfony 6.0
- Flysystem 3.x
- Accessor/Mutator syntax changes
- SwiftMailer → Symfony Mailer

---

### Phase 8: Laravel 9.x → 10.x

**PHP Requirement:** 8.1+
**Status:** Not Started

#### Tasks

- [ ] **PHP VERSION UPGRADE TO 8.1+**
  - [ ] Use PHP 8.1 features (enums, readonly, etc.)
  - [ ] Update type hints throughout
- [ ] Update composer.json: `laravel/framework: ^10.0`
- [ ] Update dependencies:
  - [ ] `phpunit/phpunit: ^10.0`
  - [ ] All packages to v10-compatible
- [ ] Update service providers:
  - [ ] Remove `$namespace` property from RouteServiceProvider
  - [ ] Update route loading
- [ ] Update validation:
  - [ ] Review validation rule changes
  - [ ] Update password validation rules
- [ ] Database changes:
  - [ ] Update schema builder usage
  - [ ] Review migration patterns
- [ ] Remove deprecated methods:
  - [ ] Update to new method signatures
- [ ] Run tests and fix failures
- [ ] Manual testing
- [ ] Commit changes

**Breaking Changes:**
- PHP 8.1 minimum
- PHPUnit 10
- Service provider changes
- Invokable validation rules required
- Native type declarations in framework

---

### Phase 9: Laravel 10.x → 11.x

**PHP Requirement:** 8.2+
**Status:** Not Started

#### Tasks

- [ ] **PHP VERSION UPGRADE TO 8.2+**
  - [ ] Utilize PHP 8.2 features
  - [ ] Review deprecations
- [ ] Update composer.json: `laravel/framework: ^11.0`
- [ ] Application structure changes:
  - [ ] Streamlined skeleton (fewer config files by default)
  - [ ] Review `bootstrap/app.php` changes
  - [ ] Update service provider registration
- [ ] Update middleware:
  - [ ] New middleware customization approach
  - [ ] Update Kernel.php if needed
- [ ] Removed config files (defaults in framework):
  - [ ] Review which configs can be removed
  - [ ] Keep only customized configs
- [ ] Model casts method:
  - [ ] Convert `$casts` property to `casts()` method
- [ ] Update dependencies:
  - [ ] All packages to v11-compatible
- [ ] SQLite driver changes:
  - [ ] Update if using SQLite for testing
- [ ] Run tests and fix failures
- [ ] Manual testing
- [ ] Commit changes

**Breaking Changes:**
- PHP 8.2 minimum
- Streamlined application structure
- Config file changes
- Model casts method
- Password validation rule changes

---

### Phase 10: Laravel 11.x → 12.x

**PHP Requirement:** 8.2+ (recommend 8.3/8.4)
**Status:** Not Started

#### Tasks

- [ ] **PHP VERSION UPGRADE TO 8.3/8.4**
  - [ ] Test with PHP 8.3
  - [ ] Test with PHP 8.4
  - [ ] Optimize for latest PHP features
- [ ] Update composer.json: `laravel/framework: ^12.0`
- [ ] Review Laravel 12 changelog:
  - [ ] Identify breaking changes
  - [ ] Update affected code
- [ ] Update dependencies:
  - [ ] All packages to latest versions
  - [ ] Remove any deprecated packages
- [ ] Performance optimizations:
  - [ ] Review query performance
  - [ ] Optimize eager loading
  - [ ] Review caching strategies
- [ ] Security updates:
  - [ ] Review authentication
  - [ ] Update authorization policies
  - [ ] Security headers
- [ ] Run full test suite
- [ ] Comprehensive manual testing
- [ ] Performance testing
- [ ] Security audit
- [ ] Commit changes

**Breaking Changes:**
- TBD based on Laravel 12 release notes
- Continued framework refinements

---

## Testing Strategy

### Automated Tests

After each upgrade phase:

1. **Run Existing Tests:**
   ```bash
   php artisan test
   vendor/bin/phpunit
   ```

2. **Test Coverage Targets:**
   - Phase 0: Baseline (current: minimal)
   - Phase 4 (L6): 30% coverage
   - Phase 6 (L8): 50% coverage
   - Phase 10 (L12): 70% coverage

3. **New Tests to Write:**
   - [ ] Authentication flow tests
   - [ ] Conversation CRUD tests
   - [ ] Customer management tests
   - [ ] Email fetching tests
   - [ ] Module system tests
   - [ ] API endpoint tests
   - [ ] Mail sending tests
   - [ ] Queue job tests
   - [ ] Real-time broadcast tests
   - [ ] Middleware tests

### Manual Testing Checklist

After each phase, test:

- [ ] **Authentication:**
  - [ ] Login
  - [ ] Logout
  - [ ] Password reset
  - [ ] User registration (if enabled)
  - [ ] Role-based access

- [ ] **Mailbox Management:**
  - [ ] Create mailbox
  - [ ] Edit mailbox
  - [ ] Delete mailbox
  - [ ] Email fetching (IMAP)
  - [ ] Email sending

- [ ] **Conversations:**
  - [ ] Create conversation
  - [ ] Reply to conversation
  - [ ] Assign conversation
  - [ ] Change status
  - [ ] Add notes
  - [ ] Attachments
  - [ ] Drafts

- [ ] **Customers:**
  - [ ] Create customer
  - [ ] Edit customer
  - [ ] Merge customers
  - [ ] View history

- [ ] **Real-time Features:**
  - [ ] Live notifications
  - [ ] Chat updates
  - [ ] Conversation viewer status

- [ ] **Module System:**
  - [ ] Install module
  - [ ] Activate/deactivate
  - [ ] Module licensing
  - [ ] Module updates

- [ ] **Scheduled Tasks:**
  - [ ] Email fetching (every minute)
  - [ ] Send monitor (every 10 min)
  - [ ] Queue processing

- [ ] **Admin Functions:**
  - [ ] System settings
  - [ ] User management
  - [ ] Activity logs
  - [ ] System logs

---

## Dependency Updates

### Major Package Upgrades

| Package | Current | Target (L12) | Priority |
|---------|---------|--------------|----------|
| laravel/framework | 5.5.40 | ^12.0 | Critical |
| symfony/* | 3.4.x | 7.x | Critical |
| nwidart/laravel-modules | 2.7.0 | 11.x | High |
| phpunit/phpunit | 9.5.28 | ^11.0 | High |
| spatie/laravel-activitylog | 2.7.0 | ^4.8 | High |
| barryvdh/laravel-debugbar | 3.2.0 | ^3.13 | Medium |
| doctrine/dbal | 2.12.1 | ^4.0 | Medium |
| webklex/php-imap | 4.1.1 | ^5.0 | High |
| guzzlehttp/guzzle | * | ^7.8 | Medium |
| mockery/mockery | 1.1.0 | ^1.6 | Medium |

### Packages to Remove/Replace

- **fzaninotto/faker** → `fakerphp/faker`
- **SwiftMailer** → Symfony Mailer (Laravel 9+)
- **fideloper/proxy** → built-in trusted proxies (Laravel 8+)

---

## Override Files Strategy

### Current State: 279 Override Files

Location: `/overrides/` (3.9MB)

### Analysis Phases

- [ ] **Phase 0:** Document all overrides
  - [ ] Categorize by package
  - [ ] Identify purpose of each override
  - [ ] Assess if still needed in modern Laravel

- [ ] **Phase 1-3 (L5.6-5.8):** Minimal override updates
  - [ ] Only update breaking overrides
  - [ ] Test override compatibility

- [ ] **Phase 4-6 (L6-L8):** Major override review
  - [ ] Check if Laravel now includes override functionality
  - [ ] Refactor to extend instead of override where possible
  - [ ] Document remaining necessary overrides

- [ ] **Phase 7-10 (L9-L12):** Minimize overrides
  - [ ] Target <50 override files
  - [ ] Use proper extension/events instead
  - [ ] Document why each remaining override is necessary

### Override Categories

1. **Laravel Core** (Illuminate\*)
2. **Symfony Components**
3. **Third-party Packages** (Barryvdh, Nwidart, etc.)
4. **Mail Libraries** (SwiftMailer)
5. **Database Libraries** (Doctrine)

---

## Risk Mitigation

### High Risk Areas

1. **Module System (nwidart/laravel-modules)**
   - Critical for application architecture
   - May have compatibility issues
   - Mitigation: Test thoroughly, consider alternatives

2. **Override Files**
   - 279 files modifying core behavior
   - May break during upgrades
   - Mitigation: Phase-wise review and reduction

3. **Real-time Broadcasting**
   - Complex Polycast integration
   - May break with newer Laravel
   - Mitigation: Test early, consider modern alternatives

4. **Mail System**
   - SwiftMailer → Symfony Mailer (L9)
   - Critical business function
   - Mitigation: Comprehensive testing, backup strategy

### Rollback Strategy

For each phase:
1. Git branch per upgrade step
2. Database snapshot before testing
3. Keep previous version's composer.lock
4. Document rollback steps

---

## Success Criteria

### Phase Completion Checklist

Each phase is complete when:

- [ ] All tests pass
- [ ] No deprecation warnings
- [ ] Manual testing checklist completed
- [ ] Performance is same or better
- [ ] No security regressions
- [ ] Changes committed with detailed message
- [ ] Documentation updated

### Final Success (Laravel 12)

- [ ] All phases complete
- [ ] PHP 8.3/8.4 support confirmed
- [ ] Test coverage >70%
- [ ] Override files <50
- [ ] Performance benchmarks met
- [ ] Security audit passed
- [ ] Production deployment successful

---

## Timeline Estimates

- **Phase 0 (Preparation):** 2-3 days
- **Phase 1 (5.5→5.6):** 1-2 days
- **Phase 2 (5.6→5.7):** 1-2 days
- **Phase 3 (5.7→5.8):** 1-2 days
- **Phase 4 (5.8→6.x):** 3-5 days
- **Phase 5 (6.x→7.x):** 2-3 days
- **Phase 6 (7.x→8.x):** 5-7 days
- **Phase 7 (8.x→9.x):** 5-7 days
- **Phase 8 (9.x→10.x):** 3-5 days
- **Phase 9 (10.x→11.x):** 3-5 days
- **Phase 10 (11.x→12.x):** 3-5 days

**Total Estimated Time:** 30-50 days

---

## Notes

- Each phase builds on the previous
- Do not skip versions
- Test thoroughly between each step
- Document all issues encountered
- Keep improvements.md updated with findings
- Prioritize stability over new features during upgrade

---

**Last Updated:** 2025-11-22
**Next Review:** After Phase 0 completion
