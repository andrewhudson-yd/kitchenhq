# Shopping ingredient imports

Shopping → Import CSV / JSON accepts a file or pasted text in the supplied **Standalone meal ingredient exchange 1.0** format. Select the shopping week, preview, review and confirm. Nothing is imported on upload alone. Your paella and Eton Mess examples are available as downloads on the import page.

The importer adds recipe requirements and stock-status annotations in an Imported meal ingredients section below the existing shopping list. It does not create normal recipe-library entries or meal-plan slots. Imported recipe identifiers are local labels, never database IDs.

Recipe quantities, units, dates, servings, reported availability, estimates and notes are preserved. Null recipe quantity with unit `to taste` remains unquantified. No count-to-mass conversions are made; `tsp`, `clove`, `whole` and `nest` remain separate units. Both recipes serving four stay recipes for four diners rather than eight servings combined. Empty purchase quantities remain unknown. Imported `have` means reported stock, not verified sufficiency. Even confirmed stock amounts in the file are reference annotations and never overwrite the inventory.

Imports do not update or deduct inventory, calculate purchases or create receipt/purchase records. Normal shopping, stock and receipt flows remain separate. No AI or external service is used for file parsing.

CSV uses comma-separated UTF-8 data with exact exchange column headings; headings containing different names are rejected with a clear error. Required columns: `ingredient_id,ingredient_name,quantity,unit,inventory_availability,recipe_id,recipe_name,meal_date,meal_type,servings`. Optional columns are shown in the downloadable complete example. Unknown numbers are empty; CSV booleans are lowercase `true` or `false`. Quote comma-containing or multiline values. JSON uses an object with `contract_name`, `contract_version` and an `ingredients` array, with optional matching `meal` and `recipes` metadata. See `resources/import-examples/` for both complete formats.

Limit: 512 KB, 300 ingredient rows. Errors reject the whole input. Drafts are private to the signed-in household/session and expire after 30 minutes. At most three previews are retained per session. Saved imports are scoped to their household and selected shopping week, while the original meal dates are retained.

Equivalent JSON and CSV (including reordered rows and extra formatting) have the same fingerprint and cannot produce duplicate imports. Changed files that repeat a recipe name, meal date and meal type show an overlap warning and require a separate explicit checkbox. To correct an import, remove the old imported file from Shopping and import the corrected version. Removal affects only imported requirements.

Implementation: `meal_ingredient_imports` stores a canonical, validated JSON payload with a household-scoped unique fingerprint. Preview data is held in the existing server-side session; no public uploads are retained. Confirmation and removal serialize using a household database lock.

Validation performed locally on PHP 8.2 with SQLite and MariaDB. Tests cover the supplied 19 ingredients, nullable quantities and uncertainty, cross-format duplicate detection, overlaps, private previews, expiry, validation limits, escaping, household isolation, no inventory/purchase changes and removal. Browser checks cover file upload, review, confirmation, mobile overflow and duplicate detection.
