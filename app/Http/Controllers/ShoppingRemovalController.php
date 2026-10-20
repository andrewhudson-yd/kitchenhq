<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShoppingRemovalController extends Controller
{
    public function update(Request $r)
    {
        $data = $r->validate(['week' => 'required|date_format:Y-m-d', 'item_key' => 'required|regex:/^[a-f0-9]{64}$/', 'removed' => 'required|boolean']);
        DB::table('shopping_checks')->updateOrInsert(['household_id' => $r->user()->household()->firstOrFail()->id, 'week' => $data['week'], 'item_key' => $data['item_key']], ['removed' => $r->boolean('removed')]);
        $response = redirect()->route('shopping', ['week' => $data['week']])->with('status', $r->boolean('removed') ? 'Item removed from this week’s shopping list.' : 'Item restored.');
        if ($r->boolean('removed')) {
            $response->with('shopping_removed', ['week' => $data['week'], 'item_key' => $data['item_key']]);
        }
        return $response;
    }
}
