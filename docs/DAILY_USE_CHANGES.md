# Daily-use Kitchen HQ update

The confirmed 20–26 October 2026 meal plan is applied by `php artisan kitchen-hq:apply-week <email>` after migrations. It is scoped to that account's household and safe to repeat. This does not create or reset the login. Original import payloads remain in household_imports.

Stock is shown in Freezer, Cupboard and Fridge groups. The three choices are Plenty, Nearly out and Out. Levels that were never supplied stay blank until chosen. Photo additions have unknown remaining amounts. Storage grouping is inferred and editable. Choosing a qualitative level clears a stale measured count (or sets zero for Out), so cooking cannot silently deduct an old amount.

The shopping page combines uncooked meals, imported staples/packed lunches, and low/out replenishment. It separates Pick up, Check before buying, and Already have. A small explicit alias map matches items such as garden peas/peas and frozen chips/chips. Grouped or ambiguous items are not broadly fuzzy-matched. No measured purchase quantities are invented. Ticks are saved per household/week, and do not add purchased stock automatically. Confirmed meal ingredient names are shopping outlines; full methods are still pending. Lunch entries are editable suggestions derived from preferences.

OpenAI settings are under Kitchen assistant. API keys are encrypted with Laravel's app key, hidden from serialization and validation flash data, and never rendered back. Requests use the Responses API with store=false, timeouts and output limits. Keys and account details are excluded from the context payload. A user click sends kitchen data; no background calls run. API answers are escaped text and cannot mutate saved stock or meals. The default model is editable. Live connectivity requires the user's API key and API billing; automated tests use a fake endpoint.

Move to production: include these source changes, run migrations and transfer the household database securely. Preserve APP_KEY when moving encrypted OpenAI settings. Do not run the original generic DatabaseSeeder on the imported household unless its sample templates are wanted; the personal import already populated the profiles and verified kit labels.

References used: https://developers.openai.com/api/docs/quickstart and https://developers.openai.com/api/reference/resources/responses/methods/create
