<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExhibitionController extends Controller
{
    private function db()
    {
        return DB::connection('exhibitions');
    }

    public function index(Request $request)
    {
        $query = $this->db()->table('companies')
            ->where('source_department', 'Exhibitions (Clean List)')
            ->orderBy('company_name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('additional_info', 'like', "%{$search}%");
            });
        }

        $rows = $query->get();

        $months = [];
        $results = $rows->map(function ($row) use (&$months) {
            $extra = !empty($row->additional_info) ? json_decode($row->additional_info, true) : [];
            $month = $extra['Event Month'] ?? null;
            if ($month && !in_array($month, $months)) $months[] = $month;
            return [
                'id'           => $row->id,
                'company_name' => $row->company_name,
                'event_name'   => $extra['Event Name'] ?? 'N/A',
                'venue'        => $extra['Venue'] ?? 'N/A',
                'event_date'   => $extra['Event Date'] ?? 'N/A',
                'event_month'  => $month ?? 'N/A',
            ];
        });

        if ($monthFilter = $request->input('month')) {
            $results = $results->filter(fn($r) => $r['event_month'] === $monthFilter)->values();
        }

        sort($months);

        return response()->json([
            'data'   => $results,
            'months' => $months,
            'total'  => $results->count(),
        ]);
    }

    public function show(string $id)
    {
        $row = $this->db()->table('companies')
            ->where('id', $id)
            ->where('source_department', 'Exhibitions (Clean List)')
            ->first();

        if (!$row) abort(404);

        $contacts = $this->db()->table('contacts')->where('company_id', $id)->get();
        $extra = !empty($row->additional_info) ? json_decode($row->additional_info, true) : [];

        return response()->json([
            'data' => array_merge((array)$row, ['extra' => $extra, 'contacts' => $contacts]),
        ]);
    }
}
