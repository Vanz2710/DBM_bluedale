<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Announcement;
use App\Models\Contact;
use App\Models\ContactArea;
use App\Models\ContactCategory;
use App\Models\ContactIndustry;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\DataInjection;
use App\Models\Deal;
use App\Models\FollowUp;
use App\Models\SystemAlert;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\ToDo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DevPanelController extends Controller
{
    public function info()
    {
        $dbOk = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        try {
            $free  = disk_free_space(base_path()) ?: 0;
            $total = disk_total_space(base_path()) ?: 0;
        } catch (\Throwable $e) {
            $free  = 0;
            $total = 0;
        }

        return response()->json([
            'php'         => PHP_VERSION,
            'laravel'     => app()->version(),
            'env'         => config('app.env'),
            'debug'       => config('app.debug'),
            'url'         => config('app.url'),
            'timezone'    => config('app.timezone'),
            'db_ok'       => $dbOk,
            'db_driver'   => config('database.default'),
            'db_name'     => config('database.connections.' . config('database.default') . '.database'),
            'db_host'     => config('database.connections.' . config('database.default') . '.host'),
            'db_port'     => config('database.connections.' . config('database.default') . '.port'),
            'cache'       => config('cache.default'),
            'queue'       => config('queue.default'),
            'storage_ok'  => is_writable(storage_path()),
            'disk_free'   => $free,
            'disk_total'  => $total,
        ]);
    }

    public function users()
    {
        $users = User::with('roles')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($u) => [
                'id'                    => $u->id,
                'name'                  => $u->name,
                'email'                 => $u->email,
                'is_approved'           => (bool) $u->is_approved,
                'email_verified_at'     => $u->email_verified_at,
                'last_login_at'         => $u->last_login_at,
                'login_count'           => $u->login_count ?? 0,
                'inactivity_flagged_at' => $u->inactivity_flagged_at,
                'created_at'            => $u->created_at,
                'roles'                 => $u->roles->pluck('name')->toArray(),
            ]);

        return response()->json([
            'users' => $users,
            'roles' => Role::pluck('name'),
        ]);
    }

    public function createUser(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'is_approved'       => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($data['role']);

        return response()->json(['ok' => true, 'id' => $user->id]);
    }

    public function updateUser(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if ($request->filled('name'))     $user->name  = $request->name;
        if ($request->filled('email'))    $user->email = $request->email;
        if ($request->filled('password')) $user->password = Hash::make($request->password);

        if ($request->has('is_approved'))           $user->is_approved           = (bool) $request->is_approved;
        if ($request->has('inactivity_flagged_at')) $user->inactivity_flagged_at = $request->inactivity_flagged_at ?: null;
        if ($request->has('email_verified_at'))     $user->email_verified_at     = $request->email_verified_at     ?: null;

        $user->save();

        if ($request->filled('role')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json(['ok' => true]);
    }

    public function deleteUser(int $id)
    {
        User::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function db()
    {
        $tables  = DB::select('SHOW TABLES');
        $dbName  = config('database.connections.' . config('database.default') . '.database');
        $colKey  = 'Tables_in_' . $dbName;

        $stats = array_map(function ($t) use ($colKey) {
            $name = $t->$colKey;
            try {
                $count = DB::table($name)->count();
            } catch (\Throwable) {
                $count = null;
            }
            return ['table' => $name, 'rows' => $count];
        }, $tables);

        usort($stats, fn($a, $b) => ($b['rows'] ?? -1) <=> ($a['rows'] ?? -1));

        return response()->json(['tables' => $stats]);
    }

    public function artisan(Request $request)
    {
        $command = $request->input('command', '');
        $extra   = $request->input('extra', []);

        $whitelist = [
            'migrate'                => ['--force' => true],
            'migrate:rollback'       => ['--force' => true, '--step' => 1],
            'migrate:status'         => [],
            'cache:clear'            => [],
            'config:clear'           => [],
            'config:cache'           => [],
            'route:clear'            => [],
            'route:cache'            => [],
            'view:clear'             => [],
            'view:cache'             => [],
            'queue:restart'          => [],
            'storage:link'           => [],
            'permission:cache-reset' => [],
            'db:seed'                => [],
        ];

        if (!array_key_exists($command, $whitelist)) {
            return response()->json(['error' => 'Command not in allowlist.'], 400);
        }

        $params = $whitelist[$command];

        if ($command === 'db:seed' && !empty($extra['class'])) {
            if (preg_match('/^[A-Za-z0-9_\\\\]+$/', $extra['class'])) {
                $params['--class'] = $extra['class'];
                $params['--force'] = true;
            }
        }

        try {
            Artisan::call($command, $params);
            $output = trim(Artisan::output()) ?: 'Done.';
            return response()->json(['output' => $output]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function settings()
    {
        return response()->json([
            'settings' => SystemSetting::all(['key', 'value']),
        ]);
    }

    public function updateSetting(Request $request)
    {
        $request->validate([
            'key'   => 'required|string',
            'value' => 'nullable|string',
        ]);

        SystemSetting::where('key', $request->key)->update(['value' => $request->value ?? '']);
        return response()->json(['ok' => true]);
    }

    public function addSetting(Request $request)
    {
        $request->validate([
            'key'   => 'required|string|max:191',
            'value' => 'nullable|string',
        ]);

        SystemSetting::set($request->key, $request->value ?? '');
        return response()->json(['ok' => true]);
    }

    public function adminUsers()
    {
        $users = User::role(['admin', 'super-admin'])
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json(['users' => $users]);
    }

    public function maintenanceStatus()
    {
        return response()->json([
            'active'  => SystemSetting::get('maintenance_mode') === '1',
            'message' => SystemSetting::get('maintenance_message') ?? '',
        ]);
    }

    public function setMaintenance(Request $request)
    {
        $request->validate([
            'active'  => 'required|boolean',
            'message' => 'nullable|string|max:500',
        ]);

        SystemSetting::set('maintenance_mode',    $request->boolean('active') ? '1' : '0', 'Maintenance Mode');
        SystemSetting::set('maintenance_message', $request->input('message') ?? '',          'Maintenance Message');
        Cache::forget('__maint');

        return response()->json(['ok' => true]);
    }

    public function activity()
    {
        $users = User::with('roles')
            ->select('id', 'name', 'email', 'last_login_at', 'login_count',
                     'is_approved', 'blocked_at', 'inactivity_flagged_at')
            ->get()
            ->map(function ($u) {
                $latestToken = DB::table('personal_access_tokens')
                    ->where('tokenable_id', $u->id)
                    ->where('tokenable_type', 'App\\Models\\User')
                    ->orderByDesc('last_used_at')
                    ->value('last_used_at');

                $sessionCount = DB::table('personal_access_tokens')
                    ->where('tokenable_id', $u->id)
                    ->where('tokenable_type', 'App\\Models\\User')
                    ->count();

                return [
                    'id'             => $u->id,
                    'name'           => $u->name,
                    'email'          => $u->email,
                    'roles'          => $u->roles->pluck('name')->toArray(),
                    'last_login_at'  => $u->last_login_at,
                    'login_count'    => $u->login_count ?? 0,
                    'last_api_call'  => $latestToken,
                    'active_sessions'=> $sessionCount,
                    'is_blocked'     => !is_null($u->blocked_at),
                    'blocked_at'     => $u->blocked_at,
                    'is_approved'    => (bool) $u->is_approved,
                ];
            })
            ->sortByDesc('last_api_call')
            ->values();

        return response()->json(['users' => $users]);
    }

    public function blockUser(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['blocked_at' => now()]);

        DB::table('personal_access_tokens')
            ->where('tokenable_id', $id)
            ->where('tokenable_type', 'App\\Models\\User')
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function unblockUser(int $id)
    {
        User::findOrFail($id)->update(['blocked_at' => null]);
        return response()->json(['ok' => true]);
    }

    /**
     * Incident-response action: reset to a random password, block login,
     * and revoke every active session in one step. Always recorded in the
     * regular admin audit log (visible at /admin/audit-log) — never silent.
     */
    public function quarantineUser(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user        = User::findOrFail($id);
        $newPassword = Str::password(20);
        $reason      = trim((string) $request->input('reason', ''));

        $user->update([
            'password'   => Hash::make($newPassword),
            'blocked_at' => now(),
        ]);

        DB::table('personal_access_tokens')
            ->where('tokenable_id', $id)
            ->where('tokenable_type', 'App\\Models\\User')
            ->delete();

        AdminAuditLog::create([
            'user_id'     => null,
            'action'      => 'quarantined',
            'entity_type' => 'user',
            'entity_id'   => (string) $user->id,
            'entity_name' => $user->name,
            'old_values'  => null,
            'new_values'  => ['reason' => $reason !== '' ? $reason : null],
            'ip_address'  => $request->ip(),
        ]);

        SystemAlert::notifyAdmins(
            'user_quarantined',
            "User quarantined: {$user->name}",
            "{$user->name} ({$user->email}) was quarantined via the DevPanel — password reset, blocked, and all sessions revoked."
                . ($reason !== '' ? " Reason: {$reason}" : ''),
            '/admin/audit-log'
        );

        return response()->json(['ok' => true, 'password' => $newPassword]);
    }

    /**
     * Mint a fresh Sanctum token for a user without ever touching (or even
     * reading) their password — gated only by the devpanel master key. This
     * is what makes it survive a password change: it doesn't authenticate
     * the password, it authenticates the key. Always audit-logged, same as
     * quarantine, since silently obtaining a live session for someone
     * else's account is security-sensitive.
     *
     * Named identically to a normal login token ('spa-token', see
     * AuthController::login()) rather than something like
     * 'devpanel-login-as' — the token name is shown verbatim to the account
     * owner in their own "Active Sessions" list (MyProfile.vue), and this
     * must not out itself there. The audit log entry below is the
     * accountability trail; the token itself stays indistinguishable from
     * an ordinary session.
     */
    public function loginAs(Request $request, int $id)
    {
        $user = User::with('roles')->findOrFail($id);

        $token = $user->createToken('spa-token')->plainTextToken;

        AdminAuditLog::create([
            'user_id'     => null,
            'action'      => 'devpanel_login_as',
            'entity_type' => 'user',
            'entity_id'   => (string) $user->id,
            'entity_name' => $user->name,
            'old_values'  => null,
            'new_values'  => null,
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    /**
     * One-click variant of loginAs() for the most common case: jump straight
     * into the super-admin account with no user picker.
     */
    public function loginAsSuperAdmin(Request $request)
    {
        $user = User::role('super-admin')->first();

        if (!$user) {
            return response()->json(['error' => 'No super-admin account exists.'], 404);
        }

        return $this->loginAs($request, $user->id);
    }

    public function sendAnnouncement(Request $request)
    {
        $data = $request->validate([
            'title'          => 'required|string|max:200',
            'body'           => 'required|string',
            'urgency'        => 'nullable|in:normal,urgent',
            'target_user_id' => 'nullable|exists:users,id',
            'published_at'   => 'nullable|date',
            'expires_at'     => 'nullable|date',
            'as_user_id'     => 'nullable|exists:users,id',
        ]);

        // Default to the first super-admin if no author is specified
        $authorId = $data['as_user_id'] ?? User::role('super-admin')->value('id')
                    ?? User::role('admin')->value('id');

        Announcement::create([
            'created_by'     => $authorId,
            'title'          => $data['title'],
            'body'           => $data['body'],
            'urgency'        => $data['urgency'] ?? 'normal',
            'target_user_id' => $data['target_user_id'] ?? null,
            'published_at'   => $data['published_at'] ?? now(),
            'expires_at'     => $data['expires_at'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    // ── Data Injection ────────────────────────────────────────────────

    public function listInjections()
    {
        return response()->json([
            'injections' => DataInjection::orderByDesc('created_at')->get(),
        ]);
    }

    public function inject(Request $request)
    {
        $data = $request->validate([
            'preset' => 'required|in:contacts,todos,deals,followups,edge_cases,full_dataset',
            'count'  => 'required|integer|min:1|max:200',
            'label'  => 'nullable|string|max:255',
        ]);

        $preset  = $data['preset'];
        $count   = (int) $data['count'];
        $label   = $data['label'] ?? (ucwords(str_replace('_', ' ', $preset)) . ' — ' . now()->format('Y-m-d H:i'));

        $ids     = [];
        $lookups = $this->getLookups();
        $userId  = User::first()?->id;

        match ($preset) {
            'contacts'    => $this->doInjectContacts($count, $ids, $userId, $lookups),
            'todos'       => $this->doInjectTodos($count, $ids, $userId),
            'deals'       => $this->doInjectDeals($count, $ids, $userId),
            'followups'   => $this->doInjectFollowUps($count, $ids),
            'edge_cases'  => $this->doInjectEdgeCases($ids, $userId, $lookups),
            'full_dataset'=> $this->doInjectFullDataset($count, $ids, $userId, $lookups),
        };

        $totalCount = array_sum(array_map('count', $ids));

        $injection = DataInjection::create([
            'label'        => $label,
            'preset'       => $preset,
            'injected_ids' => $ids,
            'record_count' => $totalCount,
        ]);

        return response()->json(['ok' => true, 'id' => $injection->id, 'count' => $totalCount]);
    }

    public function rollback(int $id)
    {
        $injection = DataInjection::findOrFail($id);
        $ids = $injection->injected_ids ?? [];

        // Hard-delete in reverse dependency order (bypasses model events — safe for test data)
        if (!empty($ids['followups'])) {
            DB::table('follow_ups')->whereIn('id', $ids['followups'])->delete();
        }
        if (!empty($ids['todos'])) {
            DB::table('to_dos')->whereIn('id', $ids['todos'])->delete();
        }
        if (!empty($ids['deals'])) {
            DB::table('deals')->whereIn('id', $ids['deals'])->delete();
        }
        if (!empty($ids['contacts'])) {
            DB::table('contacts')->whereIn('id', $ids['contacts'])->delete();
        }
        if (!empty($ids['users'])) {
            DB::table('users')->whereIn('id', $ids['users'])->delete();
        }

        $injection->delete();

        return response()->json(['ok' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────────

    /**
     * Mirrors AuthController::userPayload() so a devpanel-issued session is
     * indistinguishable from a normal login to the frontend (same shape
     * stored under the `crm_user` localStorage key).
     */
    private function userPayload(User $user): array
    {
        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'username'       => $user->username,
            'email'          => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'roles'          => $user->getRoleNames(),
            'permissions'    => $user->hasRole('super-admin')
                ? null
                : $user->getAllPermissions()->pluck('name'),
            'last_login_at'  => $user->last_login_at,
            'login_count'    => $user->login_count,
        ];
    }

    private function getLookups(): array
    {
        return [
            'status_id'   => ContactStatus::first()?->id,
            'type_id'     => ContactType::first()?->id,
            'category_id' => ContactCategory::first()?->id,
            'industry_id' => ContactIndustry::first()?->id,
            'area_id'     => ContactArea::first()?->id,
        ];
    }

    private function makeContact(string $name, int $userId, array $lookups, array $extra = []): Contact
    {
        return Contact::create(array_merge([
            'name'        => $name,
            'user_id'     => $userId,
            'status_id'   => $lookups['status_id'],
            'type_id'     => $lookups['type_id'],
            'category_id' => $lookups['category_id'],
            'industry_id' => $lookups['industry_id'],
            'area_id'     => $lookups['area_id'],
            'address'     => fake()->address(),
            'remark'      => fake()->sentence(),
            'lead_source' => fake()->randomElement(['Website', 'Referral', 'Cold Call', 'Email', 'Social Media']),
        ], $extra));
    }

    private function doInjectContacts(int $count, array &$ids, ?int $userId, array $lookups): void
    {
        $created = [];
        for ($i = 0; $i < $count; $i++) {
            $created[] = $this->makeContact(fake()->company(), $userId ?? 1, $lookups)->id;
        }
        $ids['contacts'] = $created;
    }

    private function doInjectTodos(int $count, array &$ids, ?int $userId): void
    {
        $contactIds = Contact::inRandomOrder()->limit(max($count, 10))->pluck('id')->toArray();
        if (empty($contactIds)) {
            return;
        }
        $taskId  = Task::first()?->id;
        $created = [];
        for ($i = 0; $i < $count; $i++) {
            $contactId = $contactIds[array_rand($contactIds)];
            $created[] = ToDo::create([
                'contact_id'        => $contactId,
                'user_id'           => $userId,
                'task_id'           => $taskId,
                'todo_date'         => fake()->dateTimeBetween('now', '+90 days')->format('Y-m-d'),
                'date_created'      => now()->format('Y-m-d'),
                'todo_remark'       => fake()->sentence(),
                'completion_status' => 'pending',
            ])->id;
        }
        $ids['todos'] = $created;
    }

    private function doInjectDeals(int $count, array &$ids, ?int $userId): void
    {
        $contactIds = Contact::inRandomOrder()->limit(max($count, 10))->pluck('id')->toArray();
        if (empty($contactIds)) {
            return;
        }
        $stages  = ['prospect', 'proposal', 'negotiation', 'closing'];
        $created = [];
        for ($i = 0; $i < $count; $i++) {
            $created[] = Deal::create([
                'contact_id'          => $contactIds[array_rand($contactIds)],
                'user_id'             => $userId,
                'title'               => fake()->bs(),
                'stage'               => fake()->randomElement($stages),
                'value'               => fake()->randomFloat(2, 500, 150000),
                'probability'         => fake()->numberBetween(10, 90),
                'expected_close_date' => fake()->dateTimeBetween('+7 days', '+180 days')->format('Y-m-d'),
                'status'              => 'open',
                'notes'               => fake()->paragraph(),
            ])->id;
        }
        $ids['deals'] = $created;
    }

    private function doInjectFollowUps(int $count, array &$ids): void
    {
        $todoIds = ToDo::inRandomOrder()->limit(max($count, 10))->pluck('id')->toArray();
        if (empty($todoIds)) {
            return;
        }
        $actionTypes = ['call', 'email', 'visit', 'meeting', 'demo'];
        $created     = [];
        for ($i = 0; $i < $count; $i++) {
            $created[] = FollowUp::create([
                'todo_id'           => $todoIds[array_rand($todoIds)],
                'followup_date'     => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
                'action_type'       => fake()->randomElement($actionTypes),
                'note'              => fake()->sentence(),
                'completion_status' => 'pending',
            ])->id;
        }
        $ids['followups'] = $created;
    }

    private function doInjectEdgeCases(array &$ids, ?int $userId, array $lookups): void
    {
        $uid      = $userId ?? 1;
        $edgeNames = [
            str_repeat('LongCompanyNameTest', 14),                // 252-char truncation test
            '<script>alert("xss")</script> Ltd',                  // XSS probe
            "'; DROP TABLE contacts;-- Inc",                      // SQL injection probe
            '日本語テスト株式会社',                                        // Unicode CJK
            "O'Brien & Associates \"The Best\"",                  // Apostrophes + quotes
            '!@#$%^&*()_+= Corp',                                 // Special chars
            '0',                                                   // Zero-like value
            "\n\r\tWhitespace\n Corp",                            // Embedded newlines
            str_pad('REPEAT', 250, '.'),                          // Near-max-length padding
            '<!-- HTML comment --> Company',                      // HTML comment injection
        ];

        $created = [];
        foreach ($edgeNames as $name) {
            try {
                $created[] = $this->makeContact(substr($name, 0, 255), $uid, $lookups, [
                    'remark' => 'INJECTED_EDGE_CASE',
                ])->id;
            } catch (\Throwable) {
                // skip records the DB itself rejects
            }
        }
        $ids['contacts'] = $created;
    }

    private function doInjectFullDataset(int $count, array &$ids, ?int $userId, array $lookups): void
    {
        $uid     = $userId ?? 1;
        $taskId  = Task::first()?->id;
        $stages  = ['prospect', 'proposal', 'negotiation', 'closing'];

        $contactIds = [];
        $todoIds    = [];
        $dealIds    = [];

        for ($i = 0; $i < $count; $i++) {
            $contact      = $this->makeContact(fake()->company(), $uid, $lookups);
            $contactIds[] = $contact->id;

            // 2 todos per contact
            for ($t = 0; $t < 2; $t++) {
                $todoIds[] = ToDo::create([
                    'contact_id'        => $contact->id,
                    'user_id'           => $uid,
                    'task_id'           => $taskId,
                    'todo_date'         => fake()->dateTimeBetween('now', '+60 days')->format('Y-m-d'),
                    'date_created'      => now()->format('Y-m-d'),
                    'todo_remark'       => fake()->sentence(),
                    'completion_status' => 'pending',
                ])->id;
            }

            // 1 deal per contact
            $dealIds[] = Deal::create([
                'contact_id'          => $contact->id,
                'user_id'             => $uid,
                'title'               => fake()->bs(),
                'stage'               => fake()->randomElement($stages),
                'value'               => fake()->randomFloat(2, 1000, 100000),
                'probability'         => fake()->numberBetween(10, 90),
                'expected_close_date' => fake()->dateTimeBetween('+7 days', '+180 days')->format('Y-m-d'),
                'status'              => 'open',
                'notes'               => fake()->sentence(),
            ])->id;
        }

        $ids['contacts'] = $contactIds;
        $ids['todos']    = $todoIds;
        $ids['deals']    = $dealIds;
    }
}
