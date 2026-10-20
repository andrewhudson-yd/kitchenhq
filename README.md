# Kitchen HQ

A standalone Laravel 12, Blade and MariaDB household kitchen MVP. No Node/Vite build is required: the mobile-first interface uses local CSS and server-rendered forms.

## Windows / XAMPP quick start

1. Open this folder in VS Code. Start **MySQL** in the XAMPP Control Panel. The command-line PHP found during setup was **8.5.11**, and MariaDB was **10.4.32**. The project targets PHP **8.2+**; Composer's platform is pinned to 8.2.0 so the lockfile remains installable on PHP 8.2. Tests also passed on the local backup PHP 8.2.12 runtime; the GitHub workflow checks PHP 8.2 and 8.5 on future pushes.
2. In a PowerShell terminal:

   ```powershell
   Set-Location 'C:\xampp\htdocs\dashboard\DL Portfolio\KitchenHQ'
   composer install
   # Only on a fresh checkout with no .env:
   Copy-Item .env.example .env
   php artisan key:generate
   ```

3. Create an empty `kitchen_hq` database in phpMyAdmin, using `utf8mb4_unicode_ci`. Edit `.env` with your local database username/password. Never overwrite an existing `.env` or regenerate its key after storing encrypted sessions.
4. Run:

   ```powershell
   php artisan migrate --seed
   php artisan serve --host=127.0.0.1 --port=8173
   ```

5. Open http://127.0.0.1:8173/register and create your account. There are **no preset logins**. Registration creates the household, profiles, six starter storage locations and recipe templates. No food stock or kit counts are invented. In Meal planner, fill empty slots with the household template.

Required PHP extensions include PDO MySQL, PDO SQLite (tests), mbstring, openssl, tokenizer, XML, ctype, fileinfo, curl and DOM. Composer is required. Run `php -v` to check the selected runtime. `composer dev` is not used; use `php artisan serve`.

## XAMPP Apache

Use a dedicated virtual host whose document root is **KitchenHQ/public**. Do not browse the project root under `htdocs`; it contains private application files. A space in the project path is fine when quoted.

```apache
<VirtualHost *:80>
    ServerName kitchenhq.test
    DocumentRoot "C:/xampp/htdocs/dashboard/DL Portfolio/KitchenHQ/public"
    <Directory "C:/xampp/htdocs/dashboard/DL Portfolio/KitchenHQ/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add `127.0.0.1 kitchenhq.test` to the Windows hosts file, enable Apache `mod_rewrite`, restart Apache, and set `APP_URL=http://kitchenhq.test`. Those machine-wide settings are intentionally left for you. The development server works without them.

## First household setup

- Confirm actual freezer drawer names/counts. The starter four drawers are unverified placeholders, not an imported freezer layout.
- Stock & freezer: add food, its location, quantity and unit. Blank quantity means **unverified**, zero means counted and empty. Use matching names and units in recipes and stock (for example `baby potatoes`, `g`). Different pack sizes and synonyms are not auto-converted.
- Kit shelf: record the total count per brand/name. Two or more flags duplicates. Re-saving replaces the total; it does not add it.
- Recipe ingredients are `food name | quantity | unit`, one per line. Units are `g`, `ml`, `each`, `portion`, `pack`. Quantities are for the recipe's total servings. Suggested seeded recipes are editable starting points, not nutritional prescriptions or extracted recipe cards.
- Replace the two SimplyCook placeholders with actual kit recipes and link each kit. Keep a recipe as a planning placeholder until its ingredients are complete. A kit is consumed per full or partial recipe batch (`ceil(planned servings / recipe servings)`).
- The template adds Noah's seven earlier dinners, seven later adult dinners (including Sunday roast), five packed lunches and five WFH lunches. Tuesday and Thursday reserve the two SimplyCook meals. It fills empty slots without overwriting your plan. Rule warnings remain visible if you change the weekly rhythm. These are reminders, not restrictions on custom meals.
- Family profiles preserve the supplied preferences. Custom meals are not automatically checked for dietary conflicts; review them. Running notes are household context rather than a calculated calorie/macronutrient programme.
- Shopping aggregates uncooked planned meals, scales ingredients by servings and subtracts verified in-date stock. Unknown stock is flagged and is not subtracted. Placeholder recipes are flagged as incomplete. Fruit priorities are a reminder until included in a recipe. Checkboxes are temporary; record purchases in stock separately.
- Cooking uses earliest-expiry stock, rejects insufficient or unknown-only stock, deducts ingredients and kits in a single transaction, logs inventory changes and marks the meal cooked. Repeated clicks do not deduct twice. Expired stock is excluded. Cooked meals are retained, and their recipes cannot be edited; create a new recipe version instead.
- Confirm a drawer after physically checking its contents. At least **one confirmed empty drawer** triggers the batch-prep message. Unknown quantities prevent it. Starter locations are not presumed empty.
- Family → generate an invitation to share access with another adult account. The invitation is a secret; a new code revokes the previous code. All household members have equal editing permissions. Noah's profile is not a login.

## Validation

```powershell
php artisan test
php vendor/bin/pint --test
composer validate --strict
composer check-platform-reqs
php artisan view:cache
```

The normal tests use an in-memory SQLite database, never the household database. MariaDB integration tests must use a **separate disposable database**. Never point `RefreshDatabase` at production or household data. See `docs/VALIDATION.md` for the checks performed in this build.

## Forge / DigitalOcean

1. Create a private Git repository for this folder. Commit `composer.lock`, application source and tests. Never commit `.env`, `vendor`, logs or database files. No repository or remote is created automatically.
2. Provision a Forge-managed server/site with PHP 8.2 or newer and MySQL/MariaDB. Point the site web directory to `/public`. Configure DNS and TLS.
3. Create a dedicated database and user with access only to that database. Set production environment variables: `APP_ENV=production`, `APP_DEBUG=false`, your HTTPS `APP_URL`, `APP_TIMEZONE=Europe/London`, database credentials, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`. Generate a unique application key once with `php artisan key:generate --force` after the environment is installed; preserve it on later deploys.
4. Use `deploy/forge.sh` as the deploy script, adapting `SITE_DIRECTORY`, branch and PHP executable. Configure writable `storage` and `bootstrap/cache` for the Forge site user. The app has no scheduled jobs or mandatory queue workers.
5. Back up the database and `.env` securely before deploys. Migrations are additive; review them and test restoration. Do not run `migrate:fresh` on real data. Run `php artisan migrate --force`, warm caches and check login, dashboard and stock. Restart the relevant PHP service through Forge if required.

This is the working household MVP. Password reset mail, granular owner/member roles, bulk CSV/photo inventory import, nutrition calculations, unit conversion and automated preference-aware meal generation are future extensions. Existing registration/login, invitations, stock editing and all core planning/deduction flows are implemented.

## Source inventory availability

The referenced Recipe Card Summary conversation was accessible only as a bounded recent preview. Generated assistant responses were content-reference placeholders, with no downloadable ZIP, recipe-card files or itemised inventory exposed. All preferences provided in the current request are seeded. Actual inventory starts empty to avoid treating favourite foods as owned stock. If the original ZIP or stock list becomes available, inspect and import it with unknown quantities left null.
