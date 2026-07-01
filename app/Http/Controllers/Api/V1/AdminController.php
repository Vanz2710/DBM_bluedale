<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Contact;
use App\Models\ContactArea;
use App\Models\ContactCategory;
use App\Models\ContactIndustry;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\Forecast;
use App\Models\ForecastProduct;
use App\Models\ForecastResult;
use App\Models\ForecastType;
use App\Models\PerformanceTarget;
use App\Models\SocialMediaPackage;
use App\Models\SocialMediaReminder;
use App\Models\Task;
use App\Models\ToDo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private static array $map = [
        'statuses'          => [ContactStatus::class,  'name'],
        'types'             => [ContactType::class,     'name'],
        'industries'        => [ContactIndustry::class, 'name'],
        'categories'        => [ContactCategory::class, 'name'],
        'areas'             => [ContactArea::class,     'name'],
        'tasks'             => [Task::class,            'name'],
        'forecast-products' => [ForecastProduct::class, 'name'],
        'forecast-types'    => [ForecastType::class,    'name'],
        'forecast-results'  => [ForecastResult::class,  'name'],
        'packages'          => [SocialMediaPackage::class, 'name'],
    ];

    // Referencing model classes (resolved to table names via getTable() at call time)
    // instead of hardcoded table-name strings, so renaming a model's table can't
    // silently desync this map from the schema.
    private static array $usageMap = [
        'statuses'          => [[Contact::class, 'status_id']],
        'types'             => [[Contact::class, 'type_id']],
        'industries'        => [[Contact::class, 'industry_id']],
        'categories'        => [[Contact::class, 'category_id']],
        'areas'             => [[Contact::class, 'area_id']],
        'tasks'             => [[ToDo::class, 'task_id'], [PerformanceTarget::class, 'task_id']],
        'forecast-products' => [[Forecast::class, 'product_id']],
        'forecast-types'    => [[Forecast::class, 'forecast_type_id']],
        'forecast-results'  => [[Forecast::class, 'result_id']],
        // 3rd element = parent column to match on (defaults to 'id').
        // social_media_reminders.package stores the package name, not a FK id.
        'packages'          => [[SocialMediaReminder::class, 'package', 'name']],
    ];

    public function index(string $entity)
    {
        [$model] = $this->resolve($entity);
        $modelTable = (new $model)->getTable();
        $sources    = self::$usageMap[$entity];

        $subParts = array_map(function ($src) use ($modelTable) {
            $childTable = (new $src[0])->getTable();
            $parentCol  = $src[2] ?? 'id';
            return "(SELECT COUNT(*) FROM {$childTable} WHERE {$src[1]} = {$modelTable}.{$parentCol})";
        }, $sources);
        $usageExpr = implode(' + ', $subParts);

        $items = $model::selectRaw("*, ({$usageExpr}) as usage_count")
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, string $entity)
    {
        [$model] = $this->resolve($entity);

        $validated = $request->validate(['name' => 'required|string|max:255']);

        if ($model::where('name', $validated['name'])->exists()) {
            throw ValidationException::withMessages(['name' => ['This entry already exists.']]);
        }

        $item = $model::create($validated);

        Cache::forget('lookups_v2');
        $this->audit('created', $entity, $item->id, $item->name, null, ['name' => $item->name], $request);

        return response()->json(['status' => 'success', 'data' => $item], 201);
    }

    public function update(Request $request, string $entity, string $id)
    {
        [$model] = $this->resolve($entity);
        $item = $model::findOrFail($id);

        $validated = $request->validate(['name' => 'required|string|max:255']);

        if ($model::where('name', $validated['name'])->where('id', '!=', $id)->exists()) {
            throw ValidationException::withMessages(['name' => ['This name already exists.']]);
        }

        $old = ['name' => $item->name];
        $item->update($validated);

        Cache::forget('lookups_v2');
        $this->audit('updated', $entity, $item->id, $item->name, $old, ['name' => $item->name], $request);

        return response()->json(['status' => 'success', 'data' => $item]);
    }

    public function destroy(Request $request, string $entity, string $id)
    {
        [$model] = $this->resolve($entity);

        return DB::transaction(function () use ($model, $entity, $id, $request) {
            $item = $model::lockForUpdate()->findOrFail($id);

            $count = 0;
            foreach (self::$usageMap[$entity] as $src) {
                [$childModel, $col] = $src;
                $childTable = (new $childModel)->getTable();
                $matchValue = isset($src[2]) ? $item->{$src[2]} : $id;
                $count += DB::table($childTable)->where($col, $matchValue)->count();
            }

            if ($count > 0) {
                return response()->json([
                    'message' => "Cannot delete \"{$item->name}\" — it is referenced by {$count} record(s). Remove those references first.",
                ], 409);
            }

            Cache::forget('lookups_v2');
            $this->audit('deleted', $entity, $item->id, $item->name, ['name' => $item->name], null, $request);

            $item->delete();
            return response()->json(['status' => 'success']);
        });
    }

    private function resolve(string $entity): array
    {
        if (!array_key_exists($entity, self::$map)) {
            abort(404, "Unknown admin entity: {$entity}");
        }
        return self::$map[$entity];
    }

    private function audit(
        string $action, string $entityType, $entityId, ?string $entityName,
        ?array $old, ?array $new, Request $request
    ): void {
        AdminAuditLog::create([
            'user_id'     => $request->user()?->id,
            'action'      => $action,
            'entity_type' => 'lookup:' . $entityType,
            'entity_id'   => (string) $entityId,
            'entity_name' => $entityName,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $request->ip(),
        ]);
    }
}
