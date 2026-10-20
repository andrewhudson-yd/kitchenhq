# Kitchen HQ — full conversation data import pack

Extract this folder into `imports/kitchen-hq/` in your Laravel project.

Give Codex this instruction:

> Read all files under `imports/kitchen-hq/`. Implement Laravel migrations, a validated dry-run import command and idempotent seeders. Import household profiles, inventory, meal rules and shopping staples. Treat null quantity as unknown, not zero. Reconcile beef mince to OUT, overriding any earlier positive record. Never infer an allergy from a dislike or absence. Do not delete existing data when an input section is missing. Recipe kit names and counts require verification against photographs. Add automated import tests.

The JSON files are data, not Laravel migrations. They should not be publicly served. Do not commit personal household details to a public repository.
