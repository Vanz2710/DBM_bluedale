<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaReminder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocialMediaReminderController extends Controller
{
    private const CONTENT_STATUSES = ['pending', 'wfa', 'approved'];
    private const POSTING_STATUSES = ['pending', 'wfa', 'approved', 'scheduling', 'posted'];
    private const REPORT_STATUSES = ['pending', 'wfa', 'done', 'completed'];

    public function index(Request $request)
    {
        $query = SocialMediaReminder::with('contact:id,name')
            ->orderByRaw("STR_TO_DATE(CONCAT('01 ', month), '%d %M %Y') DESC")
            ->orderBy('company_name')
            ->orderBy('package');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('package', 'like', "%{$search}%");
            });
        }

        if ($month = $request->input('month')) {
            $query->where('month', $month);
        }

        $perPage = min((int) $request->input('per_page', 10), 100);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $reminder = SocialMediaReminder::create($validated);

        return response()->json(['status' => 'success', 'data' => $reminder], 201);
    }

    public function update(Request $request, string $id)
    {
        $socialMediaReminder = SocialMediaReminder::findOrFail($id);
        $validated = $request->validate($this->rules(partial: true));

        if (($validated['posting_status'] ?? $socialMediaReminder->posting_status) !== 'scheduling') {
            $validated['posting_staff_initials'] = null;
        }

        $socialMediaReminder->update($validated);

        return response()->json(['status' => 'success', 'data' => $socialMediaReminder->fresh()]);
    }

    public function destroy(string $id)
    {
        $socialMediaReminder = SocialMediaReminder::findOrFail($id);
        $socialMediaReminder->delete();

        return response()->json(['status' => 'success']);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'company_name' => [$required, 'string', 'max:255'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'package' => [$required, 'string', 'max:255'],
            'month' => [$required, 'string', 'max:40'],
            'content_calendar_status' => ['sometimes', Rule::in(self::CONTENT_STATUSES)],
            'artwork_editing_status' => ['sometimes', Rule::in(self::CONTENT_STATUSES)],
            'posting_status' => ['sometimes', Rule::in(self::POSTING_STATUSES)],
            'posting_staff_initials' => ['nullable', 'string', 'max:10'],
            'report_status' => ['sometimes', Rule::in(self::REPORT_STATUSES)],
        ];
    }
}
