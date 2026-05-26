<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TravelController extends Controller
{
    private function db()
    {
        return DB::connection('exhibitions');
    }

    public function index(Request $request)
    {
        $query = $this->db()->table('companies as c')
            ->leftJoin('contacts as ct', 'c.id', '=', 'ct.company_id')
            ->where('c.source_department', 'Travel Guide')
            ->select('c.*', DB::raw('MAX(ct.phone) as phone'), DB::raw('MAX(ct.email) as email'))
            ->groupBy('c.id')
            ->orderBy('c.company_name')
            ->limit(150);

        if ($state = $request->input('state'))       $query->where('c.state', $state);
        if ($category = $request->input('category')) $query->where('c.category', $category);
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('c.company_name', 'like', "%{$search}%")
                  ->orWhere('c.address', 'like', "%{$search}%")
                  ->orWhere('c.additional_info', 'like', "%{$search}%");
            });
        }

        $rows = $query->get()->map(function ($row) {
            $extra = !empty($row->additional_info) ? json_decode($row->additional_info, true) : [];
            return array_merge((array)$row, ['extra' => $extra]);
        });

        $states = $this->db()->table('companies')
            ->where('source_department', 'Travel Guide')
            ->whereNotNull('state')->where('state', '!=', '')
            ->distinct()->orderBy('state')->pluck('state');

        $categories = $this->db()->table('companies')
            ->where('source_department', 'Travel Guide')
            ->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category');

        return response()->json([
            'data'       => $rows,
            'states'     => $states,
            'categories' => $categories,
            'total'      => $rows->count(),
        ]);
    }

    public function show(string $id)
    {
        $row = $this->db()->table('companies')
            ->where('id', $id)->where('source_department', 'Travel Guide')->first();

        if (!$row) abort(404);

        $contacts = $this->db()->table('contacts')->where('company_id', $id)->get();
        $extra = !empty($row->additional_info) ? json_decode($row->additional_info, true) : [];

        return response()->json([
            'data' => array_merge((array)$row, ['extra' => $extra, 'contacts' => $contacts]),
        ]);
    }
}
