<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactArea;
use App\Models\ContactCategory;
use App\Models\ContactIndustry;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private static array $map = [
        'statuses'   => [ContactStatus::class,  'name'],
        'types'      => [ContactType::class,     'name'],
        'industries' => [ContactIndustry::class, 'name'],
        'categories' => [ContactCategory::class, 'name'],
        'areas'      => [ContactArea::class,     'name'],
        'tasks'      => [Task::class,            'name'],
    ];

    public function index(string $entity)
    {
        [$model] = $this->resolve($entity);
        return response()->json(['data' => $model::orderBy('name')->get()]);
    }

    public function store(Request $request, string $entity)
    {
        [$model] = $this->resolve($entity);

        $validated = $request->validate(['name' => 'required|string|max:255']);

        if ($model::where('name', $validated['name'])->exists()) {
            throw ValidationException::withMessages(['name' => ['This entry already exists.']]);
        }

        $item = $model::create($validated);
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

        $item->update($validated);
        return response()->json(['status' => 'success', 'data' => $item]);
    }

    public function destroy(string $entity, string $id)
    {
        [$model] = $this->resolve($entity);
        $model::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }

    private function resolve(string $entity): array
    {
        if (!array_key_exists($entity, self::$map)) {
            abort(404, "Unknown admin entity: {$entity}");
        }
        return self::$map[$entity];
    }
}
