<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;

class AdminAuditLogController extends Controller
{
    public function index()
    {
        $logs = AdminAuditLog::with('actor:id,name,username')
            ->orderByDesc('created_at')
            ->limit(300)
            ->get();

        return response()->json(['data' => $logs]);
    }
}
