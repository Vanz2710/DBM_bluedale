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
use App\Models\Department;
use App\Models\DeptTask;
use App\Models\Forecast;
use App\Models\ForecastProduct;
use App\Models\ForecastResult;
use App\Models\ForecastType;
use App\Models\PerformanceTarget;
use App\Models\SocialMediaPackage;
use App\Models\SocialMediaReminder;
use App\Models\Task;
use App\Models\ToDo;
use App\Models\User;
use Illuminate\Database\QueryException;
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
        'departments'       => [Department::class,      'name'],
        'forecast-products' => [ForecastProduct::class, 'name'],
        'forecast-types'    => [ForecastType::class,    'name'],
        'forecast-results'  => [ForecastResult::class,  'name'],
        'packages'          => [SocialMediaPackage::class, 'name'],
    ];

    // Extra fields (beyond 'name') that a given entity's add/edit form may submit,
    // merged into the validation rules and passed straight through to create/update.
    private static array $extraFields = [
        'departments' => ['color' => 'nullable|string|max:20'],
    ];

    // Referencing model classes (resolved to table names via getTable() at call time)
    // instead of hardcoded table-name strings, so renaming a model's table can't
    // silently desync this map from the schema.
    private static array $usageMap = [
        'statuses'          => [[Contact::class, 'status_id'], [Forecast::class, 'contact_status_id']],
        'types'             => [[Contact::class, 'type_id'], [Forecast::class, 'contact_type_id']],
        'industries'        => [[Contact::class, 'industry_id']],
        'categories'        => [[Contact::class, 'category_id']],
        'areas'             => [[Contact::class, 'area_id']],
        'tasks'             => [[ToDo::class, 'task_id'], [PerformanceTarget::class, 'task_id']],
        'departments'       => [[DeptTask::class, 'department_id'], [User::class, 'department_id']],
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

        $rules     = array_merge(['name' => 'required|string|max:255'], self::$extraFields[$entity] ?? []);
        $validated = $request->validate($rules);

        if ($model::where('name', $validated['name'])->exists()) {
            throw ValidationException::withMessages(['name' => ['This entry already exists.']]);
        }

        if ($entity === 'departments') {
            $validated['code']  = $this->generateDepartmentCode($validated['name']);
            $validated['color'] = $validated['color'] ?? '#1d4ed8';
        }

        $item = $model::create($validated);

        Cache::forget('lookups_v2');
        // forecast-results rows also feed a separate long-lived cache in ForecastController::summary()
        Cache::forget('forecast_result_ids_v2');
        Cache::forget('forecast_no_result_id_v2');
        $this->audit('created', $entity, $item->id, $item->name, null, $validated, $request);

        return response()->json(['status' => 'success', 'data' => $item], 201);
    }

    public function update(Request $request, string $entity, string $id)
    {
        [$model] = $this->resolve($entity);
        $item = $model::findOrFail($id);

        $rules     = array_merge(['name' => 'required|string|max:255'], self::$extraFields[$entity] ?? []);
        $validated = $request->validate($rules);

        if ($model::where('name', $validated['name'])->where('id', '!=', $id)->exists()) {
            throw ValidationException::withMessages(['name' => ['This name already exists.']]);
        }

        $old = $item->only(array_keys($validated));
        $item->update($validated);

        Cache::forget('lookups_v2');
        // forecast-results rows also feed a separate long-lived cache in ForecastController::summary()
        Cache::forget('forecast_result_ids_v2');
        Cache::forget('forecast_no_result_id_v2');
        $this->audit('updated', $entity, $item->id, $item->name, $old, $validated, $request);

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
            // forecast-results rows also feed a separate long-lived cache in ForecastController::summary()
            Cache::forget('forecast_result_ids_v2');
            Cache::forget('forecast_no_result_id_v2');
            $this->audit('deleted', $entity, $item->id, $item->name, ['name' => $item->name], null, $request);

            $item->delete();
            return response()->json(['status' => 'success']);
        });
    }

    public function merge(Request $request, string $entity)
    {
        [$model] = $this->resolve($entity);
        $table = (new $model)->getTable();

        $validated = $request->validate([
            'keep_id'     => "nullable|integer|exists:{$table},id",
            'new_name'    => 'nullable|string|max:255',
            'merge_ids'   => 'required|array|min:1',
            'merge_ids.*' => "integer|exists:{$table},id",
        ]);

        if (empty($validated['keep_id']) && empty($validated['new_name'])) {
            throw ValidationException::withMessages([
                'keep_id' => ['Choose an item to keep, or provide a new name.'],
            ]);
        }

        return DB::transaction(function () use ($model, $entity, $validated, $request) {
            // If both are given, new_name wins — mint a fresh row rather than reuse an existing one.
            if (!empty($validated['new_name'])) {
                if ($model::where('name', $validated['new_name'])->exists()) {
                    throw ValidationException::withMessages(['new_name' => ['This entry already exists.']]);
                }
                $createData = ['name' => $validated['new_name']];
                if ($entity === 'departments') {
                    $createData['code'] = $this->generateDepartmentCode($validated['new_name']);
                }
                $keep = $model::create($createData);
            } else {
                $keep = $model::lockForUpdate()->findOrFail($validated['keep_id']);
            }

            $mergeIds = array_values(array_unique(array_filter(
                $validated['merge_ids'],
                fn ($id) => (int) $id !== (int) $keep->id
            )));

            if (empty($mergeIds)) {
                return response()->json(['status' => 'success', 'merged' => 0, 'data' => $keep]);
            }

            $mergedItems = $model::whereIn('id', $mergeIds)->lockForUpdate()->get(['id', 'name']);
            $reassignments = [];

            try {
                foreach (self::$usageMap[$entity] as $src) {
                    [$childModel, $col] = $src;
                    $childTable = (new $childModel)->getTable();
                    $parentCol  = $src[2] ?? 'id';

                    $sourceValues = $mergedItems->pluck($parentCol)->all();
                    if (empty($sourceValues)) {
                        continue;
                    }

                    // Snapshot which child rows currently hold which source value *before*
                    // the reassignment below collapses that information — this is what lets
                    // a later revert move exactly these rows back instead of guessing.
                    $moves = DB::table($childTable)
                        ->whereIn($col, $sourceValues)
                        ->select('id', $col)
                        ->get()
                        ->groupBy($col)
                        ->map(fn ($rows) => $rows->pluck('id')->all());

                    if ($moves->isNotEmpty()) {
                        $reassignments[] = [
                            'child_table' => $childTable,
                            'column'      => $col,
                            'parent_col'  => $parentCol,
                            'keep_value'  => $keep->{$parentCol},
                            'moves'       => $moves,
                        ];
                    }

                    DB::table($childTable)->whereIn($col, $sourceValues)->update([$col => $keep->{$parentCol}]);
                }
            } catch (QueryException $e) {
                throw ValidationException::withMessages([
                    'merge_ids' => ['Merge failed because it would create a duplicate record (for example, a KPI target for the same user already existing on both items). Resolve the conflict and try again.'],
                ]);
            }

            $model::whereIn('id', $mergeIds)->delete();

            Cache::forget('lookups_v2');
            // forecast-results rows also feed a separate long-lived cache in ForecastController::summary()
            Cache::forget('forecast_result_ids_v2');
            Cache::forget('forecast_no_result_id_v2');

            $mergedItemsPayload = $mergedItems->map(fn ($i) => ['id' => $i->id, 'name' => $i->name])->all();

            $this->audit(
                'merged',
                $entity,
                $keep->id,
                $keep->name,
                ['merged_items' => $mergedItemsPayload],
                ['kept_item' => ['id' => $keep->id, 'name' => $keep->name]],
                $request,
                ['merged_items' => $mergedItemsPayload, 'reassignments' => $reassignments]
            );

            return response()->json(['status' => 'success', 'merged' => count($mergeIds), 'data' => $keep]);
        });
    }

    // departments.code is a required, unique column the frontend never exposes for
    // editing (unused anywhere in the UI) — derive one from the name so the generic
    // create path never hits a "no default value" DB error.
    private function generateDepartmentCode(string $name): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
        $base = $base !== '' ? substr($base, 0, 10) : 'DEPT';

        $code   = $base;
        $suffix = 1;
        while (Department::where('code', $code)->exists()) {
            $suffixStr = (string) $suffix;
            $code = substr($base, 0, 10 - strlen($suffixStr)) . $suffixStr;
            $suffix++;
        }

        return $code;
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
        ?array $old, ?array $new, Request $request, ?array $revertData = null
    ): void {
        AdminAuditLog::create([
            'user_id'     => $request->user()?->id,
            'action'      => $action,
            'entity_type' => 'lookup:' . $entityType,
            'entity_id'   => (string) $entityId,
            'entity_name' => $entityName,
            'old_values'  => $old,
            'new_values'  => $new,
            'revert_data' => $revertData,
            'ip_address'  => $request->ip(),
        ]);
    }

    public function revertMerge(Request $request, string $auditLogId)
    {
        $log = AdminAuditLog::where('action', 'merged')->findOrFail($auditLogId);

        if ($log->reverted_at) {
            throw ValidationException::withMessages([
                'id' => ['This merge has already been reverted.'],
            ]);
        }

        $revertData = $log->revert_data;
        if (empty($revertData['merged_items'])) {
            throw ValidationException::withMessages([
                'id' => ['This merge was made before revert support was added and cannot be automatically reverted.'],
            ]);
        }

        $entity = str_replace('lookup:', '', $log->entity_type);
        [$model] = $this->resolve($entity);

        return DB::transaction(function () use ($model, $entity, $log, $revertData, $request) {
            // Keyed two ways because `moves` (captured in merge()) is grouped by whatever
            // the child FK actually stores — the lookup row's id for every entity except
            // 'packages', which stores the name string instead (see $usageMap).
            $idMapById   = [];
            $idMapByName = [];
            foreach ($revertData['merged_items'] as $item) {
                if ($model::where('name', $item['name'])->exists()) {
                    throw ValidationException::withMessages([
                        'id' => ["Cannot revert: an entry named \"{$item['name']}\" already exists again."],
                    ]);
                }
                $createData = ['name' => $item['name']];
                if ($entity === 'departments') {
                    $createData['code'] = $this->generateDepartmentCode($item['name']);
                }
                $created = $model::create($createData);
                $idMapById[$item['id']]     = $created;
                $idMapByName[$item['name']] = $created;
            }

            $recreatedCount = 0;
            foreach ($revertData['reassignments'] ?? [] as $r) {
                $keepValue = $r['keep_value'];
                $idMap     = $r['parent_col'] === 'name' ? $idMapByName : $idMapById;
                foreach ($r['moves'] as $originalValue => $ids) {
                    $newRow = $idMap[$originalValue] ?? null;
                    if (!$newRow || empty($ids)) {
                        continue;
                    }
                    $newValue = $r['parent_col'] === 'name' ? $newRow->name : $newRow->id;
                    $recreatedCount += DB::table($r['child_table'])
                        ->whereIn('id', $ids)
                        ->where($r['column'], $keepValue)
                        ->update([$r['column'] => $newValue]);
                }
            }

            Cache::forget('lookups_v2');
            // forecast-results rows also feed a separate long-lived cache in ForecastController::summary()
            Cache::forget('forecast_result_ids_v2');
            Cache::forget('forecast_no_result_id_v2');

            $log->update(['reverted_at' => now(), 'reverted_by' => $request->user()?->id]);

            $this->audit(
                'reverted',
                $entity,
                $log->entity_id,
                $log->entity_name,
                null,
                [
                    'reverted_log_id' => $log->id,
                    'recreated'       => collect($idMapById)->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values()->all(),
                ],
                $request
            );

            return response()->json([
                'status'         => 'success',
                'recreated'      => count($idMapById),
                'records_moved'  => $recreatedCount,
            ]);
        });
    }
}
