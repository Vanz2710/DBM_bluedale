<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ToDo;
use Illuminate\Http\Request;

class ToDoController extends Controller
{
    public function index(string $contactId)
    {
        Contact::findOrFail($contactId);
        $todos = ToDo::with(['task', 'user'])
            ->where('contact_id', $contactId)
            ->orderByDesc('todo_date')
            ->get();
        return response()->json(['data' => $todos]);
    }

    public function store(Request $request, string $contactId)
    {
        Contact::findOrFail($contactId);

        $validated = $request->validate([
            'user_id'      => 'nullable|exists:users,id',
            'task_id'      => 'nullable|exists:tasks,id',
            'todo_date'    => 'required|date',
            'date_created' => 'nullable|date',
            'todo_remark'  => 'nullable|string',
        ]);

        if (empty($validated['date_created'])) {
            $validated['date_created'] = now()->toDateString();
        }

        $todo = ToDo::create(array_merge($validated, ['contact_id' => $contactId]));

        return response()->json(['status' => 'success', 'data' => $todo->load(['task', 'user'])], 201);
    }

    public function update(Request $request, string $contactId, string $id)
    {
        $todo = ToDo::where('contact_id', $contactId)->findOrFail($id);

        $validated = $request->validate([
            'user_id'      => 'nullable|exists:users,id',
            'task_id'      => 'nullable|exists:tasks,id',
            'todo_date'    => 'required|date',
            'date_created' => 'nullable|date',
            'todo_remark'  => 'nullable|string',
        ]);

        $todo->update($validated);
        return response()->json(['status' => 'success', 'data' => $todo->fresh(['task', 'user'])]);
    }

    public function destroy(string $contactId, string $id)
    {
        ToDo::where('contact_id', $contactId)->findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }
}
