<?php

namespace App\Http\Controllers;

use App\Services\ShoppingActions;
use Illuminate\Http\Request;

class ShoppingRemovalController extends Controller
{
    public function update(Request $r)
    {
        $data = $r->validate(['week' => 'required|date_format:Y-m-d', 'item_key' => 'required|regex:/^[a-f0-9]{64}$/', 'removed' => 'required|boolean']);
        $h = $r->user()->household()->firstOrFail();
        app(ShoppingActions::class)->remove($h, $data['week'], $data['item_key'], $r->boolean('removed'));
        $response = redirect()->route('shopping', $h->weekParams($data['week']))->with('status', $r->boolean('removed') ? 'Item removed from this week’s shopping list.' : 'Item restored.');
        if ($r->boolean('removed')) {
            $response->with('shopping_removed', ['week' => $data['week'], 'item_key' => $data['item_key']]);
        }
        return $response;
    }
}
