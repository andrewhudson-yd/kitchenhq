<?php

namespace App\Http\Controllers;

use App\Services\PurchaseReconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function prepare(Request $r, PurchaseReconciliation $service, ?int $id = null)
    {
        $h = $r->user()->household()->firstOrFail();
        $names = [];
        if ($id) {
            $draft = DB::table('shopping_photo_drafts')->where('household_id', $h->id)->where('id', $id)->firstOrFail();
            abort_unless($draft->purpose === 'receipt', 422);
            $data = $r->validate(['items' => 'required|string|max:16000']);
            $names = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['items']))));
            validator(['names' => $names], ['names' => 'array|min:1|max:100', 'names.*' => 'required|string|max:150'])->validate();
            $week = $draft->week;
        } else {
            $week = $r->validate(['week' => 'required|date_format:Y-m-d'])['week'];
        }
        $batchId = $service->prepare($h, $week, $names, $id);
        return redirect()->route('purchases.review', $batchId);
    }

    public function review(Request $r, int $id, PurchaseReconciliation $service)
    {
        $h = $r->user()->household()->firstOrFail();
        $batch = DB::table('purchase_batches')->where('household_id', $h->id)->where('id', $id)->firstOrFail();
        return view('purchase-review', ['batch' => $batch, 'rows' => json_decode($batch->rows, true), 'inventory' => $h->inventory()->orderBy('name')->get(), 'shopping' => $service->shopping($h, $batch->week)]);
    }

    public function save(Request $r, int $id, PurchaseReconciliation $service)
    {
        $h = $r->user()->household()->firstOrFail();
        $batch = DB::table('purchase_batches')->where('household_id', $h->id)->where('id', $id)->firstOrFail();
        $data = $r->validate(['rows' => 'required|array|max:200', 'rows.*.include' => 'nullable|boolean', 'rows.*.name' => 'required|string|max:150', 'rows.*.inventory_id' => ['nullable', 'integer', Rule::exists('inventory_items', 'id')->where('household_id', $h->id)], 'rows.*.location' => ['required', Rule::in(['pantry', 'fridge', 'freezer'])], 'rows.*.shopping_key' => 'nullable|string|regex:/^[a-f0-9]{64}$/']);
        $count = $service->save($h, $id, $data['rows']);
        return redirect()->route('shopping', ['week' => $batch->week])->with('status', $count ? "$count purchases saved to Stock and matched shopping items marked as got." : 'These purchases were already saved.');
    }
}
