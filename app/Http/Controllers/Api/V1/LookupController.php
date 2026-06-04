<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactCategory;
use App\Models\ContactIndustry;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\ForecastProduct;
use App\Models\ForecastResult;
use App\Models\ForecastType;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class LookupController extends Controller
{
    public function all()
    {
        $data = Cache::remember('lookups', 3600, function () {
            return [
                'statuses'    => ContactStatus::orderBy('name')->get(),
                'industries'  => ContactIndustry::orderBy('name')->get(),
                'categories'  => ContactCategory::orderBy('name')->get(),
                'types'       => ContactType::orderBy('name')->get(),
                'users'       => User::orderBy('name')->get(['id', 'name']),
                'tasks'       => Task::orderBy('name')->get(),
                'forecast_products' => ForecastProduct::orderBy('name')->get(),
                'forecast_types'    => ForecastType::orderBy('name')->get(),
                'forecast_results'  => ForecastResult::orderBy('name')->get(),
            ];
        });

        return response()->json($data);
    }
}
