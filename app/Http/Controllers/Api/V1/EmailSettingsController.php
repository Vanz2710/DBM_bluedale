<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailSetting;
use App\Services\CampaignSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailSettingsController extends Controller
{
    public function show()
    {
        return response()->json(['data' => $this->present(EmailSetting::current())]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'smtp_host'        => 'nullable|string|max:255',
            'smtp_port'        => 'nullable|integer|min:1|max:65535',
            'smtp_username'    => 'nullable|string|max:255',
            'smtp_password'    => 'nullable|string|max:500',
            'smtp_encryption'  => 'nullable|in:tls,ssl,none',
            'from_name'        => 'nullable|string|max:255',
            'from_email'       => 'nullable|email|max:255',
            'reply_to'         => 'nullable|email|max:255',
            'company_name'     => 'nullable|string|max:255',
            'company_address'  => 'nullable|string|max:500',
            'email_footer'     => 'nullable|string|max:1000',
            'tracking_enabled' => 'nullable|boolean',
        ]);

        if (($data['smtp_encryption'] ?? null) === 'none') {
            $data['smtp_encryption'] = null;
        }

        // Keep the existing password when the field is left blank.
        if (empty($data['smtp_password'])) {
            unset($data['smtp_password']);
        }

        $settings = EmailSetting::current();
        $settings->update($data);

        return response()->json(['data' => $this->present($settings->fresh())]);
    }

    /**
     * Send a test message through the saved SMTP settings to verify them.
     */
    public function test(Request $request, CampaignSender $sender)
    {
        $request->validate(['email' => 'required|email']);

        $settings = EmailSetting::current();

        if (!$settings->isConfigured()) {
            return response()->json(['message' => 'Add an SMTP host and sender email before testing.'], 422);
        }

        try {
            $sender->configureMailer($settings);

            Mail::mailer('email_module')->html(
                '<p>This is a test email from your CRM email marketing module. If you received this, your SMTP settings work.</p>',
                function ($message) use ($request, $settings) {
                    $message->to($request->input('email'))
                        ->subject('CRM SMTP test')
                        ->from($settings->from_email, $settings->from_name);
                }
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'SMTP test failed: ' . $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Test email sent to ' . $request->input('email') . '.']);
    }

    /**
     * Shape settings for the UI: never expose the stored password, only whether
     * one is set, plus the tracking base URL and a configured flag.
     */
    private function present(EmailSetting $s): array
    {
        return [
            'smtp_host'        => $s->smtp_host,
            'smtp_port'        => $s->smtp_port,
            'smtp_username'    => $s->smtp_username,
            'smtp_password_set' => filled($s->smtp_password),
            'smtp_encryption'  => $s->smtp_encryption ?? 'none',
            'from_name'        => $s->from_name,
            'from_email'       => $s->from_email,
            'reply_to'         => $s->reply_to,
            'company_name'     => $s->company_name,
            'company_address'  => $s->company_address,
            'email_footer'     => $s->email_footer,
            'tracking_enabled' => $s->tracking_enabled,
            'configured'       => $s->isConfigured(),
            'tracking_base_url' => rtrim(config('app.url'), '/'),
        ];
    }
}
