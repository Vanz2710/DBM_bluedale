<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);

        $perPage   = min((int) $request->input('per_page', 50), 200);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $logs = $this->filteredQuery($request)->limit(10000)->get();

        $filename = 'Audit_Log_Export_' . now()->format('Y-m-d') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Date', 'Time', 'Actor', 'Action', 'Entity Type', 'Entity Name', 'IP Address']);
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->created_at?->format('d-m-Y') ?? '-',
                    $log->created_at?->format('H:i') ?? '-',
                    $log->actor?->name ?? 'System',
                    $log->action,
                    $log->entity_type,
                    $log->entity_name ?? $log->entity_id ?? '-',
                    $log->ip_address ?? '-',
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = AdminAuditLog::with('actor:id,name,username')
            ->orderByDesc('created_at');

        if ($days = (int) $request->input('days', 30)) {
            $query->where('created_at', '>=', now()->subDays($days));
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($entityType = $request->input('entity_type')) {
            $query->where('entity_type', $entityType);
        }

        if ($q = $request->input('q')) {
            $term = "%{$q}%";
            $query->where(function ($sq) use ($term) {
                $sq->whereHas('actor', fn ($a) => $a->where('name', 'like', $term))
                   ->orWhere('entity_name', 'like', $term)
                   ->orWhere('entity_type', 'like', $term)
                   ->orWhere('action', 'like', $term)
                   ->orWhere('ip_address', 'like', $term);
            });
        }

        return $query;
    }
}
