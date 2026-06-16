<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignContact;
use App\Models\EmailTemplate;
use App\Services\BrevoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmailCampaignController extends Controller
{
    public function index()
    {
        $campaigns = EmailCampaign::with('contacts')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($c) => $this->formatCampaign($c));

        return response()->json(['data' => $campaigns]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'subject'      => 'nullable|string|max:500',
            'preview_text' => 'nullable|string|max:255',
            'body'         => 'nullable|string',
            'sender_name'  => 'nullable|string|max:255',
            'sender_email' => 'nullable|email|max:255',
            'scheduled_at' => 'nullable|date',
            'pic_ids'      => 'nullable|array',
            'pic_ids.*'    => 'integer',
        ]);

        $campaign = EmailCampaign::create([
            'name'         => $data['name'],
            'subject'      => $data['subject'] ?? '',
            'preview_text' => $data['preview_text'] ?? null,
            'body'         => $data['body'] ?? null,
            'sender_name'  => $data['sender_name'] ?? null,
            'sender_email' => $data['sender_email'] ?? null,
            'scheduled_at' => isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
            'status'       => 'draft',
            'user_id'      => $request->user()->id,
        ]);

        $this->syncContacts($campaign, $data['pic_ids'] ?? []);

        return response()->json(['data' => $this->formatCampaign($campaign->fresh('contacts'))], 201);
    }

    public function update(Request $request, EmailCampaign $campaign)
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'subject'      => 'sometimes|string|max:500',
            'preview_text' => 'nullable|string|max:255',
            'body'         => 'nullable|string',
            'sender_name'  => 'nullable|string|max:255',
            'sender_email' => 'nullable|email|max:255',
            'scheduled_at' => 'nullable|date',
            'pic_ids'      => 'nullable|array',
            'pic_ids.*'    => 'integer',
        ]);

        $campaign->fill(array_filter([
            'name'         => $data['name'] ?? null,
            'subject'      => $data['subject'] ?? null,
            'preview_text' => $data['preview_text'] ?? null,
            'body'         => $data['body'] ?? null,
            'sender_name'  => $data['sender_name'] ?? null,
            'sender_email' => $data['sender_email'] ?? null,
            'scheduled_at' => isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
        ], fn ($v) => $v !== null));

        $campaign->save();

        if (array_key_exists('pic_ids', $data)) {
            $this->syncContacts($campaign, $data['pic_ids'] ?? []);
        }

        return response()->json(['data' => $this->formatCampaign($campaign->fresh('contacts'))]);
    }

    public function destroy(EmailCampaign $campaign)
    {
        $campaign->delete();
        return response()->json(['message' => 'Campaign deleted.']);
    }

    public function schedule(Request $request, EmailCampaign $campaign, BrevoService $brevo)
    {
        $request->validate(['scheduled_at' => 'nullable|date']);

        if (!config('services.brevo.api_key')) {
            return response()->json(['message' => 'Brevo API key not configured. Set BREVO_API_KEY in .env'], 422);
        }

        $contacts = $campaign->contacts->map(fn ($c) => ['email' => $c->email, 'name' => $c->name])->toArray();

        if (empty($contacts)) {
            return response()->json(['message' => 'No contacts in this campaign.'], 422);
        }

        try {
            $listId = $brevo->createList("CRM – {$campaign->name}");
            $brevo->upsertContacts($contacts, $listId);

            $scheduledAt = $request->input('scheduled_at')
                ? Carbon::parse($request->input('scheduled_at'))->toIso8601String()
                : null;

            $brevoId = $brevo->createCampaign([
                'name'         => $campaign->name,
                'subject'      => $campaign->subject,
                'preview_text' => $campaign->preview_text,
                'body'         => $campaign->body,
                'sender_name'  => $campaign->sender_name,
                'sender_email' => $campaign->sender_email,
                'list_id'      => $listId,
                'scheduled_at' => $scheduledAt,
            ]);

            if (!$scheduledAt) {
                $brevo->sendNow($brevoId);
            }

            $campaign->update([
                'brevo_campaign_id' => $brevoId,
                'brevo_list_id'     => $listId,
                'audience_count'    => count($contacts),
                'status'            => $scheduledAt ? 'scheduled' : 'sent',
                'scheduled_at'      => $scheduledAt ? Carbon::parse($scheduledAt) : $campaign->scheduled_at,
                'sent_at'           => $scheduledAt ? null : now(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->formatCampaign($campaign->fresh('contacts'))]);
    }

    public function sendTest(Request $request, EmailCampaign $campaign, BrevoService $brevo)
    {
        $request->validate(['email' => 'required|email']);

        if (!config('services.brevo.api_key')) {
            return response()->json(['message' => 'Brevo API key not configured.'], 422);
        }

        try {
            $listId = $brevo->createList("TEST – {$campaign->name} – " . now()->format('His'));
            $brevo->upsertContacts([['email' => $request->input('email')]], $listId);
            $brevoId = $brevo->createCampaign([
                'name'         => "[TEST] {$campaign->name}",
                'subject'      => "[TEST] {$campaign->subject}",
                'body'         => $campaign->body,
                'sender_name'  => $campaign->sender_name,
                'sender_email' => $campaign->sender_email,
                'list_id'      => $listId,
            ]);
            $brevo->sendNow($brevoId);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => "Test email sent to {$request->input('email')}."]);
    }

    public function syncStats(EmailCampaign $campaign, BrevoService $brevo)
    {
        if (!$campaign->brevo_campaign_id) {
            return response()->json(['message' => 'Campaign not yet sent via Brevo.'], 422);
        }

        try {
            $stats = $brevo->getStats($campaign->brevo_campaign_id);
            $campaign->update([
                'sent_count' => $stats['sent_count'],
                'open_rate'  => $stats['open_rate'],
                'click_rate' => $stats['click_rate'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->formatCampaign($campaign->fresh('contacts'))]);
    }

    // --- Templates ---

    public function templateIndex()
    {
        return response()->json(['data' => EmailTemplate::orderBy('name')->get()]);
    }

    public function templateStore(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'subject'  => 'required|string|max:500',
            'body'     => 'required|string',
        ]);

        return response()->json(['data' => EmailTemplate::create($data)], 201);
    }

    public function templateUpdate(Request $request, EmailTemplate $template)
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'category' => 'nullable|string|max:100',
            'subject'  => 'sometimes|string|max:500',
            'body'     => 'sometimes|string',
        ]);

        $template->update($data);
        return response()->json(['data' => $template]);
    }

    public function templateDestroy(EmailTemplate $template)
    {
        $template->delete();
        return response()->json(['message' => 'Template deleted.']);
    }

    public function settings()
    {
        return response()->json([
            'configured'   => (bool) config('services.brevo.api_key'),
            'sender_name'  => config('services.brevo.sender_name'),
            'sender_email' => config('services.brevo.sender_email'),
            'providers'    => [
                ['id' => 'gmail', 'name' => 'Gmail', 'senders' => config('services.email_campaigns.gmail_senders')],
                ['id' => 'outlook', 'name' => 'Outlook', 'senders' => config('services.email_campaigns.outlook_senders')],
            ],
        ]);
    }

    // --- Helpers ---

    private function syncContacts(EmailCampaign $campaign, array $picIds): void
    {
        $campaign->contacts()->delete();

        if (empty($picIds)) {
            return;
        }

        $incharges = \App\Models\ContactIncharge::whereIn('id', $picIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        $rows = $incharges->map(fn ($pic) => [
            'email_campaign_id'   => $campaign->id,
            'contact_incharge_id' => $pic->id,
            'email'               => $pic->email,
            'name'                => $pic->name,
            'created_at'          => now(),
            'updated_at'          => now(),
        ])->toArray();

        if ($rows) {
            EmailCampaignContact::insert($rows);
        }

        $campaign->update(['audience_count' => count($rows)]);
    }

    private function formatCampaign(EmailCampaign $campaign): array
    {
        return [
            'id'                => $campaign->id,
            'name'              => $campaign->name,
            'subject'           => $campaign->subject,
            'preview_text'      => $campaign->preview_text,
            'body'              => $campaign->body,
            'sender_name'       => $campaign->sender_name,
            'sender_email'      => $campaign->sender_email,
            'status'            => $campaign->status,
            'scheduled_at'      => $campaign->scheduled_at?->toISOString(),
            'sent_at'           => $campaign->sent_at?->toISOString(),
            'audience_count'    => $campaign->audience_count,
            'sent_count'        => $campaign->sent_count,
            'open_rate'         => $campaign->open_rate,
            'click_rate'        => $campaign->click_rate,
            'brevo_campaign_id' => $campaign->brevo_campaign_id,
            'pic_ids'           => $campaign->contacts->pluck('contact_incharge_id')->filter()->values(),
            'created_at'        => $campaign->created_at?->toISOString(),
            'updated_at'        => $campaign->updated_at?->toISOString(),
        ];
    }
}
