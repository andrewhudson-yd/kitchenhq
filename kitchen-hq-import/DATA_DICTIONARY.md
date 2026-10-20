# Data dictionary and import contract

`stock_level`: `plenty`, `low`, `out`, `unknown`, `present_unknown_amount`. This is qualitative. `out` is confirmed absence; `unknown` is not absence.

`quantity`: nullable number; null = not measured. Do not coerce null to 0. `quantity_verified`: false means no measured count. A qualitative `out` can coexist with null numeric quantity.

`household.people[].confirmed`: user-stated preferences, not medical restrictions or allergies.

`meal_rules.rules[]`: confirmed user requirements, not immutable hard-coded logic; make editable.

`simplycook_kits`: 31 physical / 27 unique were earlier assistant counts accepted by user, not an audited count. Each named recipe has null physical_quantity until independently verified.

`sample_week`: assistant-proposed, not user-approved. Do not mark as an accepted plan.

`shopping_staples`: recurring requested items, not proof of current stock absence. Quantities are null.

## Import contract
Use a stable canonical key per item scoped to household and location/category as appropriate. Prefer upsert to append. Log provenance and conflict resolution. Apply later explicit correction over earlier grouped inventory, particularly `Beef mince: out`. Keep burgers unknown. Never delete records due to empty/missing arrays. Dry-run mode and transaction rollback on validation failures. Preserve user edits newer than imported source unless explicitly reconciled.
