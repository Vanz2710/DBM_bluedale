<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTest extends Command
{
    protected $signature = 'mail:test {to : Recipient email address}';

    protected $description = 'Send a test email to verify SMTP is configured correctly';

    public function handle(): int
    {
        $to = $this->argument('to');

        $this->info("Mailer:  " . config('mail.default'));
        $this->info("Host:    " . config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port'));
        $this->info("From:    " . config('mail.from.address'));
        $this->info("Sending test email to {$to} ...");

        try {
            Mail::raw(
                "This is a test email from the Bluedale CRM.\n\nIf you received this, SMTP is working.\n\nSent at " . now()->toDateTimeString(),
                fn ($m) => $m->to($to)->subject('Bluedale CRM — SMTP Test')
            );

            $this->info('✓ Sent without error. Check the inbox (and spam folder).');
            $this->line('  Note: with MAIL_MAILER=log, "sent" means it was written to storage/logs/laravel.log, not actually delivered.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✗ Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
