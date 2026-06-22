<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\ContactCategory;
use App\Models\ContactIndustry;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\ForecastProduct;
use App\Models\ForecastResult;
use App\Models\ForecastType;
use App\Models\Task;
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
        'tasks'             => [Task::class,            'name'],
        'forecast-products' => [ForecastProduct::class, 'name'],
        'forecast-types'    => [ForecastType::class,    'name'],
        'forecast-results'  => [ForecastResult::class,  'name'],
    ];

    private static array $usageMap = [
        'statuses'          => [['contacts', 'status_id']],
        'types'             => [['contacts', 'type_id']],
        'industries'        => [['contacts', 'industry_id']],
        'categories'        => [['contacts', 'category_id']],
        'tasks'             => [['to_dos', 'task_id'], ['performance_targets', 'task_id']],
        'forecast-products' => [['forecasts', 'product_id']],
        'forecast-types'    => [['forecasts', 'forecast_type_id']],
        'forecast-results'  => [['forecasts', 'result_id']],
    ];

    public function index(string $entity)
    {
        [$model] = $this->resolve($entity);
        $modelTable = (new $model)->getTable();
        $sources    = self::$usageMap[$entity];

        $subParts  = array_map(
            fn($src) => "(SELECT COUNT(*) FROM {$src[0]} WHERE {$src[1]} = {$modelTable}.id)",
            $sources
        );
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

        Cache::forget('lookups');
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

        Cache::forget('lookups');
        $this->audit('updated', $entity, $item->id, $item->name, $old, ['name' => $item->name], $request);

        return response()->json(['status' => 'success', 'data' => $item]);
    }

    public function destroy(Request $request, string $entity, string $id)
    {
        [$model] = $this->resolve($entity);

        return DB::transaction(function () use ($model, $entity, $id, $request) {
            $item = $model::lockForUpdate()->findOrFail($id);

            $count = 0;
            foreach (self::$usageMap[$entity] as [$table, $col]) {
                $count += DB::table($table)->where($col, $id)->count();
            }

            if ($count > 0) {
                return response()->json([
                    'message' => "Cannot delete \"{$item->name}\" — it is referenced by {$count} record(s). Remove those references first.",
                ], 409);
            }

            Cache::forget('lookups');
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
