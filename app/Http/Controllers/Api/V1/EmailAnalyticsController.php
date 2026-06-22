<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailContact;
use App\Models\EmailLog;
use Illuminate\Support\Carbon;

class EmailAnalyticsController extends Controller
{
    /**
     * Top-line stats, recent + top campaigns, a 14-day performance series,
     * and an activity timeline for the dashboard.
     */
    public function dashboard()
    {
        $totalSent    = (int) EmailCampaign::sum('sent_count');
        $totalOpened  = (int) EmailCampaign::sum('opened_count');
        $totalClicked = (int) EmailCampaign::sum('clicked_count');

        return response()->json([
            'stats' => [
                'total_contacts'     => EmailContact::count(),
                'active_subscribers' => EmailContact::where('status', 'subscribed')->count(),
                'total_campaigns'    => EmailCampaign::count(),
                'emails_sent'        => $totalSent,
                'open_rate'          => $totalSent > 0 ? round($totalOpened / $totalSent * 100, 1) : 0,
                'click_rate'         => $totalSent > 0 ? round($totalClicked / $totalSent * 100, 1) : 0,
                'unsubscribed'       => EmailContact::where('status', 'unsubscribed')->count(),
            ],
            'performance'      => $this->performanceSeries(),
            'recent_campaigns' => $this->recentCampaigns(),
            'top_campaigns'    => $this->topCampaigns(),
            'timeline'         => $this->timeline(),
        ]);
    }

    /**
     * Trend lines, campaign comparison, audience growth, and most-active contacts.
     */
    public function analytics()
    {
        $sent = EmailCampaign::where('status', 'sent')
            ->orderBy('sent_at')
            ->get(['name', 'sent_count', 'opened_count', 'clicked_count', 'open_rate', 'click_rate', 'sent_at']);

        return response()->json([
            'trend' => $sent->map(fn($c) => [
                'name'       => $c->name,
                'open_rate'  => $c->open_rate ?? 0,
                'click_rate' => $c->click_rate ?? 0,
                'date'       => $c->sent_at?->toDateString(),
            ])->values(),
            'comparison'          => $this->topCampaigns(8),
            'audience_growth'     => $this->audienceGrowth(),
            'most_active_contacts' => $this->mostActiveContacts(),
        ]);
    }

    // --- Builders --------------------------------------------------------

    private function performanceSeries(): array
    {
        $start = now()->subDays(13)->startOfDay();

        $rows = EmailLog::where('created_at', '>=', $start)
            ->selectRaw('date(created_at) as d, event, count(*) as c')
            ->groupBy('d', 'event')
            ->get();

        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $series[$date] = ['date' => $date, 'sent' => 0, 'opened' => 0, 'clicked' => 0];
        }

        foreach ($rows as $row) {
            if (!isset($series[$row->d])) {
                continue;
            }
            match ($row->event) {
                'sent'  => $series[$row->d]['sent'] = $row->c,
                'open'  => $series[$row->d]['opened'] = $row->c,
                'click' => $series[$row->d]['clicked'] = $row->c,
                default => null,
            };
        }

        return array_values($series);
    }

    private function recentCampaigns(int $limit = 6): array
    {
        return EmailCampaign::with('audienceGroup:id,name')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'status'         => $c->status,
                'audience'       => $c->audienceGroup?->name,
                'audience_count' => $c->audience_count,
                'sent_count'     => $c->sent_count,
                'open_rate'      => $c->open_rate,
                'click_rate'     => $c->click_rate,
                'date'           => ($c->sent_at ?? $c->updated_at)?->toISOString(),
            ])->all();
    }

    private function topCampaigns(int $limit = 5): array
    {
        return EmailCampaign::where('sent_count', '>', 0)
            ->orderByDesc('open_rate')
            ->limit($limit)
            ->get()
            ->map(fn($c) => [
                'name'       => $c->name,
                'sent_count' => $c->sent_count,
                'open_rate'  => $c->open_rate ?? 0,
                'click_rate' => $c->click_rate ?? 0,
            ])->all();
    }

    private function timeline(int $limit = 8): array
    {
        return EmailCampaign::orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn($c) => [
                'id'     => $c->id,
                'name'   => $c->name,
                'status' => $c->status,
                'count'  => $c->audience_count,
                'at'     => $c->updated_at?->toISOString(),
            ])->all();
    }

    private function audienceGrowth(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $months[] = [
                'month' => $month->format('M Y'),
                'count' => EmailContact::whereBetween('created_at', [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                ])->count(),
            ];
        }

        return $months;
    }

    private function mostActiveContacts(int $limit = 10): array
    {
        return EmailCampaignRecipient::selectRaw('email, name, sum(open_count) as opens, sum(click_count) as clicks')
            ->groupBy('email', 'name')
            ->havingRaw('sum(open_count) + sum(click_count) > 0')
            ->orderByRaw('sum(open_count) + sum(click_count) desc')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'email'  => $r->email,
                'name'   => $r->name,
                'opens'  => (int) $r->opens,
                'clicks' => (int) $r->clicks,
            ])->all();
    }
}
