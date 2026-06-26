<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactArea;
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
        // NOTE: cache PLAIN ARRAYS, never Eloquent Collections/Models. On file/array
        // cache drivers (cPanel) a cached Collection deserializes as an incomplete
        // object and JSON-encodes as `{}` instead of `[]`, breaking `.filter()` on the
        // frontend. `->toArray()` keeps the cached blob as pure arrays. Key is versioned
        // (`_v2`) so any previously-cached broken value is bypassed.
        $data = Cache::remember('lookups_v2', 3600, function () {
            return [
                'statuses'          => ContactStatus::orderBy('name')->get()->toArray(),
                'industries'        => ContactIndustry::orderBy('name')->get()->toArray(),
                'categories'        => ContactCategory::orderBy('name')->get()->toArray(),
                'types'             => ContactType::orderBy('name')->get()->toArray(),
                'areas'             => ContactArea::orderBy('name')->get()->toArray(),
                'tasks'             => Task::orderBy('name')->get()->toArray(),
                'forecast_products' => ForecastProduct::orderBy('name')->get()->toArray(),
                'forecast_types'    => ForecastType::orderBy('name')->get()->toArray(),
                'forecast_results'  => ForecastResult::orderBy('name')->get()->toArray(),
            ];
        });

        // Users are fetched outside the cache — they change as accounts are added/removed
        $data['users'] = User::orderBy('name')->get(['id', 'name'])->toArray();

        return response()->json($data);
    }
}
