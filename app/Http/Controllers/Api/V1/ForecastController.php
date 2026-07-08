<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactEditGrant;
use App\Models\Forecast;
use App\Models\ForecastResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        $query   = $this->baseQuery($request);

        $sortField = $request->input('sort_field', 'forecast_date');
        $sortDir   = in_array($request->input('sort_direction'), ['asc', 'desc'], true)
            ? $request->input('sort_direction')
            : 'desc';
        $allowedSorts = ['forecast_date', 'forecast_updatedate', 'amount', 'created_at'];
        if (!in_array($sortField, $allowedSorts, true)) {
            $sortField = 'forecast_date';
        }

        $forecasts = $query->orderBy($sortField, $sortDir)->paginate($perPage);
        $forecasts->getCollection()->transform(fn(Forecast $forecast) => $this->format($forecast));

        return response()->json($forecasts);
    }

    public function summary(Request $request)
    {
        $base = $this->baseQuery($request);

        // Look up result IDs — cached since these rows never change at runtime.
        // Cache a plain array (not a Collection): a serialized Collection can come back
        // as an incomplete object from some cache drivers and fatal on method calls.
        // The `_v2` suffix retires any previously-cached broken Collection without a manual
        // cache:clear — the new code simply reads a fresh key.
        $resultIds  = Cache::remember('forecast_result_ids_v2', 3600, fn () =>
            ForecastResult::whereIn('name', ['Confirmed', 'Pending', 'Rejected'])->pluck('id', 'name')->all()
        );
        $noResultId = Cache::remember('forecast_no_result_id_v2', 3600, fn () =>
            ForecastResult::where('name', 'No Result')->value('id') ?? -1
        );

        // Single conditional-aggregate query replaces 6 separate sum() queries
        $agg = (clone $base)->toBase()->selectRaw(
            'COUNT(*) as forecast_count,
             SUM(amount) as total_amount,
             SUM(CASE WHEN result_id = ? THEN amount ELSE 0 END) as confirmed_amount,
             SUM(CASE WHEN result_id = ? THEN amount ELSE 0 END) as pending_amount,
             SUM(CASE WHEN result_id = ? THEN amount ELSE 0 END) as rejected_amount,
             SUM(CASE WHEN result_id IS NULL OR result_id = ? THEN amount ELSE 0 END) as no_result_amount',
            [
                $resultIds['Confirmed'] ?? null,
                $resultIds['Pending'] ?? null,
                $resultIds['Rejected'] ?? null,
                $noResultId,
            ]
        )->first();

        $totals = [
            'forecast_count'   => (int)   ($agg->forecast_count   ?? 0),
            'total_amount'     => (float) ($agg->total_amount     ?? 0),
            'confirmed_amount' => (float) ($agg->confirmed_amount ?? 0),
            'pending_amount'   => (float) ($agg->pending_amount   ?? 0),
            'rejected_amount'  => (float) ($agg->rejected_amount  ?? 0),
            'no_result_amount' => (float) ($agg->no_result_amount ?? 0),
        ];

        // Monthly breakdown via GROUP BY
        $monthlyRaw = (clone $base)
            ->toBase()
            ->selectRaw('MONTH(forecast_date) as month, COUNT(*) as `count`, SUM(amount) as amount')
            ->groupByRaw('MONTH(forecast_date)')
            ->get()
            ->keyBy('month');

        $months = [];
        foreach (range(1, 12) as $m) {
            $entry    = $monthlyRaw->get($m);
            $months[] = [
                'month'  => $m,
                'count'  => (int) ($entry->count ?? 0),
                'amount' => (float) ($entry->amount ?? 0.0),
            ];
        }

        // Skip full row load when caller only needs totals (e.g. ForecastList header stats)
        if ($request->boolean('totals_only')) {
            return response()->json([
                'data' => ['totals' => $totals, 'months' => $months, 'rows' => []],
            ]);
        }

        $perPage  = min((int) $request->input('per_page', 25), 500);
        $paginated = (clone $base)->orderBy('forecast_date')->paginate($perPage);

        return response()->json([
            'data' => [
                'totals' => $totals,
                'months' => $months,
                'rows'   => $paginated->getCollection()->map(fn(Forecast $forecast) => $this->format($forecast))->values(),
            ],
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $contact   = Contact::findOrFail($validated['contact_id']);
        $user      = Auth::user();
        $isAdmin   = $user->hasRole(['admin', 'super-admin']);
        $assignedUserId = $isAdmin
            ? ($validated['assigned_user_id'] ?? $contact->user_id ?? Auth::id())
            : Auth::id();

        $forecast = Forecast::create([
            'contact_id'           => $contact->id,
            'user_id'              => $assignedUserId,
            'product_id'           => $validated['product_id'],
            'forecast_type_id'     => $validated['forecast_type_id'],
            'result_id'            => $validated['result_id'] ?? null,
            'contact_status_id'    => $contact->status_id,
            'contact_type_id'      => $contact->type_id,
            'amount'               => $validated['amount'],
            'forecast_date'        => $validated['forecast_date'],
            'forecast_updatedate'  => now()->toDateString(),
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $this->format($forecast->fresh($this->relations())),
        ], 201);
    }

    public function show(Forecast $forecast)
    {
        $this->abortIfHidden($forecast);

        return response()->json([
            'data' => $this->format($forecast->load($this->relations())),
        ]);
    }

    public function update(Request $request, Forecast $forecast)
    {
        $this->abortIfHidden($forecast);

        $validated = $this->validatePayload($request);
        $contact   = Contact::findOrFail($validated['contact_id']);
        $user      = Auth::user();
        $isAdmin   = $user->hasRole(['admin', 'super-admin']);

        $forecast->update([
            'contact_id'           => $contact->id,
            'user_id'              => ($isAdmin && array_key_exists('assigned_user_id', $validated) && $validated['assigned_user_id'])
                ? $validated['assigned_user_id']
                : $forecast->user_id,
            'product_id'           => $validated['product_id'],
            'forecast_type_id'     => $validated['forecast_type_id'],
            'result_id'            => $validated['result_id'] ?? null,
            'contact_status_id'    => $contact->status_id,
            'contact_type_id'      => $contact->type_id,
            'amount'               => $validated['amount'],
            'forecast_date'        => $validated['forecast_date'],
            'forecast_updatedate'  => now()->toDateString(),
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $this->format($forecast->fresh($this->relations())),
        ]);
    }

    public function destroy(Forecast $forecast)
    {
        $this->abortIfHidden($forecast);

        $forecast->delete();

        return response()->json(['status' => 'success']);
    }

    private function baseQuery(Request $request)
    {
        $user    = Auth::user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);

        $query = Forecast::with($this->relations())
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->when($request->input('contact_id'), fn($q, $v) => $q->where('contact_id', $v))
            ->when($request->input('product_id'), fn($q, $v) => $q->where('product_id', $v))
            ->when($request->input('forecast_type_id'), fn($q, $v) => $q->where('forecast_type_id', $v))
            ->when($request->input('contact_status_id'), fn($q, $v) => $q->where('contact_status_id', $v))
            ->when($request->input('contact_type_id'), fn($q, $v) => $q->where('contact_type_id', $v))
            ->when($isAdmin && $request->input('user_id'), fn($q, $v) => $q->where('user_id', $v))
            ->when($request->input('from_date'), fn($q, $v) => $q->where('forecast_date', '>=', $v))
            ->when($request->input('to_date'), fn($q, $v) => $q->where('forecast_date', '<=', $v))
            ->when($request->input('year'), fn($q, $v) => $q->whereBetween('forecast_date', ["{$v}-01-01", "{$v}-12-31"]));

        if ($request->filled('result_id')) {
            $result = $request->input('result_id');
            $result === 'none'
                ? $query->whereNull('result_id')
                : $query->where('result_id', $result);
        }

        if ($search = $request->input('q', $request->input('search'))) {
            $term = "%{$search}%";
            $query->where(function ($q) use ($term) {
                $q->whereHas('contact', fn($q) => $q->where('name', 'like', $term))
                    ->orWhereHas('product', fn($q) => $q->where('name', 'like', $term))
                    ->orWhereHas('forecastType', fn($q) => $q->where('name', 'like', $term))
                    ->orWhereHas('result', fn($q) => $q->where('name', 'like', $term))
                    ->orWhereHas('user', fn($q) => $q->where('name', 'like', $term));
            });
        }

        return $query;
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'contact_id'       => 'required|exists:contacts,id',
            'product_id'       => 'required|exists:forecast_products,id',
            'forecast_type_id' => 'required|exists:forecast_types,id',
            'result_id'        => 'nullable|exists:forecast_results,id',
            'amount'           => 'required|numeric|min:0|max:999999999999.99',
            'forecast_date'    => 'required|date',
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);
    }

    private function relations(): array
    {
        // Constrained selects — only the columns used in format(), reduces data transfer significantly.
        // contact also selects status_id/type_id so the nested status/type fallbacks in format()
        // are eager-loaded (avoids an N+1 lazy-load per row when the denormalized FK is null).
        return [
            'contact:id,name,status_id,type_id',
            'contact.status:id,name',
            'contact.type:id,name',
            'user:id,name',
            'product:id,name',
            'forecastType:id,name',
            'result:id,name',
            'contactStatus:id,name',
            'contactType:id,name',
        ];
    }

    private ?array $_grantedOwnerIds = null;

    private function grantedOwnerIds(): array
    {
        if ($this->_grantedOwnerIds !== null) return $this->_grantedOwnerIds;
        $me = Auth::user();
        if (!$me || $me->hasAnyRole(['admin', 'super-admin'])) {
            return $this->_grantedOwnerIds = [];
        }
        return $this->_grantedOwnerIds = ContactEditGrant::where('user_id', $me->id)
            ->pluck('target_user_id')->map(fn($id) => (int) $id)->toArray();
    }

    private function format(Forecast $forecast): array
    {
        $me      = Auth::user();
        $isAdmin = $me?->hasAnyRole(['admin', 'super-admin']);

        return [
            'id'                    => $forecast->id,
            'amount'                => $forecast->amount,
            'forecast_date'         => $forecast->forecast_date?->format('Y-m-d'),
            'forecast_updatedate'   => $forecast->forecast_updatedate?->format('Y-m-d'),
            'contact_id'            => $forecast->contact_id,
            'contact_name'          => $forecast->contact?->name,
            'contact_status_id'     => $forecast->contact_status_id,
            'contact_status_name'   => $forecast->contactStatus?->name ?? $forecast->contact?->status?->name,
            'contact_type_id'       => $forecast->contact_type_id,
            'contact_type_name'     => $forecast->contactType?->name ?? $forecast->contact?->type?->name,
            'user_id'               => $forecast->user_id,
            'user_name'             => $forecast->user?->name,
            'product_id'            => $forecast->product_id,
            'product_name'          => $forecast->product?->name,
            'forecast_type_id'      => $forecast->forecast_type_id,
            'forecast_type_name'    => $forecast->forecastType?->name,
            'result_id'             => $forecast->result_id,
            'result_name'           => $forecast->result?->name,
            'created_at'            => $forecast->created_at?->toISOString(),
            'can_edit'              => $isAdmin
                || (int) $forecast->user_id === (int) $me?->id
                || \in_array((int) $forecast->user_id, $this->grantedOwnerIds()),
        ];
    }

    private function abortIfHidden(Forecast $forecast): void
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'super-admin']) && $forecast->user_id !== $user->id) {
            abort(404);
        }
    }
}
