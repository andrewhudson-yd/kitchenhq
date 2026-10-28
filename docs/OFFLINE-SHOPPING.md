# Offline shopping list (phone app)

In the iOS/Android app, the **Shopping** tab opens a screen that ships inside the app, so the list
works with no signal. Everything you do is saved on the phone and sent to Kitchen HQ as soon as there's
a connection. Changes from the rest of the household show up within ~15 seconds while the list is open.

The website is unchanged for browsers: the Shopping tab only switches to the app screen inside the app.

## How it works

```
Website (signed in)                Phone app                         Laravel
──────────────────                 ─────────                         ───────
public/app-bridge.js ── POST /app/token ───────────────────────────▶ AppShoppingController@token
  stores the device token in the app (NativeApp.storage)              (only the SHA-256 is stored)

Shopping tab (data-native-href) ──▶ bundled /app/shopping/
                                    keeps a copy of the list +
                                    a queue of changes
                                    ── GET  /api/app/shopping ──────▶ the week's list
                                    ── POST /api/app/shopping/sync ─▶ applies queued ops, returns list
```

- **Same rules as the website:** web controllers and the API both go through
  `App\Services\ShoppingActions` (add, tick → restock, quantity, remove).
- **Operations are idempotent** (e.g. "set ticked"), so retrying after a dropped connection is safe.
  A malformed operation is reported and skipped; it never blocks the rest of the queue.
- **Auth:** `Authorization: Bearer <device token>`, checked by `AuthenticateAppToken`.
  Signing out on the website deletes that user's device tokens.
- **Household sharing:** everything is scoped to the household, so partners share one list.
  Other households can't see it (`tests/Feature/AppShoppingTest.php`).
- **CORS:** `/api/*` uses Laravel's default CORS config. The app calls it from `https://localhost`
  (Android) or `capacitor://localhost` (iOS).

## Deploying

1. Deploy this branch (`app-offline-shopping`).
2. `php artisan migrate` (adds the `app_tokens` table).
3. Make sure `public/app-bridge.js` is deployed.
4. Build the app (KitchenHQ-App) with `npm run sync` and install it. Then sign in once with signal;
   after that the shopping list works offline.

Nothing changes for users of the website in a browser.

## Testing end to end on a phone (no deployment needed)

Uses a throwaway database (`kitchen_hq_apptest`), so your local data is untouched.

1. Create and migrate the test database, and add a test account (see `AppShoppingTest` for the setup).
2. Serve Laravel on a spare port with the test database forced. On Windows,
   `DB_DATABASE=… php artisan serve` does **not** reach the app, so use a router script for `php -S`
   that sets `DB_DATABASE` via `putenv()` / `$_ENV` and then `return require …/server.php;`.
   Run it from `public/`.
3. In KitchenHQ-App, rename `app.config.local.e2e-test.json` to `app.config.local.json`, then
   `npm run sync` and build.
4. `adb reverse tcp:8188 tcp:8188` and open the app.

Automated tests: `php artisan test --filter=AppShoppingTest`.
