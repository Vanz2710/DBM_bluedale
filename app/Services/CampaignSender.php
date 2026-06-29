<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends campaigns over a runtime-configured SMTP mailer (built from the
 * email_settings row, not the .env mailer) and injects open/click/unsubscribe
 * tracking into every message.
 */
class CampaignSender
{
    /**
     * Build the "email_module" mailer from the stored SMTP settings.
     */
    public function configureMailer(EmailSetting $settings): void
    {
        config()->set('mail.mailers.email_module', [
            'transport'    => 'smtp',
            'host'         => $settings->smtp_host,
            'port'         => $settings->smtp_port ?: 587,
            'encryption'   => $settings->smtp_encryption ?: null,
            'username'     => $settings->smtp_username,
            'password'     => $settings->smtp_password,
            'timeout'      => 30,
            'local_domain' => parse_url(config('app.url'), PHP_URL_HOST) ?: null,
        ]);

        // Drop any previously resolved instance so the new config takes effect.
        app('mail.manager')->purge('email_module');
    }

    /**
     * Send every pending/failed recipient of a campaign. Returns a tally.
     */
    public function send(EmailCampaign $campaign): array
    {
        $settings = EmailSetting::current();

        if (!$settings->isConfigured()) {
            throw new \RuntimeException('SMTP is not configured. Open Email Settings and add your SMTP host and sender email first.');
        }

        $this->configureMailer($settings);

        $recipients = $campaign->recipients()
            ->whereIn('status', ['pending', 'failed'])
            ->with('contact')
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            if ($this->deliver($campaign, $recipient, $settings)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $this->refreshCounters($campaign);

        $campaign->update([
            'status'  => 'sent',
            'sent_at' => $campaign->sent_at ?? now(),
        ]);

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Deliver one recipient. Returns true on success.
     */
    private function deliver(EmailCampaign $campaign, EmailCampaignRecipient $recipient, EmailSetting $settings): bool
    {
        try {
            $html = $this->renderHtml($campaign, $recipient, $settings);

            Mail::mailer('email_module')->html($html, function ($message) use ($campaign, $recipient, $settings) {
                $message->to($recipient->email, $recipient->name ?: null)
                    ->subject($campaign->subject ?: $campaign->name)
                    ->from(
                        $campaign->sender_email ?: $settings->from_email,
                        $campaign->sender_name ?: $settings->from_name
                    );

                if ($settings->reply_to) {
                    $message->replyTo($settings->reply_to);
                }
            });

            $recipient->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
            $this->log($campaign, $recipient, 'sent');

            return true;
        } catch (\Throwable $e) {
            $recipient->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 480)]);
            $this->log($campaign, $recipient, 'failed');

            return false;
        }
    }

    /**
     * One-off test send that bypasses recipient records and tracking.
     */
    public function sendTest(EmailCampaign $campaign, string $email): void
    {
        $settings = EmailSetting::current();

        if (!$settings->isConfigured()) {
            throw new \RuntimeException('SMTP is not configured. Open Email Settings and add your SMTP host and sender email first.');
        }

        $this->configureMailer($settings);

        $body = $this->mergeTags($campaign->body ?? '', null, $email);
        $html = $this->wrap($campaign, $body, $settings, null);

        Mail::mailer('email_module')->html($html, function ($message) use ($campaign, $email, $settings) {
            $message->to($email)
                ->subject('[TEST] ' . ($campaign->subject ?: $campaign->name))
                ->from(
                    $campaign->sender_email ?: $settings->from_email,
                    $campaign->sender_name ?: $settings->from_name
                );
        });
    }

    /**
     * Recalculate campaign counters from recipient state.
     */
    public function refreshCounters(EmailCampaign $campaign): void
    {
        $r = $campaign->recipients();

        $sent        = (clone $r)->whereIn('status', ['sent', 'delivered', 'opened', 'clicked'])->count();
        $opened      = (clone $r)->whereIn('status', ['opened', 'clicked'])->count();
        $clicked     = (clone $r)->where('status', 'clicked')->count();
        $bounced     = (clone $r)->where('status', 'bounced')->count();
        $unsubscribed = (clone $r)->where('status', 'unsubscribed')->count();

        $campaign->update([
            'sent_count'         => $sent,
            'delivered_count'    => $sent,
            'opened_count'       => $opened,
            'clicked_count'      => $clicked,
            'bounced_count'      => $bounced,
            'unsubscribed_count' => $unsubscribed,
            'open_rate'          => $sent > 0 ? round($opened / $sent * 100, 2) : null,
            'click_rate'         => $sent > 0 ? round($clicked / $sent * 100, 2) : null,
        ]);
    }

    // --- HTML rendering --------------------------------------------------

    private function renderHtml(EmailCampaign $campaign, EmailCampaignRecipient $recipient, EmailSetting $settings): string
    {
        $body = $this->mergeTags($campaign->body ?? '', $recipient, $recipient->email);

        return $this->wrap($campaign, $body, $settings, $recipient);
    }

    /**
     * Replace merge tags with recipient/contact values.
     */
    private function mergeTags(string $body, ?EmailCampaignRecipient $recipient, string $email): string
    {
        $name    = $recipient?->name ?: '';
        $first   = $name ? Str::before($name, ' ') : 'there';
        $company = $recipient?->contact?->company ?? '';
        $phone   = $recipient?->contact?->phone ?? '';

        return strtr($body, [
            '{{first_name}}'   => $first,
            '{{full_name}}'    => $name ?: 'there',
            '{{email}}'        => $email,
            '{{company_name}}' => $company,
            '{{phone}}'        => $phone,
        ]);
    }

    /**
     * Wrap the body in a branded shell with tracking pixel, rewritten links,
     * footer, and unsubscribe link. Plain-text bodies are converted to HTML.
     */
    private function wrap(EmailCampaign $campaign, string $body, EmailSetting $settings, ?EmailCampaignRecipient $recipient): string
    {
        $isHtml = $body !== strip_tags($body);
        $content = $isHtml ? $this->inlineImages($body) : nl2br(e($body));

        $base = rtrim(config('app.url'), '/');
        $trackingOn = $settings->tracking_enabled && $recipient;

        // Rewrite links through the click tracker.
        if ($trackingOn) {
            $content = $this->rewriteLinks($content, $base, $recipient->token);
        }

        $pixel = '';
        if ($trackingOn) {
            $pixel = '<img src="' . $base . '/email/track/open/' . $recipient->token . '.png" width="1" height="1" alt="" style="display:none">';
        }

        $unsubscribe = '';
        if ($recipient) {
            $unsubscribe = '<a href="' . $base . '/email/unsubscribe/' . $recipient->token . '" style="color:#94a3b8">Unsubscribe</a>';
        }

        $footer = e($settings->email_footer ?: ($settings->company_name ?? ''));
        $company = e($settings->company_name ?? '');
        $address = e($settings->company_address ?? '');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#172033">
          <div style="max-width:600px;margin:0 auto;padding:24px">
            <div style="background:#ffffff;border-radius:10px;padding:28px;line-height:1.6;font-size:15px">
              {$content}
            </div>
            <div style="text-align:center;color:#94a3b8;font-size:12px;padding:18px 8px;line-height:1.6">
              <p style="margin:0 0 4px">{$footer}</p>
              <p style="margin:0 0 4px">{$company} {$address}</p>
              <p style="margin:0">{$unsubscribe}</p>
            </div>
          </div>
          {$pixel}
        </body>
        </html>
        HTML;
    }

    /**
     * Wrap each http(s) href in a signed click-tracking redirect.
     */
    private function rewriteLinks(string $html, string $base, string $token): string
    {
        return preg_replace_callback('/href="(https?:\/\/[^"]+)"/i', function ($m) use ($base, $token) {
            $url = $m[1];

            // Don't rewrite our own tracking/unsubscribe links.
            if (str_contains($url, '/email/track/') || str_contains($url, '/email/unsubscribe/')) {
                return $m[0];
            }

            $sig = hash_hmac('sha256', $token . '|' . $url, config('app.key'));
            $tracked = $base . '/email/track/click/' . $token
                . '?u=' . urlencode(base64_encode($url))
                . '&s=' . $sig;

            return 'href="' . $tracked . '"';
        }, $html);
    }

    /**
     * Replace local storage image URLs with base64 data URIs so images
     * render in email clients that cannot reach localhost.
     */
    private function inlineImages(string $html): string
    {
        return preg_replace_callback('/<img([^>]*?)src="([^"]+)"([^>]*?)>/i', function ($m) {
            $src  = $m[2];
            $path = $this->storageUrlToPath($src);
            if ($path && file_exists($path)) {
                $mime = mime_content_type($path);
                $src  = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
            return '<img' . $m[1] . 'src="' . $src . '"' . $m[3] . '>';
        }, $html);
    }

    private function storageUrlToPath(string $url): ?string
    {
        if (!str_contains($url, '/storage/email-images/')) {
            return null;
        }
        $filename = basename(parse_url($url, PHP_URL_PATH));
        return storage_path('app/public/email-images/' . $filename);
    }

    private function log(EmailCampaign $campaign, EmailCampaignRecipient $recipient, string $event): void
    {
        EmailLog::create([
            'email_campaign_id'           => $campaign->id,
            'email_campaign_recipient_id' => $recipient->id,
            'email_contact_id'            => $recipient->email_contact_id,
            'event'                       => $event,
        ]);
    }
}
