<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    private static function defaults(): array
    {
        return [
            'theme'             => 'system',
            'timezone'          => 'UTC',
            'date_format'       => 'DD/MM/YYYY',
            'time_format'       => '12h',
            'first_day_of_week' => 'monday',
            'notifications'     => [
                'crm_reminders'   => true,
                'whatsapp_alerts' => true,
                'deal_updates'    => true,
                'task_reminders'  => true,
            ],
            'crm' => [
                'default_landing'      => '/',
                'contact_list_density' => 'comfortable',
                'records_per_page'     => 20,
                'show_completed_tasks' => false,
                'pipeline_view'        => 'list',
            ],
        ];
    }

    public function show(Request $request)
    {
        $stored   = $request->user()->settings ?? [];
        $settings = array_replace_recursive(self::defaults(), $stored);

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'theme'                         => 'sometimes|string|in:light,dark,system',
            'timezone'                      => 'sometimes|string|max:100',
            'date_format'                   => 'sometimes|string|in:DD/MM/YYYY,MM/DD/YYYY,YYYY-MM-DD,DD-MM-YYYY',
            'time_format'                   => 'sometimes|string|in:12h,24h',
            'first_day_of_week'             => 'sometimes|string|in:monday,sunday,saturday',
            'notifications'                 => 'sometimes|array',
            'notifications.crm_reminders'   => 'sometimes|boolean',
            'notifications.whatsapp_alerts' => 'sometimes|boolean',
            'notifications.deal_updates'    => 'sometimes|boolean',
            'notifications.task_reminders'  => 'sometimes|boolean',
            'crm'                           => 'sometimes|array',
            'crm.default_landing'           => 'sometimes|string|max:255',
            'crm.contact_list_density'      => 'sometimes|string|in:comfortable,compact',
            'crm.records_per_page'          => 'sometimes|integer|in:10,20,50,100',
            'crm.show_completed_tasks'      => 'sometimes|boolean',
            'crm.pipeline_view'             => 'sometimes|string|in:list,kanban',
        ]);

        $user     = $request->user();
        $existing = $user->settings ?? [];
        $incoming = $request->only([
            'theme', 'timezone', 'date_format', 'time_format',
            'first_day_of_week', 'notifications', 'crm',
        ]);

        $merged = array_replace_recursive($existing, $incoming);
        $user->update(['settings' => $merged]);
        $user->refresh();

        return response()->json([
            'settings' => array_replace_recursive(self::defaults(), $user->settings ?? []),
        ]);
    }
}
