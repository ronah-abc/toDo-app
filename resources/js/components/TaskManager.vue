<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    Circle,
    Pencil,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { index as taskIndex } from '@/routes/tasks';
import type { TaskFilterValues, TaskItem, TaskPriority, Team } from '@/types';

const props = withDefaults(
    defineProps<{
        currentTeam: Team;
        tasks: TaskItem[];
        mode?: 'dashboard' | 'index';
        filters?: TaskFilterValues;
    }>(),
    {
        mode: 'dashboard',
        filters: () => ({
            search: '',
            status: 'all',
            priority: 'all',
            due: 'all',
        }),
    },
);

const taskList = ref<HTMLElement | null>(null);
const editingTaskId = ref<number | null>(null);
const deletingTaskId = ref<number | null>(null);
const taskDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const taskToDelete = ref<TaskItem | null>(null);
const activeFilter = ref<TaskFilterValues['status']>(props.filters.status);
const search = ref(props.filters.search);
const priorityFilter = ref<TaskFilterValues['priority']>(
    props.filters.priority,
);
const dueFilter = ref<TaskFilterValues['due']>(props.filters.due);
const filterTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const form = useForm({
    title: '',
    description: '',
    priority: 'medium' as TaskPriority,
    due_date: '',
});

const activeCount = computed(
    () => props.tasks.filter((task) => !task.completedAt).length,
);
const completedCount = computed(() => props.tasks.length - activeCount.value);
const filteredTasks = computed(() => {
    if (props.mode === 'index') {
        return props.tasks;
    }

    return props.tasks.filter((task) => {
        if (activeFilter.value === 'active') {
            return !task.completedAt;
        }

        if (activeFilter.value === 'completed') {
            return Boolean(task.completedAt);
        }

        return true;
    });
});
const emptyMessage = computed(() => {
    if (
        props.mode === 'index' &&
        (search.value ||
            activeFilter.value !== 'all' ||
            priorityFilter.value !== 'all' ||
            dueFilter.value !== 'all')
    ) {
        return 'No tasks match these filters';
    }

    if (activeFilter.value === 'completed') {
        return 'No completed tasks yet';
    }

    if (activeFilter.value === 'active') {
        return 'You are all caught up';
    }

    return 'Your task list is empty';
});

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        activeFilter.value = filters.status;
        priorityFilter.value = filters.priority;
        dueFilter.value = filters.due;
    },
    { deep: true },
);

watch(search, () => {
    if (props.mode !== 'index') {
        return;
    }

    if (filterTimer.value) {
        clearTimeout(filterTimer.value);
    }

    filterTimer.value = setTimeout(applyFilters, 300);
});

onBeforeUnmount(() => {
    if (filterTimer.value) {
        clearTimeout(filterTimer.value);
    }
});

function applyFilters() {
    if (filterTimer.value) {
        clearTimeout(filterTimer.value);
        filterTimer.value = null;
    }

    router.get(
        taskIndex(props.currentTeam.slug).url,
        {
            search: search.value || undefined,
            status: activeFilter.value,
            priority: priorityFilter.value,
            due: dueFilter.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function submitTask() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            taskDialogOpen.value = false;
            form.reset();
            editingTaskId.value = null;
        },
    };

    if (editingTaskId.value !== null) {
        form.put(
            TaskController.update({
                current_team: props.currentTeam.slug,
                task: editingTaskId.value,
            }).url,
            options,
        );

        return;
    }

    form.post(TaskController.store(props.currentTeam.slug).url, options);
}

function editTask(task: TaskItem) {
    editingTaskId.value = task.id;
    form.title = task.title;
    form.description = task.description ?? '';
    form.priority = task.priority;
    form.due_date = task.dueDate ?? '';
    taskDialogOpen.value = true;
}

function openCreateTask() {
    form.reset();
    form.clearErrors();
    editingTaskId.value = null;
    taskDialogOpen.value = true;
}

function closeTaskDialog() {
    taskDialogOpen.value = false;
    form.reset();
    form.clearErrors();
    editingTaskId.value = null;
}

function toggleCompletion(task: TaskItem) {
    router.patch(
        TaskController.toggleCompletion({
            current_team: props.currentTeam.slug,
            task: task.id,
        }).url,
        {},
        { preserveScroll: true },
    );
}

function deleteTask(task: TaskItem) {
    taskToDelete.value = task;
    deleteDialogOpen.value = true;
}

function confirmDeleteTask() {
    if (!taskToDelete.value) {
        return;
    }

    const task = taskToDelete.value;
    deletingTaskId.value = task.id;
    router.delete(
        TaskController.destroy({
            current_team: props.currentTeam.slug,
            task: task.id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                taskToDelete.value = null;
            },
            onFinish: () => (deletingTaskId.value = null),
        },
    );
}

function focusCreate() {
    openCreateTask();
    void nextTick(() => {
        document.getElementById('task-title')?.focus({ preventScroll: true });
    });
}

function showActiveTasks() {
    activeFilter.value = 'active';
    taskList.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function formatDueDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
}

function priorityClass(priority: TaskPriority): string {
    return {
        low: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        medium: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        high: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
    }[priority];
}

defineExpose({ focusCreate, showActiveTasks });
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex justify-end">
            <Button type="button" @click="openCreateTask">
                <Plus />
                Add task
            </Button>
        </div>

        <Dialog v-model:open="taskDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editingTaskId === null ? 'Add a task' : 'Edit task'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Add a title, then optionally set a priority and due
                        date.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitTask">
                    <div class="grid gap-2">
                        <label for="task-title" class="text-sm font-medium">
                            Task
                        </label>
                        <Input
                            id="task-title"
                            v-model="form.title"
                            maxlength="255"
                            placeholder="What needs to get done?"
                            required
                            :aria-invalid="Boolean(form.errors.title)"
                        />
                        <p
                            v-if="form.errors.title"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <label
                            for="task-description"
                            class="text-sm font-medium"
                        >
                            Notes
                            <span class="text-muted-foreground"
                                >(optional)</span
                            >
                        </label>
                        <textarea
                            id="task-description"
                            v-model="form.description"
                            maxlength="2000"
                            rows="2"
                            placeholder="Add a few details"
                            class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <p
                            v-if="form.errors.description"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <label
                                for="task-priority"
                                class="text-sm font-medium"
                            >
                                Priority
                            </label>
                            <select
                                id="task-priority"
                                v-model="form.priority"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <label
                                for="task-due-date"
                                class="text-sm font-medium"
                            >
                                Due date
                                <span class="text-muted-foreground"
                                    >(optional)</span
                                >
                            </label>
                            <Input
                                id="task-due-date"
                                v-model="form.due_date"
                                type="date"
                                :aria-invalid="Boolean(form.errors.due_date)"
                            />
                            <p
                                v-if="form.errors.due_date"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.due_date }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeTaskDialog"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{
                                editingTaskId === null
                                    ? 'Add task'
                                    : 'Save changes'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete this task?</DialogTitle>
                    <DialogDescription>
                        This action cannot be undone. The task
                        <strong v-if="taskToDelete">
                            “{{ taskToDelete.title }}”
                        </strong>
                        will be permanently deleted.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="deleteDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deletingTaskId !== null"
                        @click="confirmDeleteTask"
                    >
                        Delete task
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div ref="taskList">
            <Card>
                <CardHeader
                    class="gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-1">
                        <CardTitle>
                            {{ mode === 'index' ? 'All tasks' : 'Your list' }}
                        </CardTitle>
                        <CardDescription>
                            {{ activeCount }} active ·
                            {{ completedCount }} completed
                        </CardDescription>
                    </div>
                    <nav
                        v-if="mode === 'dashboard'"
                        aria-label="Filter tasks"
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            v-for="filter in [
                                'all',
                                'active',
                                'completed',
                            ] as const"
                            :key="filter"
                            type="button"
                            size="sm"
                            :variant="
                                activeFilter === filter ? 'default' : 'outline'
                            "
                            :aria-pressed="activeFilter === filter"
                            @click="activeFilter = filter"
                        >
                            {{ filter[0]!.toUpperCase() + filter.slice(1) }}
                        </Button>
                    </nav>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="mode === 'index'"
                        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div class="relative sm:col-span-2 lg:col-span-1">
                            <Search
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="search"
                                type="search"
                                aria-label="Search tasks"
                                placeholder="Search tasks..."
                                class="pl-9"
                            />
                        </div>
                        <select
                            v-model="activeFilter"
                            aria-label="Filter by status"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            @change="applyFilters"
                        >
                            <option value="all">All statuses</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                        </select>
                        <select
                            v-model="priorityFilter"
                            aria-label="Filter by priority"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            @change="applyFilters"
                        >
                            <option value="all">All priorities</option>
                            <option value="high">High priority</option>
                            <option value="medium">Medium priority</option>
                            <option value="low">Low priority</option>
                        </select>
                        <select
                            v-model="dueFilter"
                            aria-label="Filter by due date"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            @change="applyFilters"
                        >
                            <option value="all">Any due date</option>
                            <option value="overdue">Overdue</option>
                            <option value="today">Due today</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="none">No due date</option>
                        </select>
                    </div>

                    <ul v-if="filteredTasks.length" class="divide-y">
                        <li
                            v-for="task in filteredTasks"
                            :key="task.id"
                            class="flex gap-3 py-4 first:pt-0 last:pb-0"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="mt-0.5 shrink-0"
                                :aria-label="
                                    task.completedAt
                                        ? `Reopen ${task.title}`
                                        : `Complete ${task.title}`
                                "
                                @click="toggleCompletion(task)"
                            >
                                <Check v-if="task.completedAt" class="size-4" />
                                <Circle v-else class="size-4" />
                            </Button>
                            <div class="min-w-0 flex-1 space-y-1">
                                <p
                                    class="font-medium break-words"
                                    :class="
                                        task.completedAt &&
                                        'text-muted-foreground line-through'
                                    "
                                >
                                    {{ task.title }}
                                </p>
                                <p
                                    v-if="task.description"
                                    class="text-sm break-words whitespace-pre-wrap text-muted-foreground"
                                >
                                    {{ task.description }}
                                </p>
                                <div
                                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
                                >
                                    <span
                                        class="rounded-full px-2.5 py-1 font-semibold capitalize"
                                        :class="priorityClass(task.priority)"
                                    >
                                        {{ task.priority }} priority
                                    </span>
                                    <span
                                        v-if="task.dueDate"
                                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-muted-foreground"
                                    >
                                        <CalendarDays class="size-3.5" />
                                        {{ formatDueDate(task.dueDate) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-start gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Edit ${task.title}`"
                                    @click="editTask(task)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="deletingTaskId === task.id"
                                    :aria-label="`Delete ${task.title}`"
                                    @click="deleteTask(task)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </li>
                    </ul>
                    <div
                        v-else
                        class="rounded-lg border border-dashed px-6 py-10 text-center"
                    >
                        <p class="font-medium">
                            {{ emptyMessage }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                mode === 'index'
                                    ? 'Try changing or clearing your filters.'
                                    : 'Add a task above to get started.'
                            }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
