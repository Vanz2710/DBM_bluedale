<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Territory;
use Illuminate\Http\Request;

class TerritoryController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Territory::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:territories,name',
            'description' => 'nullable|string|max:255',
        ]);

        $territory = Territory::create($validated);

        return response()->json(['data' => $territory], 201);
    }

    public function update(Request $request, Territory $territory)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:territories,name,' . $territory->id,
            'description' => 'nullable|string|max:255',
        ]);

        $territory->update($validated);

        return response()->json(['data' => $territory]);
    }

    public function destroy(Territory $territory)
    {
        $territory->delete();

        return response()->json(['status' => 'deleted']);
    }

    public function stats()
    {
        $territories = Territory::withCount('contacts')
            ->orderBy('name')
            ->get()
            ->map(fn($t) => [
                'id'             => $t->id,
                'name'           => $t->name,
                'description'    => $t->description,
                'contacts_count' => $t->contacts_count,
            ]);

        return response()->json(['data' => $territories]);
    }
}
