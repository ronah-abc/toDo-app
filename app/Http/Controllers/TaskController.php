<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaskRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'completed'])],
            'priority' => ['nullable', Rule::in(['all', 'low', 'medium', 'high'])],
            'due' => ['nullable', Rule::in(['all', 'overdue', 'today', 'upcoming', 'none'])],
        ]);
        $filters = [
            'search' => $validated['search'] ?? '',
            'status' => $validated['status'] ?? 'all',
            'priority' => $validated['priority'] ?? 'all',
            'due' => $validated['due'] ?? 'all',
        ];

        $tasks = $request->user()->tasks()
            ->when($filters['search'], fn ($query, $search) => $query
                ->where(fn ($query) => $query
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')))
            ->when($filters['status'] === 'active', fn ($query) => $query->whereNull('completed_at'))
            ->when($filters['status'] === 'completed', fn ($query) => $query->whereNotNull('completed_at'))
            ->when($filters['priority'] !== 'all', fn ($query) => $query->where('priority', $filters['priority']))
            ->when($filters['due'] === 'overdue', fn ($query) => $query->whereNull('completed_at')->whereDate('due_date', '<', today()))
            ->when($filters['due'] === 'today', fn ($query) => $query->whereDate('due_date', today()))
            ->when($filters['due'] === 'upcoming', fn ($query) => $query->whereDate('due_date', '>', today()))
            ->when($filters['due'] === 'none', fn ($query) => $query->whereNull('due_date'))
            ->latest()
            ->get()
            ->map(fn (Task $task) => $task->toTaskData());

        return Inertia::render('tasks/Index', [
            'tasks' => $tasks,
            'filters' => $filters,
        ]);
    }

    public function store(SaveTaskRequest $request, string $currentTeam): RedirectResponse
    {
        $request->user()->tasks()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task created.')]);

        return back();
    }

    public function update(SaveTaskRequest $request, string $currentTeam, Task $task): RedirectResponse
    {
        $this->ensureTaskOwner($request, $task);

        $task->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task updated.')]);

        return back();
    }

    public function toggleCompletion(Request $request, string $currentTeam, Task $task): RedirectResponse
    {
        $this->ensureTaskOwner($request, $task);

        $task->update([
            'completed_at' => $task->completed_at ? null : now(),
        ]);

        return back();
    }

    public function destroy(Request $request, string $currentTeam, Task $task): RedirectResponse
    {
        $this->ensureTaskOwner($request, $task);

        $task->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task deleted.')]);

        return back();
    }

    private function ensureTaskOwner(Request $request, Task $task): void
    {
        abort_unless($task->user_id === $request->user()->id, 404);
    }
}
