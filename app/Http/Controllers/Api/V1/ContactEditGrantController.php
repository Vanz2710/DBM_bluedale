<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactEditGrant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactEditGrantController extends Controller
{
    public function index()
    {
        $grants = ContactEditGrant::with([
            'user:id,name',
            'targetUser:id,name',
            'grantedBy:id,name',
        ])->orderBy('created_at', 'desc')->get();

        return response()->json($grants);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'target_user_id' => 'required|exists:users,id|different:user_id',
        ]);

        $grant = ContactEditGrant::firstOrCreate(
            ['user_id' => $validated['user_id'], 'target_user_id' => $validated['target_user_id']],
            ['granted_by' => Auth::id()]
        );

        $grant->load(['user:id,name', 'targetUser:id,name', 'grantedBy:id,name']);

        return response()->json($grant, 201);
    }

    public function destroy(string $id)
    {
        ContactEditGrant::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }
}
