<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Services\AudienceResolver;
use App\Services\CampaignSender;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EmailCampaignController extends Controller
{
    public function __construct(private AudienceResolver $resolver) {}

    public function index()
    {
        $campaigns = EmailCampaign::with('audienceGroup:id,name')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($c) => $this->present($c));

        return response()->json(['data' => $campaigns]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCampaign($request);

        $campaign = EmailCampaign::create([
            'name'              => $data['name'],
            'subject'           => $data['subject'] ?? '',
            'preview_text'      => $data['preview_text'] ?? null,
            'body'              => $data['body'] ?? null,
            'sender_name'       => $data['sender_name'] ?? null,
            'sender_email'      => $data['sender_email'] ?? null,
            'audience_group_id' => $data['audience_group_id'] ?? null,
            'scheduled_at'      => isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
            'status'            => 'draft',
            'user_id'           => $request->user()->id,
            'audience_count'    => $this->audienceCount($data['audience_group_id'] ?? null),
        ]);

        return response()->json(['data' => $this->present($campaign->fresh('audienceGroup'))], 201);
    }

    public function update(Request $request, EmailCampaign $campaign)
    {
        if ($campaign->status === 'sent') {
            return response()->json(['message' => 'A sent campaign cannot be edited.'], 422);
        }

        $data = $this->validateCampaign($request, true);

        $campaign->fill(array_filter([
            'name'              => $data['name'] ?? null,
            'subject'           => $data['subject'] ?? null,
            'preview_text'      => $data['preview_text'] ?? null,
            'body'              => $data['body'] ?? null,
            'sender_name'       => $data['sender_name'] ?? null,
            'sender_email'      => $data['sender_email'] ?? null,
            'scheduled_at'      => isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
        ], fn($v) => $v !== null));

        if (array_key_exists('audience_group_id', $data)) {
            $campaign->audience_group_id = $data['audience_group_id'];
            $campaign->audience_count = $this->audienceCount($data['audience_group_id']);
        }

        $campaign->save();

        return response()->json(['data' => $this->present($campaign->fresh('audienceGroup'))]);
    }

    public function destroy(EmailCampaign $campaign)
    {
        if ($campaign->status === 'sent') {
            return response()->json(['message' => 'A sent campaign cannot be deleted — it contains delivery history. Archive it instead if needed.'], 422);
        }

        $campaign->delete();
        return response()->json(['message' => 'Campaign deleted.']);
    }

    public function duplicate(EmailCampaign $campaign)
    {
        $copy = $campaign->replicate(['sent_at', 'sent_count', 'delivered_count', 'opened_count', 'clicked_count', 'bounced_count', 'unsubscribed_count', 'open_rate', 'click_rate']);
        $copy->name = $campaign->name . ' (copy)';
        $copy->status = 'draft';
        $copy->scheduled_at = null;
        $copy->save();

        return response()->json(['data' => $this->present($copy->fresh('audienceGroup'))], 201);
    }

    /**
     * Build recipients (if needed) and send the campaign immediately over SMTP.
     */
    public function send(EmailCampaign $campaign, CampaignSender $sender)
    {
        if ($campaign->status === 'sent') {
            return response()->json(['message' => 'This campaign has already been sent.'], 422);
        }

        try {
            $built = $this->buildRecipients($campaign);
            if ($built === 0 && $campaign->recipients()->count() === 0) {
                return response()->json(['message' => 'No subscribed contacts in this audience.'], 422);
            }

            $result = $sender->send($campaign);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data'    => $this->present($campaign->fresh('audienceGroup')),
            'message' => "Sent {$result['sent']} email(s)" . ($result['failed'] ? ", {$result['failed']} failed." : '.'),
        ]);
    }

    /**
     * Build recipients and mark the campaign scheduled; the scheduler command
     * (email:dispatch-scheduled) sends it when the time arrives.
     */
    public function schedule(Request $request, EmailCampaign $campaign)
    {
        if ($campaign->status === 'sent') {
            return response()->json(['message' => 'A sent campaign cannot be rescheduled.'], 422);
        }

        $data = $request->validate(['scheduled_at' => 'required|date|after:now']);

        try {
            $this->buildRecipients($campaign);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($campaign->recipients()->count() === 0) {
            return response()->json(['message' => 'No subscribed contacts in this audience.'], 422);
        }

        $campaign->update([
            'status'       => 'scheduled',
            'scheduled_at' => Carbon::parse($data['scheduled_at']),
        ]);

        return response()->json(['data' => $this->present($campaign->fresh('audienceGroup'))]);
    }

    public function sendTest(Request $request, EmailCampaign $campaign, CampaignSender $sender)
    {
        $request->validate(['email' => 'required|email']);

        try {
            $sender->sendTest($campaign, $request->input('email'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Send failed: ' . $e->getMessage()], 422);
        }

        return response()->json(['message' => "Test email sent to {$request->input('email')}."]);
    }

    /**
     * Per-recipient delivery + tracking status for a sent campaign.
     */
    public function recipients(EmailCampaign $campaign)
    {
        $rows = $campaign->recipients()
            ->orderByDesc('opened_at')
            ->paginate(50)
            ->through(fn($r) => [
                'email'      => $r->email,
                'name'       => $r->name,
                'status'     => $r->status,
                'open_count' => $r->open_count,
                'click_count' => $r->click_count,
                'sent_at'    => $r->sent_at?->toISOString(),
                'error'      => $r->error,
            ]);

        return response()->json($rows);
    }

    // --- Templates -------------------------------------------------------

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

    // --- Helpers ---------------------------------------------------------

    /**
     * Materialise one recipient row per subscribed contact in the audience.
     * No-op if recipients already exist. Returns the number created.
     */
    private function buildRecipients(EmailCampaign $campaign): int
    {
        if ($campaign->recipients()->exists()) {
            return 0;
        }

        if (!$campaign->audience_group_id || !$campaign->audienceGroup) {
            throw new \RuntimeException('Select an audience group before sending.');
        }

        $contacts = $this->resolver->query($campaign->audienceGroup)
            ->where('status', 'subscribed')
            ->get(['email_contacts.id', 'email_contacts.full_name', 'email_contacts.email']);

        $now = now();
        $rows = $contacts->map(fn($c) => [
            'email_campaign_id' => $campaign->id,
            'email_contact_id'  => $c->id,
            'email'             => $c->email,
            'name'              => $c->full_name,
            'status'            => 'pending',
            'token'             => Str::random(40),
            'created_at'        => $now,
            'updated_at'        => $now,
        ])->all();

        if ($rows) {
            $campaign->recipients()->insert($rows);
            $campaign->update(['audience_count' => count($rows)]);
        }

        return count($rows);
    }

    private function audienceCount(?int $groupId): int
    {
        if (!$groupId) {
            return 0;
        }

        $group = \App\Models\EmailAudienceGroup::find($groupId);
        if (!$group) {
            return 0;
        }

        return $this->resolver->query($group)->where('status', 'subscribed')->count();
    }

    private function validateCampaign(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name'              => "{$required}|string|max:255",
            'subject'           => 'nullable|string|max:500',
            'preview_text'      => 'nullable|string|max:255',
            'body'              => 'nullable|string',
            'sender_name'       => 'nullable|string|max:255',
            'sender_email'      => 'nullable|email|max:255',
            'audience_group_id' => 'nullable|integer|exists:email_audience_groups,id',
            'scheduled_at'      => 'nullable|date',
        ]);
    }

    private function present(EmailCampaign $campaign): array
    {
        return [
            'id'                 => $campaign->id,
            'name'               => $campaign->name,
            'subject'            => $campaign->subject,
            'preview_text'       => $campaign->preview_text,
            'body'               => $campaign->body,
            'sender_name'        => $campaign->sender_name,
            'sender_email'       => $campaign->sender_email,
            'status'             => $campaign->status,
            'audience_group_id'  => $campaign->audience_group_id,
            'audience_group'     => $campaign->audienceGroup?->name,
            'scheduled_at'       => $campaign->scheduled_at?->toISOString(),
            'sent_at'            => $campaign->sent_at?->toISOString(),
            'audience_count'     => $campaign->audience_count,
            'sent_count'         => $campaign->sent_count,
            'delivered_count'    => $campaign->delivered_count,
            'opened_count'       => $campaign->opened_count,
            'clicked_count'      => $campaign->clicked_count,
            'bounced_count'      => $campaign->bounced_count,
            'unsubscribed_count' => $campaign->unsubscribed_count,
            'open_rate'          => $campaign->open_rate,
            'click_rate'         => $campaign->click_rate,
            'created_at'         => $campaign->created_at?->toISOString(),
            'updated_at'         => $campaign->updated_at?->toISOString(),
        ];
    }
}
