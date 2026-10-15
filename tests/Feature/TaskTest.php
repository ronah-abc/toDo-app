<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_index_returns_only_tasks_owned_by_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $task = $user->tasks()->create([
            'title' => 'Prepare release',
            'priority' => 'high',
        ]);
        $otherUser->tasks()->create([
            'title' => 'Private release task',
            'priority' => 'high',
        ]);

        $this->actingAs($user)
            ->get(route('tasks.index', ['current_team' => $user->currentTeam->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Index')
                ->has('tasks', 1)
                ->where('tasks.0.id', $task->id)
                ->where('tasks.0.title', 'Prepare release')
                ->where('filters.search', '')
                ->where('filters.status', 'all')
                ->where('filters.priority', 'all')
                ->where('filters.due', 'all'),
            );
    }

    public function test_task_index_filters_tasks_by_search_status_priority_and_due_date(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));

        $user = User::factory()->create();
        $matchingTask = $user->tasks()->create([
            'title' => 'Release notes',
            'description' => 'Publish the latest release details',
            'priority' => 'high',
            'due_date' => '2026-10-14',
        ]);
        $user->tasks()->create([
            'title' => 'Release follow-up',
            'description' => 'Already complete',
            'priority' => 'high',
            'due_date' => '2026-10-14',
            'completed_at' => now(),
        ]);
        $user->tasks()->create([
            'title' => 'Buy groceries',
            'priority' => 'low',
            'due_date' => '2026-10-14',
        ]);

        $this->actingAs($user)
            ->get(route('tasks.index', [
                'current_team' => $user->currentTeam->slug,
                'search' => 'release',
                'status' => 'active',
                'priority' => 'high',
                'due' => 'overdue',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Index')
                ->has('tasks', 1)
                ->where('tasks.0.id', $matchingTask->id)
                ->where('filters.search', 'release')
                ->where('filters.status', 'active')
                ->where('filters.priority', 'high')
                ->where('filters.due', 'overdue'),
            );
    }

    public function test_task_index_rejects_invalid_filter_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tasks.index', [
                'current_team' => $user->currentTeam->slug,
                'status' => 'archived',
                'priority' => 'urgent',
                'due' => 'someday',
            ]))
            ->assertSessionHasErrors(['status', 'priority', 'due']);
    }

    public function test_dashboard_shows_only_the_authenticated_users_tasks(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $task = $user->tasks()->create([
            'title' => 'Write tests',
            'priority' => 'high',
            'due_date' => '2026-10-20',
        ]);
        $otherUser->tasks()->create([
            'title' => 'Private task',
            'priority' => 'low',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('tasks', 1)
                ->where('tasks.0.id', $task->id)
                ->where('tasks.0.title', 'Write tests')
                ->where('tasks.0.priority', 'high')
                ->where('tasks.0.dueDate', '2026-10-20')
                ->where('tasks.0.completedAt', null),
            );
    }

    public function test_authenticated_users_can_create_tasks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tasks.store', ['current_team' => $user->currentTeam->slug]), [
                'title' => 'Plan the week',
                'description' => 'Review upcoming work',
                'priority' => 'high',
                'due_date' => '2026-10-20',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Plan the week',
            'description' => 'Review upcoming work',
            'priority' => 'high',
            'due_date' => '2026-10-20 00:00:00',
            'completed_at' => null,
        ]);
    }

    public function test_task_creation_validates_required_fields_and_priority(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard', ['current_team' => $user->currentTeam->slug]))
            ->post(route('tasks.store', ['current_team' => $user->currentTeam->slug]), [
                'title' => '',
                'priority' => 'urgent',
            ])
            ->assertSessionHasErrors(['title', 'priority']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_task_owner_can_update_and_delete_a_task(): void
    {
        $user = User::factory()->create();
        $task = $user->tasks()->create([
            'title' => 'Draft notes',
            'priority' => 'low',
        ]);

        $this->actingAs($user)
            ->put(route('tasks.update', [
                'current_team' => $user->currentTeam->slug,
                'task' => $task->id,
            ]), [
                'title' => 'Finalize notes',
                'description' => 'Share with the team',
                'priority' => 'medium',
                'due_date' => '2026-10-21',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Finalize notes',
            'description' => 'Share with the team',
            'priority' => 'medium',
            'due_date' => '2026-10-21 00:00:00',
        ]);

        $this->delete(route('tasks.destroy', [
            'current_team' => $user->currentTeam->slug,
            'task' => $task->id,
        ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_task_owner_can_complete_and_reopen_a_task(): void
    {
        $user = User::factory()->create();
        $task = $user->tasks()->create([
            'title' => 'Finish report',
            'priority' => 'medium',
        ]);

        $this->actingAs($user)
            ->patch(route('tasks.toggle-completion', [
                'current_team' => $user->currentTeam->slug,
                'task' => $task->id,
            ]))
            ->assertRedirect();

        $this->assertNotNull($task->fresh()->completed_at);

        $this->patch(route('tasks.toggle-completion', [
            'current_team' => $user->currentTeam->slug,
            'task' => $task->id,
        ]))
            ->assertRedirect();

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_users_cannot_change_or_delete_another_users_task(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $task = $otherUser->tasks()->create([
            'title' => 'Private task',
            'priority' => 'medium',
        ]);

        $this->actingAs($user)
            ->put(route('tasks.update', [
                'current_team' => $user->currentTeam->slug,
                'task' => $task->id,
            ]), [
                'title' => 'Changed task',
                'priority' => 'high',
            ])
            ->assertNotFound();

        $this->patch(route('tasks.toggle-completion', [
            'current_team' => $user->currentTeam->slug,
            'task' => $task->id,
        ]))
            ->assertNotFound();

        $this->delete(route('tasks.destroy', [
            'current_team' => $user->currentTeam->slug,
            'task' => $task->id,
        ]))
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'user_id' => $otherUser->id,
            'title' => 'Private task',
            'completed_at' => null,
        ]);
    }
}
