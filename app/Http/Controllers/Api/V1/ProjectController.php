<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $perPage   = (int) $request->input('per_page', 100);
        $search    = $request->input('q', '');
        $sortField = $request->input('sort_field', 'project_startdate');
        $sortDir   = in_array($request->input('sort_direction'), ['asc', 'desc'])
            ? $request->input('sort_direction') : 'desc';
        $fromDate  = $request->input('from_date');
        $toDate    = $request->input('to_date');
        $entryDate = $request->input('entry_date');

        $user    = Auth::user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);

        $allowedSorts = ['project_startdate', 'project_enddate', 'project_name', 'updated_at'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'project_startdate';
        }

        $query = Project::select('projects.*', 'contacts.name as contact_name', 'users.name as user_name')
            ->join('contacts', 'projects.contact_id', '=', 'contacts.id')
            ->join('users', 'projects.user_id', '=', 'users.id')
            ->when(!$isAdmin, fn($q) => $q->where('projects.user_id', $user->id))
            ->when($fromDate, fn($q) => $q->where('projects.project_startdate', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->where('projects.project_startdate', '<=', $toDate))
            ->when($entryDate, fn($q) => $q->whereDate('projects.created_at', $entryDate))
            ->when($search, function ($q) use ($search) {
                $term = "%{$search}%";
                $q->where(function ($q) use ($term) {
                    $q->where('projects.project_name', 'like', $term)
                      ->orWhere('projects.project_remark', 'like', $term)
                      ->orWhere('contacts.name', 'like', $term);
                });
            })
            ->orderBy('projects.' . $sortField, $sortDir);

        $projects = $query->paginate($perPage);

        $projects->getCollection()->transform(fn($p) => $this->format($p));

        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_name'      => 'required|string|max:800',
            'project_startdate' => 'required|date',
            'project_enddate'   => 'required|date|after:project_startdate',
            'project_remark'    => 'nullable|string|max:800',
            'contact_id'        => 'required|exists:contacts,id',
        ]);

        $project = Project::create([
            'project_name'      => $validated['project_name'],
            'project_startdate' => $validated['project_startdate'],
            'project_enddate'   => $validated['project_enddate'],
            'project_remark'    => $validated['project_remark'] ?? null,
            'contact_id'        => $validated['contact_id'],
            'user_id'           => Auth::id(),
        ]);

        return response()->json(['status' => 'success', 'data' => $project->load('contact')], 201);
    }

    public function show(string $id)
    {
        $project = Project::with(['contact', 'user'])->findOrFail($id);

        return response()->json(['data' => [
            'id'                => $project->id,
            'project_name'      => $project->project_name,
            'project_startdate' => $project->project_startdate?->format('Y-m-d'),
            'project_enddate'   => $project->project_enddate?->format('Y-m-d'),
            'project_remark'    => $project->project_remark,
            'contact_id'        => $project->contact_id,
            'contact_name'      => $project->contact?->name,
            'user'              => $project->user?->name,
        ]]);
    }

    public function update(Request $request, string $id)
    {
        $project = Project::findOrFail($id);

        $validated = $request->validate([
            'project_name'      => 'required|string|max:800',
            'project_startdate' => 'required|date',
            'project_enddate'   => 'required|date|after:project_startdate',
            'project_remark'    => 'nullable|string|max:800',
            'contact_id'        => 'required|exists:contacts,id',
        ]);

        $project->update([
            'project_name'      => $validated['project_name'],
            'project_startdate' => $validated['project_startdate'],
            'project_enddate'   => $validated['project_enddate'],
            'project_remark'    => $validated['project_remark'] ?? null,
            'contact_id'        => $validated['contact_id'],
            'user_id'           => Auth::id(),
        ]);

        return response()->json(['status' => 'success', 'data' => $project->fresh(['contact'])]);
    }

    public function destroy(string $id)
    {
        Project::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }

    public function remark(string $id)
    {
        $project = Project::findOrFail($id);
        return response()->json(['data' => ['project_remark' => $project->project_remark]]);
    }

    public function export(Request $request)
    {
        $user    = Auth::user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);
        $search   = $request->input('q', '');
        $fromDate = $request->input('from_date');
        $toDate   = $request->input('to_date');

        $query = Project::with(['contact', 'user'])
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->when($fromDate, fn($q) => $q->where('project_startdate', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->where('project_startdate', '<=', $toDate))
            ->when($search, function ($q) use ($search) {
                $term = "%{$search}%";
                $q->where(function ($q) use ($term) {
                    $q->where('project_name', 'like', $term)
                      ->orWhere('project_remark', 'like', $term)
                      ->orWhereHas('contact', fn($q) => $q->where('name', 'like', $term));
                });
            })
            ->orderByDesc('project_startdate');

        $projects = $query->limit(10000)->get();

        $filename = 'Projects_Export_' . now()->format('Y-m-d') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($projects) {
            $out = fopen('php://output', 'w');
            fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['NO', 'START DATE', 'END DATE', 'DURATION (DAYS)', 'COMPANY', 'PROJECT NAME', 'REMARK', 'ENTRY BY', 'ENTRY DATE']);
            $no = 1;
            foreach ($projects as $p) {
                $start    = $p->project_startdate;
                $end      = $p->project_enddate;
                $duration = ($start && $end) ? $start->diffInDays($end) : '-';
                fputcsv($out, [
                    $no++,
                    $start?->format('d-m-Y') ?? '-',
                    $end?->format('d-m-Y') ?? '-',
                    $duration,
                    $p->contact?->name ?? '-',
                    $p->project_name ?? '-',
                    $p->project_remark ?? '-',
                    $p->user?->name ?? '-',
                    $p->created_at?->format('d-m-Y') ?? '-',
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function format(object $p): array
    {
        $start    = $p->project_startdate;
        $end      = $p->project_enddate;
        $duration = ($start && $end)
            ? (is_string($start)
                ? (int) ceil((strtotime($end) - strtotime($start)) / 86400)
                : $start->diffInDays($end))
            : null;

        return [
            'id'                => $p->id,
            'project_startdate' => is_string($start) ? $start : $start?->format('Y-m-d'),
            'project_enddate'   => is_string($end)   ? $end   : $end?->format('Y-m-d'),
            'duration_days'     => $duration,
            'project_name'      => $p->project_name,
            'project_remark'    => $p->project_remark,
            'contact_id'        => $p->contact_id,
            'contact_name'      => $p->contact_name ?? null,
            'user_name'         => $p->user_name ?? null,
            'entry_date'        => is_string($p->updated_at)
                ? date('d-m-Y', strtotime($p->updated_at))
                : $p->updated_at?->format('d-m-Y'),
        ];
    }
}
