<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProdJob;
use App\Models\ProdApplication;
use App\Models\ProdArtworkPayment;
use App\Models\ProdInstallation;
use App\Models\ProdComplaint;
use App\Models\ProdDismantle;
use App\Models\ProdComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProductionSupportController extends Controller
{
    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function dashboard(): \Illuminate\Http\JsonResponse
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();

        $total        = ProdJob::whereNotIn('overall_status', ['cancelled'])->count();
        $pendingApps  = ProdApplication::where('status', 'pending')->count();
        $pendingPay   = ProdArtworkPayment::whereIn('payment_status', ['pending', 'partial'])->count();
        $awaitingInst = ProdJob::where('current_stage', 'installation')->where('overall_status', 'active')->count();
        $openComplaints = ProdComplaint::where('resolution_status', '!=', 'resolved')->count();
        $completedMonth = ProdJob::where('current_stage', 'completed')
            ->whereBetween('updated_at', [$monthStart, $now])
            ->count();

        $recentJobs = ProdJob::with('creator')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn($j) => $this->formatJob($j));

        $byStage = ProdJob::whereNotIn('overall_status', ['cancelled'])
            ->selectRaw('current_stage, count(*) as total')
            ->groupBy('current_stage')
            ->pluck('total', 'current_stage');

        return response()->json([
            'stats' => [
                'total_active'       => $total,
                'pending_apps'       => $pendingApps,
                'pending_payments'   => $pendingPay,
                'awaiting_install'   => $awaitingInst,
                'open_complaints'    => $openComplaints,
                'completed_month'    => $completedMonth,
            ],
            'byStage'    => $byStage,
            'recentJobs' => $recentJobs,
        ]);
    }

    // ─── Job List ─────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = ProdJob::with('creator');

        if ($search = $request->search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('client_name', 'like', "%$search%")
                    ->orWhere('job_number', 'like', "%$search%")
                    ->orWhere('location', 'like', "%$search%")
                    ->orWhere('title', 'like', "%$search%");
            });
        }
        if ($stage = $request->stage) {
            $q->where('current_stage', $stage);
        }
        if ($type = $request->product_type) {
            $q->where('product_type', $type);
        }
        if ($status = $request->overall_status) {
            $q->where('overall_status', $status);
        }
        if ($from = $request->date_from) {
            $q->where('request_date', '>=', $from);
        }
        if ($to = $request->date_to) {
            $q->where('request_date', '<=', $to);
        }

        $sort = $request->sort ?? 'created_at';
        $dir  = $request->dir  ?? 'desc';
        $allowedSorts = ['request_date', 'client_name', 'current_stage', 'due_date', 'created_at'];
        if (!in_array($sort, $allowedSorts)) $sort = 'created_at';

        $jobs = $q->orderBy($sort, $dir === 'asc' ? 'asc' : 'desc')->get();

        return response()->json($jobs->map(fn($j) => $this->formatJob($j)));
    }

    // ─── Create Job ───────────────────────────────────────────────────────────

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'client_name'    => 'required|string|max:255',
            'title'          => 'nullable|string|max:255',
            'product_type'   => 'required|in:Billboard,Bunting,Banner,Signboard,Lamp Post,Others',
            'location'       => 'nullable|string|max:500',
            'request_details'=> 'nullable|string',
            'request_date'   => 'required|date',
            'pic'            => 'nullable|string|max:255',
            'current_stage'  => 'nullable|in:new_request,application,artwork_approval,payment_pending,printing,installation,completed,cancelled',
            'overall_status' => 'nullable|in:active,on_hold,completed,cancelled',
            'due_date'       => 'nullable|date',
            'installation_date' => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        $data['created_by']   = auth()->id();
        $data['job_number']   = $this->generateJobNumber();
        $data['current_stage']  = $data['current_stage'] ?? 'new_request';
        $data['overall_status'] = $data['overall_status'] ?? 'active';

        $job = ProdJob::create($data);
        $job->load('creator');

        return response()->json($this->formatJob($job), 201);
    }

    // ─── Show Job ─────────────────────────────────────────────────────────────

    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        $job = ProdJob::with([
            'creator',
            'application',
            'artworkPayment',
            'installation',
            'dismantle',
            'complaints.assignedUser',
            'comments.user',
        ])->findOrFail($id);

        return response()->json($this->formatJob($job, true));
    }

    // ─── Update Job ───────────────────────────────────────────────────────────

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $job = ProdJob::findOrFail($id);

        $data = $request->validate([
            'client_name'    => 'sometimes|required|string|max:255',
            'title'          => 'nullable|string|max:255',
            'product_type'   => 'sometimes|required|in:Billboard,Bunting,Banner,Signboard,Lamp Post,Others',
            'location'       => 'nullable|string|max:500',
            'request_details'=> 'nullable|string',
            'request_date'   => 'sometimes|required|date',
            'pic'            => 'nullable|string|max:255',
            'current_stage'  => 'nullable|in:new_request,application,artwork_approval,payment_pending,printing,installation,completed,cancelled',
            'overall_status' => 'nullable|in:active,on_hold,completed,cancelled',
            'due_date'       => 'nullable|date',
            'installation_date' => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        $job->update($data);
        $job->load('creator');

        return response()->json($this->formatJob($job));
    }

    // ─── Delete Job ───────────────────────────────────────────────────────────

    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        ProdJob::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ─── Update Stage (drag-drop) ─────────────────────────────────────────────

    public function updateStage(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'stage' => 'required|in:new_request,application,artwork_approval,payment_pending,printing,installation,completed,cancelled',
        ]);

        $job = ProdJob::findOrFail($id);
        $job->current_stage = $data['stage'];
        if ($data['stage'] === 'completed') {
            $job->overall_status = 'completed';
        } elseif ($data['stage'] === 'cancelled') {
            $job->overall_status = 'cancelled';
        } else {
            if ($job->overall_status !== 'on_hold') $job->overall_status = 'active';
        }
        $job->save();

        return response()->json($this->formatJob($job));
    }

    // ─── Application ─────────────────────────────────────────────────────────

    public function updateApplication(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $job = ProdJob::findOrFail($id);

        $data = $request->validate([
            'submission_date'  => 'nullable|date',
            'council'          => 'nullable|in:DBKL,MBPJ,MBSJ,MBSA,JKR,Others',
            'council_other'    => 'nullable|string|max:255',
            'status'           => 'nullable|in:pending,submitted,approved,rejected',
            'reference_number' => 'nullable|string|max:255',
            'remarks'          => 'nullable|string',
        ]);

        $app = ProdApplication::updateOrCreate(['job_id' => $id], $data);

        // Auto-advance stage when approved
        if (($data['status'] ?? null) === 'approved' && $job->current_stage === 'application') {
            $job->current_stage = 'artwork_approval';
            $job->save();
        }

        return response()->json($app);
    }

    // ─── Artwork & Payment ────────────────────────────────────────────────────

    public function updateArtworkPayment(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $job = ProdJob::findOrFail($id);

        $data = $request->validate([
            'artwork_version'  => 'nullable|string|max:100',
            'artwork_status'   => 'nullable|in:pending,in_review,revision,approved',
            'payment_amount'   => 'nullable|numeric|min:0',
            'payment_status'   => 'nullable|in:pending,partial,paid',
            'invoice_number'   => 'nullable|string|max:100',
            'payment_due_date' => 'nullable|date',
            'artwork_notes'    => 'nullable|string',
        ]);

        $ap = ProdArtworkPayment::updateOrCreate(['job_id' => $id], $data);

        // Auto-advance: artwork approved → payment_pending
        if (($data['artwork_status'] ?? null) === 'approved' && $job->current_stage === 'artwork_approval') {
            $job->current_stage = 'payment_pending';
            $job->save();
        }
        // Auto-advance: payment paid → printing
        if (($data['payment_status'] ?? null) === 'paid' && $job->current_stage === 'payment_pending') {
            $job->current_stage = 'printing';
            $job->save();
        }

        return response()->json($ap);
    }

    // ─── Installation ─────────────────────────────────────────────────────────

    public function updateInstallation(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $job = ProdJob::findOrFail($id);

        $data = $request->validate([
            'installation_date'   => 'nullable|date',
            'quantity'            => 'nullable|integer|min:1',
            'printing_status'     => 'nullable|in:pending,in_production,ready',
            'installation_status' => 'nullable|in:scheduled,ongoing,completed',
            'installer_pic'       => 'nullable|string|max:255',
            'installation_notes'  => 'nullable|string',
        ]);

        $inst = ProdInstallation::updateOrCreate(['job_id' => $id], $data);

        // Auto-advance: printing ready → installation (if in printing stage)
        if (($data['printing_status'] ?? null) === 'ready' && $job->current_stage === 'printing') {
            $job->current_stage = 'installation';
            $job->save();
        }
        // Auto-advance: installation completed → completed
        if (($data['installation_status'] ?? null) === 'completed' && $job->current_stage === 'installation') {
            $job->current_stage = 'completed';
            $job->overall_status = 'completed';
            $job->save();
        }

        return response()->json($inst);
    }

    // ─── Complaints ───────────────────────────────────────────────────────────

    public function addComplaint(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        ProdJob::findOrFail($id);

        $data = $request->validate([
            'complaint_date'    => 'required|date',
            'site_location'     => 'nullable|string|max:500',
            'complaint_type'    => 'required|in:lighting,structural,missing_panel,printing_defect,installation_defect,others',
            'description'       => 'nullable|string',
            'resolution_status' => 'nullable|in:open,in_progress,resolved',
            'assigned_user_id'  => 'nullable|exists:users,id',
        ]);

        $complaint = ProdComplaint::create(['job_id' => $id] + $data);
        $complaint->load('assignedUser');

        return response()->json($complaint, 201);
    }

    public function updateComplaint(Request $request, int $complaintId): \Illuminate\Http\JsonResponse
    {
        $complaint = ProdComplaint::findOrFail($complaintId);

        $data = $request->validate([
            'complaint_date'    => 'nullable|date',
            'site_location'     => 'nullable|string|max:500',
            'complaint_type'    => 'nullable|in:lighting,structural,missing_panel,printing_defect,installation_defect,others',
            'description'       => 'nullable|string',
            'resolution_status' => 'nullable|in:open,in_progress,resolved',
            'assigned_user_id'  => 'nullable|exists:users,id',
        ]);

        $complaint->update($data);
        $complaint->load('assignedUser');

        return response()->json($complaint);
    }

    // ─── Dismantle ────────────────────────────────────────────────────────────

    public function updateDismantle(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        ProdJob::findOrFail($id);

        $data = $request->validate([
            'scheduled_date'  => 'nullable|date',
            'completion_date' => 'nullable|date',
            'pic'             => 'nullable|string|max:255',
            'status'          => 'nullable|in:pending,scheduled,completed',
            'notes'           => 'nullable|string',
        ]);

        $dismantle = ProdDismantle::updateOrCreate(['job_id' => $id], $data);

        return response()->json($dismantle);
    }

    // ─── Comments ─────────────────────────────────────────────────────────────

    public function addComment(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        ProdJob::findOrFail($id);

        $data = $request->validate(['comment' => 'required|string|max:2000']);

        $comment = ProdComment::create([
            'job_id'  => $id,
            'user_id' => auth()->id(),
            'comment' => $data['comment'],
        ]);
        $comment->load('user');

        return response()->json([
            'id'         => $comment->id,
            'comment'    => $comment->comment,
            'user'       => ['id' => $comment->user->id, 'name' => $comment->user->name],
            'created_at' => $comment->created_at->toISOString(),
        ], 201);
    }

    public function deleteComment(int $commentId): \Illuminate\Http\JsonResponse
    {
        $comment = ProdComment::findOrFail($commentId);
        if ($comment->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $comment->delete();
        return response()->json(['success' => true]);
    }

    // ─── Users ────────────────────────────────────────────────────────────────

    public function users(): \Illuminate\Http\JsonResponse
    {
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        return response()->json($users);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateJobNumber(): string
    {
        $year   = date('Y');
        $prefix = "PS-{$year}-";
        $last   = ProdJob::where('job_number', 'like', $prefix . '%')
            ->orderByDesc('job_number')
            ->value('job_number');
        $seq = $last ? (intval(substr($last, -3)) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    private function formatJob(ProdJob $job, bool $withDetails = false): array
    {
        $base = [
            'id'               => $job->id,
            'job_number'       => $job->job_number,
            'client_name'      => $job->client_name,
            'title'            => $job->title,
            'product_type'     => $job->product_type,
            'location'         => $job->location,
            'request_details'  => $job->request_details,
            'request_date'     => $job->request_date?->toDateString(),
            'pic'              => $job->pic,
            'current_stage'    => $job->current_stage,
            'overall_status'   => $job->overall_status,
            'due_date'         => $job->due_date?->toDateString(),
            'installation_date'=> $job->installation_date?->toDateString(),
            'notes'            => $job->notes,
            'created_by'       => $job->creator ? ['id' => $job->creator->id, 'name' => $job->creator->name] : null,
            'created_at'       => $job->created_at?->toISOString(),
            'updated_at'       => $job->updated_at?->toISOString(),
            'is_overdue'       => $job->due_date && $job->due_date->isPast()
                && !in_array($job->current_stage, ['completed', 'cancelled']),
        ];

        if ($withDetails) {
            $ap = $job->artworkPayment;
            $inst = $job->installation;
            $dis = $job->dismantle;

            $base['application']     = $job->application;
            $base['artwork_payment'] = $ap ? [
                'id'               => $ap->id,
                'artwork_version'  => $ap->artwork_version,
                'artwork_status'   => $ap->artwork_status,
                'payment_amount'   => $ap->payment_amount,
                'payment_status'   => $ap->payment_status,
                'invoice_number'   => $ap->invoice_number,
                'payment_due_date' => $ap->payment_due_date?->toDateString(),
                'artwork_notes'    => $ap->artwork_notes,
            ] : null;
            $base['installation']    = $inst ? [
                'id'                  => $inst->id,
                'installation_date'   => $inst->installation_date?->toDateString(),
                'quantity'            => $inst->quantity,
                'printing_status'     => $inst->printing_status,
                'installation_status' => $inst->installation_status,
                'installer_pic'       => $inst->installer_pic,
                'installation_notes'  => $inst->installation_notes,
            ] : null;
            $base['dismantle']       = $dis ? [
                'id'              => $dis->id,
                'scheduled_date'  => $dis->scheduled_date?->toDateString(),
                'completion_date' => $dis->completion_date?->toDateString(),
                'pic'             => $dis->pic,
                'status'          => $dis->status,
                'notes'           => $dis->notes,
            ] : null;
            $base['complaints']      = $job->complaints->map(fn($c) => [
                'id'                => $c->id,
                'complaint_date'    => $c->complaint_date->toDateString(),
                'site_location'     => $c->site_location,
                'complaint_type'    => $c->complaint_type,
                'description'       => $c->description,
                'resolution_status' => $c->resolution_status,
                'assigned_user'     => $c->assignedUser ? ['id' => $c->assignedUser->id, 'name' => $c->assignedUser->name] : null,
            ]);
            $base['comments']        = $job->comments->map(fn($c) => [
                'id'         => $c->id,
                'comment'    => $c->comment,
                'user'       => ['id' => $c->user->id, 'name' => $c->user->name],
                'created_at' => $c->created_at->toISOString(),
            ]);
        }

        return $base;
    }
}
