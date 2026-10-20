# Kitchen HQ — Laravel project brief

## Platform
New standalone Laravel project, Windows XAMPP, PHP 8.2, MariaDB 10.4, VS Code, later Laravel Forge/DigitalOcean. Confirm Laravel/PHP compatibility. Mobile-first, authenticated and private.

## Confirmed user needs
- Pantry/fridge/freezer stock inventory with qualitative levels and optional measured quantities.
- Separate household profiles and food preferences.
- Weekly plan with separate earlier dinner for Noah and later dinner for adults; Sunday roast; two SimplyCook meals Monday–Thursday.
- Use freezer stock to empty **one whole drawer** before beginning batch prep.
- When space is available, freeze main dishes in two-person portions; prepare rice/pasta/noodles fresh.
- Recipe-kit library for 31 reported physical SimplyCook kits, duplicates tracked, vegetarian-to-meat adaptations, use supplied flavour pots.
- Shopping list built from meals and actual stock, plus packed lunches, fruit and snacks.
- Work-from-home lunches using freezer foods, balanced for evening running and gradual weight loss.

## Proposed software features, not established household facts
Dashboard, freezer drawer visual tracker, automatic shopping calculations, photo/barcode input, AI recipe transcription, expiry alerts and auto-deduction. Prioritise a reliable inventory and weekly planner MVP before AI.

## Implementation order
1. Authentication and household model; schema, provenance and confidence fields.
2. Inventory import with explicit reconciliation of beef mince OUT.
3. Preferences and rule-driven weekly planner.
4. Shopping checklist and stock reconciliation.
5. Freezer drawer tracker and batch-prep workflow.
6. Recipe-kit verification and photo transcription.

## Acceptance criteria
- Running the importer twice creates no duplicates.
- Missing data does not delete prior records.
- Null numeric quantity stays null.
- Beef mince is OUT even if an earlier source said Plenty.
- Burgers remain UNKNOWN, not conflated with beef mince.
- A dislike is never treated as an allergy.
- Proposed sample meals are not presented as confirmed user decisions.
