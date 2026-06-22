<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSignature;
use Illuminate\Http\Request;

class UserSignatureController extends Controller
{
    public function getOwn(Request $request)
    {
        $sig = UserSignature::where('user_id', $request->user()->id)->first();
        return response()->json(['signature_data' => $sig?->signature_data]);
    }

    public function saveOwn(Request $request)
    {
        $request->validate([
            'signature_data' => ['required', 'string', 'max:600000'],
        ]);

        UserSignature::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['signature_data' => $request->signature_data],
        );

        return response()->json(['ok' => true]);
    }

    public function listAll()
    {
        $signatures = UserSignature::with('user:id,name,email')
            ->get()
            ->map(fn($s) => [
                'user_id'        => $s->user_id,
                'user_name'      => $s->user->name ?? '—',
                'user_email'     => $s->user->email ?? '—',
                'signature_data' => $s->signature_data,
                'updated_at'     => $s->updated_at->toDateTimeString(),
            ]);

        return response()->json($signatures);
    }

    public function deleteFor(User $user)
    {
        UserSignature::where('user_id', $user->id)->delete();
        return response()->json(['ok' => true]);
    }
}
