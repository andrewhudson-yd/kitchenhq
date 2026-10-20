<?php

namespace App\Services;

use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PersonalHouseholdImport
{
    public function run(string $email, string $passwordHash): User
    {
        return DB::transaction(function () use ($email, $passwordHash) {
            $data = [];
            foreach (['household', 'inventory', 'meal_rules', 'shopping_staples', 'simplycook_kits', 'verified_kit_labels'] as $file) {
                $data[$file] = json_decode(file_get_contents(database_path("data/kitchen-hq/$file.json")), true, 512, JSON_THROW_ON_ERROR);
            }
            $user = User::where('email', strtolower($email))->first();
            $h = $user?->household ?? Household::create(['name' => 'Kitchen HQ', 'join_code' => hash('sha256', Str::random(32))]);
            $user ??= new User(['email' => strtolower($email), 'name' => 'You']);
            $user->fill(['household_id' => $h->id, 'password' => $passwordHash])->save();
            // Once applied, reruns do not overwrite edits or later stock events.
            if (DB::table('household_imports')->where('household_id', $h->id)->where('source_key', 'personal-profile-v1')->exists()) {
                return $user;
            }
            foreach ($data['household']['people'] as $person) {
                $p = $person['confirmed'];
                $h->profiles()->firstOrCreate(['name' => $person['name'] ?? 'You'], [
                    'age' => $person['age'] ?? null,
                    'likes' => implode('; ', $p['likes'] ?? []),
                    'dislikes' => implode('; ', $p['dislikes'] ?? []),
                    'notes' => json_encode($p, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).'\nAllergy information not supplied.',
                ]);
            }
            $location = $h->locations()->firstOrCreate(['name' => 'Imported stock — location unverified'], ['type' => 'pantry']);
            foreach ($data['inventory']['items'] as $item) {
                $row = $h->inventory()->firstOrCreate(['name' => $item['name']], [
                    'storage_location_id' => $location->id, 'unit' => 'each',
                    'quantity' => null, 'notes' => 'Unit is a form placeholder; not measured. Category: '.$item['category'].'. '.$item['provenance'].'. '.($item['notes'] ?? ''),
                ]);
                if ($row->wasRecentlyCreated) {
                    $row->forceFill(['qualitative_stock' => $item['stock_level']])->save();
                }
                if (strtolower($item['name']) === 'beef mince') {
                    // Explicit correction applies to every exact-name lot, not burgers.
                    foreach ($h->inventory()->whereRaw('LOWER(name) = ?', ['beef mince'])->get() as $lot) {
                        if ($lot->quantity !== null && (float) $lot->quantity !== 0.0) {
                            $h->inventory();
                            DB::table('stock_movements')->insert(['household_id' => $h->id, 'inventory_item_id' => $lot->id, 'change' => -(float) $lot->quantity, 'reason' => 'User import correction: no beef mince', 'created_at' => now(), 'updated_at' => now()]);
                            $lot->quantity = 0;
                        }
                        $lot->forceFill(['qualitative_stock' => 'out'])->save();
                    }
                }
            }
            foreach ($data['verified_kit_labels']['kits'] as $kit) {
                $k = $h->kits()->firstOrCreate(['name' => $kit['box_name'], 'brand' => 'SimplyCook'], ['quantity' => null]);
                foreach ($kit['recipes'] as $name) {
                    $h->recipes()->firstOrCreate(['name' => $name], [
                        'recipe_kit_id' => $k->id, 'servings' => 2, 'is_simplycook' => true, 'planning_only' => true,
                        'instructions' => 'Name verified against user photograph '.$kit['source'].'. Full ingredient and method transcription pending; do not mark ready to cook. Kit quantity unknown.',
                    ]);
                }
            }
            DB::table('household_imports')->insert(['household_id' => $h->id, 'source_key' => 'personal-profile-v1', 'payload' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            return $user;
        });
    }
}
