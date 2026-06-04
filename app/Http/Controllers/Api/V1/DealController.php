<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DealController extends Controller
{
    const STAGES = ['New Lead', 'Contacted', 'Quotation Sent', 'Negotiation', 'Won', 'Lost'];

    public function index(Request $request)
    {
        $perPage   = min((int) $request->input('per_page', 100), 500);
        $search    = $request->input('q', '');
        $sortField = $request->input('sort_field', 'created_at');
        $sortDir   = in_array($request->input('sort_direction'), ['asc', 'desc'])
            ? $request->input('sort_direction') : 'desc';
        $stage     = $request->input('stage');
        $status    = $request->input('status');
        $userId    = $request->input('user_id');
        $fromDate  = $request->input('from_date');
        $toDate    = $request->input('to_date');

        $user    = Auth::user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);

        $allowedSorts = ['title', 'stage', 'value', 'probability', 'expected_close_date', 'created_at'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'created_at';
        }

        $query = Deal::select('deals.*', 'contacts.name as contact_name', 'users.name as user_name')
            ->join('contacts', 'deals.contact_id', '=', 'contacts.id')
            ->join('users', 'deals.user_id', '=', 'users.id')
            ->when(!$isAdmin, fn($q) => $q->where('deals.user_id', $user->id))
            ->when($isAdmin && $userId, fn($q) => $q->where('deals.user_id', $userId))
            ->when($stage, fn($q) => $q->where('deals.stage', $stage))
            ->when($status, fn($q) => $q->where('deals.status', $status))
            ->when($fromDate, fn($q) => $q->where('deals.expected_close_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->where('deals.expected_close_date', '<=', $toDate))
            ->when($search, function ($q) use ($search) {
                $term = "%{$search}%";
                $q->where(function ($q) use ($term) {
                    $q->where('deals.title', 'like', $term)
                      ->orWhere('contacts.name', 'like', $term)
                      ->orWhere('deals.notes', 'like', $term);
                });
            })
            ->orderBy('deals.' . $sortField, $sortDir);

        $deals = $query->paginate($perPage);
        $deals->getCollection()->transform(fn($d) => $this->format($d));

        return response()->json($deals);
    }

    public function summary(Request $request)
    {
        $user    = Auth::user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);
        $userId  = $request->input('user_id');
        $stage   = $request->input('stage');
        $fromDate = $request->input('from_date');
        $toDate   = $request->input('to_date');
        $search   = $request->input('q', '');

        $base = Deal::query()
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->when($isAdmin && $userId, fn($q) => $q->where('user_id', $userId))
            ->when($stage, fn($q) => $q->where('stage', $stage))
            ->when($fromDate, fn($q) => $q->where('expected_close_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->where('expected_close_date', '<=', $toDate))
            ->when($search, function ($q) use ($search) {
                $term = "%{$search}%";
                $q->where(function ($q) use ($term) {
                    $q->where('title', 'like', $term)
                      ->orWhere('notes', 'like', $term)
                      ->orWhereHas('contact', fn($q) => $q->where('name', 'like', $term));
                });
            });

        $openBase = (clone $base)->where('status', 'open');

        return response()->json(['data' => [
            'open_count'         => (clone $openBase)->count(),
            'open_value'         => (clone $openBase)->sum('value'),
            'won_count'          => (clone $base)->where('status', 'won')->count(),
            'won_value'          => (clone $base)->where('status', 'won')->sum('value'),
            'lost_value'         => (clone $base)->where('status', 'lost')->sum('value'),
            'weighted_forecast'  => (clone $openBase)->whereNotNull('value')->whereNotNull('probability')
                                        ->sum(DB::raw('value * probability / 100')),
            'by_stage'           => (clone $openBase)
                                        ->select('stage', DB::raw('count(*) as count'), DB::raw('sum(value) as total_value'))
                                        ->groupBy('stage')
                                        ->orderByRaw("FIELD(stage, 'New Lead','Contacted','Quotation Sent','Negotiation','Won','Lost')")
                                        ->get(),
        ]]);
    }

    public function store(Request $request)
    {
        $user    = Auth::user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);

        $validated = $request->validate([
            'title'               => 'required|string|max:500',
            'stage'               => 'required|in:New Lead,Contacted,Quotation Sent,Negotiation,Won,Lost',
            'contact_id'          => 'required|exists:contacts,id',
            'value'               => 'nullable|numeric|min:0|max:999999999',
            'probability'         => 'nullable|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'status'              => 'required|in:open,won,lost',
            'lost_reason'         => 'nullable|string|max:500',
            'notes'               => 'nullable|string|max:2000',
            'assigned_user_id'    => 'nullable|exists:users,id',
        ]);

        $deal = Deal::create([
            'title'               => $validated['title'],
            'stage'               => $validated['stage'],
            'contact_id'          => $validated['contact_id'],
            'value'               => $validated['value'] ?? null,
            'probability'         => $validated['probability'] ?? null,
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'status'              => $validated['status'],
            'lost_reason'         => $validated['lost_reason'] ?? null,
            'notes'               => $validated['notes'] ?? null,
            'user_id'             => ($isAdmin && !empty($validated['assigned_user_id']))
                ? $validated['assigned_user_id']
                : Auth::id(),
        ]);

        return response()->json(['status' => 'success', 'data' => $deal->load('contact', 'user')], 201);
    }

    public function show(string $id)
    {
        $deal = Deal::with(['contact', 'user'])->findOrFail($id);

        return response()->json(['data' => [
            'id'                  => $deal->id,
            'title'               => $deal->title,
            'stage'               => $deal->stage,
            'value'               => $deal->value,
            'probability'         => $deal->probability,
            'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
            'status'              => $deal->status,
            'lost_reason'         => $deal->lost_reason,
            'notes'               => $deal->notes,
            'contact_id'          => $deal->contact_id,
            'contact_name'        => $deal->contact?->name,
            'user_id'             => $deal->user_id,
            'user_name'           => $deal->user?->name,
        ]]);
    }

    public function update(Request $request, string $id)
    {
        $deal    = Deal::findOrFail($id);
        $user    = Auth::user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);

        $validated = $request->validate([
            'title'               => 'required|string|max:500',
            'stage'               => 'required|in:New Lead,Contacted,Quotation Sent,Negotiation,Won,Lost',
            'contact_id'          => 'required|exists:contacts,id',
            'value'               => 'nullable|numeric|min:0|max:999999999',
            'probability'         => 'nullable|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'status'              => 'required|in:open,won,lost',
            'lost_reason'         => 'nullable|string|max:500',
            'notes'               => 'nullable|string|max:2000',
            'assigned_user_id'    => 'nullable|exists:users,id',
        ]);

        $deal->update([
            'title'               => $validated['title'],
            'stage'               => $validated['stage'],
            'contact_id'          => $validated['contact_id'],
            'value'               => $validated['value'] ?? null,
            'probability'         => $validated['probability'] ?? null,
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'status'              => $validated['status'],
            'lost_reason'         => $validated['lost_reason'] ?? null,
            'notes'               => $validated['notes'] ?? null,
            'user_id'             => ($isAdmin && !empty($validated['assigned_user_id']))
                ? $validated['assigned_user_id']
                : $deal->user_id,
        ]);

        return response()->json(['status' => 'success', 'data' => $deal->fresh(['contact', 'user'])]);
    }

    public function destroy(string $id)
    {
        Deal::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }

    public function export(Request $request)
    {
        $user    = Auth::user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);
        $search   = $request->input('q', '');
        $stage    = $request->input('stage');
        $status   = $request->input('status');
        $userId   = $request->input('user_id');
        $fromDate = $request->input('from_date');
        $toDate   = $request->input('to_date');

        $query = Deal::with(['contact', 'user'])
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->when($isAdmin && $userId, fn($q) => $q->where('user_id', $userId))
            ->when($stage, fn($q) => $q->where('stage', $stage))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($fromDate, fn($q) => $q->where('expected_close_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->where('expected_close_date', '<=', $toDate))
            ->when($search, function ($q) use ($search) {
                $term = "%{$search}%";
                $q->where(function ($q) use ($term) {
                    $q->where('title', 'like', $term)
                      ->orWhere('notes', 'like', $term)
                      ->orWhereHas('contact', fn($q) => $q->where('name', 'like', $term));
                });
            })
            ->orderByDesc('created_at');

        $deals    = $query->get();
        $filename = 'Deals_Export_' . now()->format('Y-m-d') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($deals) {
            $out = fopen('php://output', 'w');
            fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['NO', 'TITLE', 'STAGE', 'STATUS', 'COMPANY', 'VALUE', 'PROBABILITY (%)', 'EXPECTED CLOSE', 'ASSIGNED TO', 'LOST REASON', 'NOTES', 'ENTRY DATE']);
            $no = 1;
            foreach ($deals as $d) {
                fputcsv($out, [
                    $no++,
                    $d->title ?? '-',
                    $d->stage ?? '-',
                    $d->status ?? '-',
                    $d->contact?->name ?? '-',
                    $d->value ?? '-',
                    $d->probability ?? '-',
                    $d->expected_close_date?->format('d-m-Y') ?? '-',
                    $d->user?->name ?? '-',
                    $d->lost_reason ?? '-',
                    $d->notes ?? '-',
                    $d->created_at?->format('d-m-Y') ?? '-',
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function format(object $d): array
    {
        $closeDate = $d->expected_close_date;

        return [
            'id'                  => $d->id,
            'title'               => $d->title,
            'stage'               => $d->stage,
            'value'               => $d->value,
            'probability'         => $d->probability,
            'expected_close_date' => is_string($closeDate) ? $closeDate : $closeDate?->format('Y-m-d'),
            'status'              => $d->status,
            'lost_reason'         => $d->lost_reason,
            'notes'               => $d->notes,
            'contact_id'          => $d->contact_id,
            'contact_name'        => $d->contact_name ?? null,
            'user_id'             => $d->user_id,
            'user_name'           => $d->user_name ?? null,
            'entry_date'          => is_string($d->created_at)
                ? date('d-m-Y', strtotime($d->created_at))
                : $d->created_at?->format('d-m-Y'),
        ];
    }
}
