<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPreparedBy;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class UserPreparedByController extends Controller
{
    public function getOwn(Request $request)
    {
        $profile = UserPreparedBy::where('user_id', $request->user()->id)->first();

        return response()->json($profile ? [
            'name'            => $profile->name,
            'title'           => $profile->title,
            'mobile_code'     => $profile->mobile_code,
            'mobile_local'    => $profile->mobile_local,
            'signature_label' => $profile->signature_label,
        ] : null);
    }

    public function saveOwn(Request $request)
    {
        $request->validate([
            'name'            => ['required', 'string', 'max:120'],
            'title'           => ['nullable', 'string', 'max:120'],
            'mobile_code'     => ['required', 'string', 'max:10'],
            'mobile_local'    => ['nullable', 'string', 'max:30'],
            'signature_label' => ['nullable', 'string', 'max:50'],
        ]);

        UserPreparedBy::updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->only(['name', 'title', 'mobile_code', 'mobile_local', 'signature_label']),
        );

        return response()->json(['ok' => true]);
    }

    public function getActive()
    {
        $userId = SystemSetting::get('active_prepared_by_user_id');
        if (!$userId) {
            return response()->json(null);
        }

        $profile = UserPreparedBy::with('user:id,name')->where('user_id', (int) $userId)->first();
        if (!$profile) {
            return response()->json(null);
        }

        return response()->json([
            'user_id'         => $profile->user_id,
            'user_name'       => $profile->user->name ?? '—',
            'name'            => $profile->name,
            'title'           => $profile->title,
            'mobile_code'     => $profile->mobile_code,
            'mobile_local'    => $profile->mobile_local,
            'signature_label' => $profile->signature_label,
        ]);
    }

    public function listAll()
    {
        $activeUserId = (int) SystemSetting::get('active_prepared_by_user_id');

        return response()->json(
            UserPreparedBy::with('user:id,name,email')
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(fn($p) => [
                    'user_id'         => $p->user_id,
                    'user_name'       => $p->user->name ?? '—',
                    'user_email'      => $p->user->email ?? '—',
                    'name'            => $p->name,
                    'title'           => $p->title,
                    'mobile_code'     => $p->mobile_code,
                    'mobile_local'    => $p->mobile_local,
                    'signature_label' => $p->signature_label,
                    'is_active'       => $p->user_id === $activeUserId,
                    'updated_at'      => $p->updated_at->toDateTimeString(),
                ])
        );
    }

    public function setActive(User $user)
    {
        if (!UserPreparedBy::where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'This user has no saved profile.'], 422);
        }

        SystemSetting::set('active_prepared_by_user_id', (string) $user->id);

        return response()->json(['ok' => true]);
    }
}
