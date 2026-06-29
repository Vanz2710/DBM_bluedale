<?php

namespace Database\Seeders;

use App\Models\EmailAudienceGroup;
use App\Models\EmailCampaign;
use App\Models\PostingCalendarReminder;
use App\Models\SocialMediaReminder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MarketingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPackages();
        $this->seedSocialMedia();
        $this->seedPostingCalendar();
        $this->seedEmailCampaigns();
    }

    private function seedPackages(): void
    {
        $packages = ['10 Posts', '15 Posts', '20 Posts', 'Reels Package', 'Full Management'];
        foreach ($packages as $name) {
            \App\Models\SocialMediaPackage::firstOrCreate(['name' => $name]);
        }
    }

    // -------------------------------------------------------------------------
    // Social Media Reminders
    // -------------------------------------------------------------------------
    private function seedSocialMedia(): void
    {
        $clients = [
            ['company_name' => 'Bluedale Marketing Sdn Bhd',   'package' => 'Full Management',  'month' => 'July 2026',  'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'posted',     'posting_staff_initials' => 'VS', 'report_status' => 'done'],
            ['company_name' => 'Nasi Lemak Warisan KL',         'package' => '15 Posts',          'month' => 'July 2026',  'content_calendar_status' => 'approved', 'artwork_editing_status' => 'wfa',      'posting_status' => 'scheduling',  'posting_staff_initials' => 'AK', 'report_status' => 'pending'],
            ['company_name' => 'Pavilion Retail Management',    'package' => '20 Posts',          'month' => 'July 2026',  'content_calendar_status' => 'wfa',      'artwork_editing_status' => 'pending',  'posting_status' => 'pending',     'posting_staff_initials' => null, 'report_status' => 'pending'],
            ['company_name' => 'MontVerde Café & Co.',          'package' => 'Reels Package',     'month' => 'July 2026',  'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'scheduled',   'posting_staff_initials' => 'LJ', 'report_status' => 'pending'],
            ['company_name' => '101 Resort & Spa',              'package' => '10 Posts',          'month' => 'July 2026',  'content_calendar_status' => 'pending',  'artwork_editing_status' => 'pending',  'posting_status' => 'pending',     'posting_staff_initials' => null, 'report_status' => 'pending'],
            ['company_name' => 'Urban Bites Restaurant',        'package' => '15 Posts',          'month' => 'July 2026',  'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'posted',      'posting_staff_initials' => 'RM', 'report_status' => 'wfa'],

            ['company_name' => 'Bluedale Marketing Sdn Bhd',   'package' => 'Full Management',  'month' => 'June 2026',  'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'posted',      'posting_staff_initials' => 'VS', 'report_status' => 'completed'],
            ['company_name' => 'Nasi Lemak Warisan KL',         'package' => '15 Posts',          'month' => 'June 2026',  'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'posted',      'posting_staff_initials' => 'AK', 'report_status' => 'completed'],
            ['company_name' => 'Pavilion Retail Management',    'package' => '20 Posts',          'month' => 'June 2026',  'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'posted',      'posting_staff_initials' => 'LJ', 'report_status' => 'done'],
            ['company_name' => 'MontVerde Café & Co.',          'package' => 'Reels Package',     'month' => 'June 2026',  'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'posted',      'posting_staff_initials' => 'VS', 'report_status' => 'completed'],
            ['company_name' => '101 Resort & Spa',              'package' => '10 Posts',          'month' => 'June 2026',  'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'posted',      'posting_staff_initials' => 'RM', 'report_status' => 'completed'],
            ['company_name' => 'Urban Bites Restaurant',        'package' => '15 Posts',          'month' => 'June 2026',  'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'posted',      'posting_staff_initials' => 'AK', 'report_status' => 'done'],

            ['company_name' => 'Green Valley Wellness',         'package' => '10 Posts',          'month' => 'May 2026',   'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'posted',      'posting_staff_initials' => 'VS', 'report_status' => 'completed'],
            ['company_name' => 'KL Skyline Properties',         'package' => '20 Posts',          'month' => 'May 2026',   'content_calendar_status' => 'approved', 'artwork_editing_status' => 'approved', 'posting_status' => 'posted',      'posting_staff_initials' => 'LJ', 'report_status' => 'completed'],
        ];

        foreach ($clients as $row) {
            SocialMediaReminder::firstOrCreate(
                ['company_name' => $row['company_name'], 'month' => $row['month']],
                $row
            );
        }
    }

    // -------------------------------------------------------------------------
    // Posting Calendar
    // -------------------------------------------------------------------------
    private function seedPostingCalendar(): void
    {
        $userId = \App\Models\User::first()?->id ?? 1;

        $entries = [
            // July 2026 — current month forward
            ['title' => 'July Promo Graphics',          'platform' => 'FB',       'client' => 'Bluedale Marketing',  'date' => '2026-07-01', 'time' => '10:00:00', 'status' => 'posted'],
            ['title' => 'Product Launch Reel',          'platform' => 'IG',       'client' => 'Nasi Lemak Warisan',  'date' => '2026-07-02', 'time' => '12:00:00', 'status' => 'posted'],
            ['title' => 'Mid-Year Sale Countdown',      'platform' => 'TikTok',   'client' => 'Pavilion Retail',     'date' => '2026-07-03', 'time' => '18:00:00', 'status' => 'posted'],
            ['title' => 'Corporate Update Post',        'platform' => 'LinkedIn', 'client' => 'Bluedale Marketing',  'date' => '2026-07-04', 'time' => '09:00:00', 'status' => 'scheduled'],
            ['title' => 'Weekend Special Promo',        'platform' => 'FB',       'client' => 'Urban Bites',         'date' => '2026-07-05', 'time' => '11:00:00', 'status' => 'scheduled'],
            ['title' => 'Menu Highlight Stories',       'platform' => 'IG',       'client' => 'MontVerde Café',      'date' => '2026-07-06', 'time' => '14:00:00', 'status' => 'approval'],
            ['title' => 'Resort Package Promotion',     'platform' => 'FB',       'client' => '101 Resort & Spa',    'date' => '2026-07-07', 'time' => '10:00:00', 'status' => 'approval'],
            ['title' => 'Behind the Scenes Clip',       'platform' => 'TikTok',   'client' => 'Bluedale Marketing',  'date' => '2026-07-08', 'time' => '16:00:00', 'status' => 'design'],
            ['title' => 'Testimonial Graphic',          'platform' => 'IG',       'client' => 'Urban Bites',         'date' => '2026-07-09', 'time' => '12:00:00', 'status' => 'design'],
            ['title' => 'Blog Feature Post',            'platform' => 'Website',  'client' => 'Bluedale Marketing',  'date' => '2026-07-10', 'time' => null,        'status' => 'planned'],
            ['title' => 'Flash Sale Announcement',      'platform' => 'FB',       'client' => 'Pavilion Retail',     'date' => '2026-07-11', 'time' => '08:00:00', 'status' => 'planned'],
            ['title' => 'Staff Spotlight',              'platform' => 'LinkedIn', 'client' => 'Bluedale Marketing',  'date' => '2026-07-14', 'time' => '10:00:00', 'status' => 'planned'],
            ['title' => 'Noodle Bowl Story',            'platform' => 'IG',       'client' => 'Nasi Lemak Warisan',  'date' => '2026-07-15', 'time' => '13:00:00', 'status' => 'planned'],
            ['title' => 'Ramadan Reel (throwback)',     'platform' => 'TikTok',   'client' => 'Urban Bites',         'date' => '2026-07-16', 'time' => '19:00:00', 'status' => 'planned'],
            ['title' => 'New Room Launch',              'platform' => 'FB',       'client' => '101 Resort & Spa',    'date' => '2026-07-17', 'time' => '11:00:00', 'status' => 'planned'],
            ['title' => 'Weekly Special Post',          'platform' => 'IG',       'client' => 'MontVerde Café',      'date' => '2026-07-18', 'time' => '14:00:00', 'status' => 'planned'],
            ['title' => 'Industry Insight Article',     'platform' => 'LinkedIn', 'client' => 'Bluedale Marketing',  'date' => '2026-07-21', 'time' => '09:00:00', 'status' => 'planned'],
            ['title' => 'Customer Review Graphic',      'platform' => 'FB',       'client' => 'Urban Bites',         'date' => '2026-07-22', 'time' => '12:00:00', 'status' => 'planned'],
            ['title' => 'End-of-Month Deal',            'platform' => 'IG',       'client' => 'Pavilion Retail',     'date' => '2026-07-28', 'time' => '10:00:00', 'status' => 'planned'],
            ['title' => 'August Teaser',                'platform' => 'TikTok',   'client' => 'Bluedale Marketing',  'date' => '2026-07-30', 'time' => '18:00:00', 'status' => 'planned'],

            // June 2026 — past (all posted)
            ['title' => 'June Launch Announcement',     'platform' => 'FB',       'client' => 'Bluedale Marketing',  'date' => '2026-06-01', 'time' => '09:00:00', 'status' => 'posted'],
            ['title' => 'Hari Raya Recap Reel',         'platform' => 'IG',       'client' => 'Nasi Lemak Warisan',  'date' => '2026-06-05', 'time' => '14:00:00', 'status' => 'posted'],
            ['title' => 'Summer Sale Countdown',        'platform' => 'TikTok',   'client' => 'Pavilion Retail',     'date' => '2026-06-10', 'time' => '18:00:00', 'status' => 'posted'],
            ['title' => 'Mid-June Update',              'platform' => 'LinkedIn', 'client' => 'Bluedale Marketing',  'date' => '2026-06-15', 'time' => '10:00:00', 'status' => 'posted'],
            ['title' => 'Café New Menu Reveal',         'platform' => 'IG',       'client' => 'MontVerde Café',      'date' => '2026-06-20', 'time' => '12:00:00', 'status' => 'posted'],
        ];

        foreach ($entries as $entry) {
            PostingCalendarReminder::firstOrCreate(
                ['user_id' => $userId, 'title' => $entry['title'], 'date' => $entry['date']],
                array_merge($entry, ['user_id' => $userId])
            );
        }
    }

    // -------------------------------------------------------------------------
    // Email Campaigns
    // -------------------------------------------------------------------------
    private function seedEmailCampaigns(): void
    {
        $userId = \App\Models\User::first()?->id ?? 1;

        // Audience groups
        $groups = [
            ['name' => 'All Contacts',     'type' => 'dynamic', 'filters' => [],                     'description' => 'Everyone in the contact book.'],
            ['name' => 'Leads',            'type' => 'dynamic', 'filters' => ['tag' => 'Lead'],      'description' => 'Contacts tagged as leads.'],
            ['name' => 'Existing Clients', 'type' => 'dynamic', 'filters' => ['tag' => 'Client'],    'description' => 'Contacts tagged as clients.'],
            ['name' => 'VIP Clients',      'type' => 'dynamic', 'filters' => ['tag' => 'VIP'],       'description' => 'Your most important contacts.'],
            ['name' => 'Inactive Clients', 'type' => 'dynamic', 'filters' => ['activity' => 'none'], 'description' => 'Contacts who never opened an email.'],
        ];

        $groupIds = [];
        foreach ($groups as $group) {
            $g = EmailAudienceGroup::firstOrCreate(
                ['name' => $group['name']],
                ['type' => $group['type'], 'filters' => $group['filters'], 'description' => $group['description'], 'is_system' => true]
            );
            $groupIds[$group['name']] = $g->id;
        }

        $campaigns = [
            // Sent campaigns — include realistic metrics
            [
                'name'               => 'Mid-Year Sale 2026',
                'subject'            => 'Exclusive Mid-Year Deals Just for You',
                'preview_text'       => 'Up to 40% off selected packages — limited time only.',
                'body'               => $this->sampleHtmlBody('Mid-Year Sale 2026', 'Check out our exclusive mid-year deals with up to 40% off selected packages.'),
                'sender_name'        => 'Bluedale Marketing',
                'sender_email'       => 'marketing@bluedale.com.my',
                'status'             => 'sent',
                'audience_group_id'  => $groupIds['All Contacts'],
                'audience_count'     => 320,
                'sent_count'         => 314,
                'delivered_count'    => 308,
                'opened_count'       => 142,
                'clicked_count'      => 38,
                'bounced_count'      => 6,
                'unsubscribed_count' => 2,
                'open_rate'          => 46.1,
                'click_rate'         => 12.3,
                'sent_at'            => Carbon::parse('2026-06-15 09:00:00'),
                'scheduled_at'       => null,
                'user_id'            => $userId,
            ],
            [
                'name'               => 'Welcome to Bluedale CRM',
                'subject'            => 'Welcome! Here\'s how we can help your business grow',
                'preview_text'       => 'Start your journey with personalised marketing support.',
                'body'               => $this->sampleHtmlBody('Welcome to Bluedale', 'We are excited to have you on board. Here\'s a quick overview of how we can help your business grow this year.'),
                'sender_name'        => 'Vance @ Bluedale',
                'sender_email'       => 'vance@bluedale.com.my',
                'status'             => 'sent',
                'audience_group_id'  => $groupIds['Leads'],
                'audience_count'     => 85,
                'sent_count'         => 83,
                'delivered_count'    => 81,
                'opened_count'       => 52,
                'clicked_count'      => 19,
                'bounced_count'      => 2,
                'unsubscribed_count' => 1,
                'open_rate'          => 64.2,
                'click_rate'         => 23.5,
                'sent_at'            => Carbon::parse('2026-06-01 10:30:00'),
                'scheduled_at'       => null,
                'user_id'            => $userId,
            ],

            // Scheduled campaigns
            [
                'name'               => 'Q3 Client Newsletter',
                'subject'            => 'Your July Update from Bluedale',
                'preview_text'       => 'Case studies, news and what\'s coming this quarter.',
                'body'               => $this->sampleHtmlBody('Q3 Client Newsletter', 'Here is your quarterly update with case studies, industry news, and what we have planned for Q3.'),
                'sender_name'        => 'Bluedale Marketing',
                'sender_email'       => 'marketing@bluedale.com.my',
                'status'             => 'scheduled',
                'audience_group_id'  => $groupIds['Existing Clients'],
                'audience_count'     => 210,
                'sent_count'         => 0,
                'delivered_count'    => 0,
                'opened_count'       => 0,
                'clicked_count'      => 0,
                'bounced_count'      => 0,
                'unsubscribed_count' => 0,
                'open_rate'          => 0,
                'click_rate'         => 0,
                'sent_at'            => null,
                'scheduled_at'       => Carbon::parse('2026-07-07 09:00:00'),
                'user_id'            => $userId,
            ],
            [
                'name'               => 'VIP Early Access — August Packages',
                'subject'            => 'You\'re invited: first look at our August packages',
                'preview_text'       => 'Exclusive early access for our VIP clients.',
                'body'               => $this->sampleHtmlBody('VIP Early Access', 'As one of our valued VIP clients, you get first access to our new August packages before they go public.'),
                'sender_name'        => 'Bluedale Marketing',
                'sender_email'       => 'marketing@bluedale.com.my',
                'status'             => 'scheduled',
                'audience_group_id'  => $groupIds['VIP Clients'],
                'audience_count'     => 42,
                'sent_count'         => 0,
                'delivered_count'    => 0,
                'opened_count'       => 0,
                'clicked_count'      => 0,
                'bounced_count'      => 0,
                'unsubscribed_count' => 0,
                'open_rate'          => 0,
                'click_rate'         => 0,
                'sent_at'            => null,
                'scheduled_at'       => Carbon::parse('2026-07-14 08:00:00'),
                'user_id'            => $userId,
            ],

            // Draft campaigns
            [
                'name'               => 'Re-engagement — Inactive Clients',
                'subject'            => 'We miss you — here\'s what\'s new at Bluedale',
                'preview_text'       => 'It\'s been a while. Let\'s reconnect.',
                'body'               => $this->sampleHtmlBody('We Miss You', 'It has been a while since we last connected. We have some exciting new services and would love to catch up.'),
                'sender_name'        => 'Bluedale Marketing',
                'sender_email'       => 'marketing@bluedale.com.my',
                'status'             => 'draft',
                'audience_group_id'  => $groupIds['Inactive Clients'],
                'audience_count'     => 67,
                'sent_count'         => 0,
                'delivered_count'    => 0,
                'opened_count'       => 0,
                'clicked_count'      => 0,
                'bounced_count'      => 0,
                'unsubscribed_count' => 0,
                'open_rate'          => 0,
                'click_rate'         => 0,
                'sent_at'            => null,
                'scheduled_at'       => null,
                'user_id'            => $userId,
            ],
            [
                'name'               => 'Year-End Promotion Draft',
                'subject'            => null,
                'preview_text'       => null,
                'body'               => null,
                'sender_name'        => null,
                'sender_email'       => null,
                'status'             => 'draft',
                'audience_group_id'  => null,
                'audience_count'     => 0,
                'sent_count'         => 0,
                'delivered_count'    => 0,
                'opened_count'       => 0,
                'clicked_count'      => 0,
                'bounced_count'      => 0,
                'unsubscribed_count' => 0,
                'open_rate'          => 0,
                'click_rate'         => 0,
                'sent_at'            => null,
                'scheduled_at'       => null,
                'user_id'            => $userId,
            ],
        ];

        foreach ($campaigns as $campaign) {
            EmailCampaign::firstOrCreate(
                ['name' => $campaign['name'], 'user_id' => $campaign['user_id']],
                $campaign
            );
        }
    }

    private function sampleHtmlBody(string $heading, string $body): string
    {
        return <<<HTML
<div style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:32px 24px;">
  <h1 style="font-size:24px;font-weight:800;color:#1d4ed8;margin:0 0 16px;">{$heading}</h1>
  <p style="font-size:15px;color:#374151;line-height:1.7;margin:0 0 20px;">{$body}</p>
  <p style="font-size:15px;color:#374151;line-height:1.7;margin:0 0 20px;">If you have any questions, simply reply to this email — we are always happy to help.</p>
  <a href="#" style="display:inline-block;background:#1d4ed8;color:#fff;font-weight:700;font-size:14px;padding:12px 28px;border-radius:6px;text-decoration:none;">Learn More</a>
  <hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 0 20px;" />
  <p style="font-size:12px;color:#9ca3af;margin:0;">Bluedale Marketing Sdn Bhd · Kuala Lumpur, Malaysia<br>You are receiving this because you are a client or contact of Bluedale. <a href="#" style="color:#9ca3af;">Unsubscribe</a></p>
</div>
HTML;
    }
}
