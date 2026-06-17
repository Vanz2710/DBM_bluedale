<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaignRecipient;
use App\Models\EmailLog;
use App\Services\CampaignSender;
use Illuminate\Http\Request;

/**
 * Public (unauthenticated) endpoints hit by email clients: open pixel,
 * click redirect, and unsubscribe. Identified only by an opaque recipient token.
 */
class EmailTrackingController extends Controller
{
    /** 1x1 transparent GIF returned by the open-tracking pixel. */
    private const PIXEL = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    public function open(string $token, Request $request)
    {
        $recipient = EmailCampaignRecipient::where('token', $token)->first();

        if ($recipient) {
            $recipient->increment('open_count');
            $recipient->forceFill([
                'opened_at' => $recipient->opened_at ?? now(),
                'status'    => in_array($recipient->status, ['clicked', 'unsubscribed']) ? $recipient->status : 'opened',
            ])->save();

            $this->log($recipient, 'open', $request);
            app(CampaignSender::class)->refreshCounters($recipient->campaign);
        }

        return response(self::PIXEL, 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
        ]);
    }

    public function click(string $token, Request $request)
    {
        $encoded = (string) $request->query('u', '');
        $sig     = (string) $request->query('s', '');
        $url     = base64_decode($encoded, true);

        abort_if($url === false, 404);

        $expected = hash_hmac('sha256', $token . '|' . $url, config('app.key'));
        abort_unless(hash_equals($expected, $sig), 403, 'Invalid tracking link.');

        $recipient = EmailCampaignRecipient::where('token', $token)->first();

        if ($recipient) {
            $recipient->increment('click_count');
            $recipient->forceFill([
                'clicked_at' => $recipient->clicked_at ?? now(),
                'opened_at'  => $recipient->opened_at ?? now(),
                'status'     => $recipient->status === 'unsubscribed' ? 'unsubscribed' : 'clicked',
            ])->save();

            $this->log($recipient, 'click', $request, $url);
            app(CampaignSender::class)->refreshCounters($recipient->campaign);
        }

        return redirect()->away($url);
    }

    public function unsubscribePage(string $token)
    {
        $recipient = EmailCampaignRecipient::where('token', $token)->first();
        abort_if(!$recipient, 404);

        return view('email.unsubscribe', [
            'token'        => $token,
            'email'        => $recipient->email,
            'done'         => $recipient->status === 'unsubscribed',
        ]);
    }

    public function unsubscribe(string $token, Request $request)
    {
        $recipient = EmailCampaignRecipient::where('token', $token)->first();
        abort_if(!$recipient, 404);

        $recipient->update(['status' => 'unsubscribed']);

        if ($recipient->contact) {
            $recipient->contact->update([
                'status'          => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]);
        }

        $this->log($recipient, 'unsubscribe', $request);
        app(CampaignSender::class)->refreshCounters($recipient->campaign);

        return view('email.unsubscribe', [
            'token' => $token,
            'email' => $recipient->email,
            'done'  => true,
        ]);
    }

    private function log(EmailCampaignRecipient $recipient, string $event, Request $request, ?string $url = null): void
    {
        EmailLog::create([
            'email_campaign_id'           => $recipient->email_campaign_id,
            'email_campaign_recipient_id' => $recipient->id,
            'email_contact_id'            => $recipient->email_contact_id,
            'event'                       => $event,
            'url'                         => $url,
            'ip_address'                  => $request->ip(),
            'user_agent'                  => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
