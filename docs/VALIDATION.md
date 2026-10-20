# Validation record

Build checked locally on 20 October 2026.

- Laravel 12.70.0 with locked dependencies resolved for PHP 8.2.0.
- PHP 8.5.11: application suite passed with SQLite and MariaDB 10.4.32.
- PHP 8.2.12: the same suite passed with SQLite and MariaDB 10.4.32.
- Tests cover authentication, household isolation, preferences, weekly generation and idempotency, Sunday roast, separate dinners, packed/WFH lunches, shopping quantities, expiry, duplicate ingredients, unknown counts, kit deductions, rollback, double cooking, verified empty drawers and cooked-meal history protection.
- Final storage lifecycle tests cover renaming, removal, occupied locations and cross-household access.
- Composer validation, installed platform requirements, code formatting and Blade view compilation checked.
- Headless Microsoft Edge checked account registration, starter-week generation, stock entry, six main pages and horizontal overflow at a 390px viewport. Desktop and mobile screenshots reviewed. Browser test accounts were created only in `kitchen_hq_test`.
- Application database: `kitchen_hq`. Disposable integration database: `kitchen_hq_test`. No default user credentials or invented household inventory.
- Main local app: http://127.0.0.1:8173. Ports 8000/8001 belong to other local applications.

Run `php artisan test` for isolated in-memory SQLite tests. The local-only `phpunit.mariadb.xml` points to `kitchen_hq_test`; it is ignored by Git. Run `php vendor/bin/phpunit -c phpunit.mariadb.xml` only after verifying its database settings. Never use a live household database for tests using RefreshDatabase.

The production Forge deployment and GitHub workflow have not been executed. No remote repository or production server was changed.
