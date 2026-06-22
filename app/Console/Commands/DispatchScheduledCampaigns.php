<?php

namespace App\Console\Commands;

use App\Models\EmailCampaign;
use App\Services\CampaignSender;
use Illuminate\Console\Command;

class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'email:dispatch-scheduled';

    protected $description = 'Send any email campaigns whose scheduled time has arrived';

    public function handle(CampaignSender $sender): int
    {
        $due = EmailCampaign::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No campaigns due.');
            return self::SUCCESS;
        }

        foreach ($due as $campaign) {
            $this->info("Sending campaign #{$campaign->id}: {$campaign->name}");

            try {
                $result = $sender->send($campaign);
                $this->info("  sent={$result['sent']} failed={$result['failed']}");
            } catch (\Throwable $e) {
                $campaign->update(['status' => 'failed']);
                $this->error("  failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
