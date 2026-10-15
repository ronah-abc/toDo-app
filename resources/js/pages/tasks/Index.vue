<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TaskManager from '@/components/TaskManager.vue';
import { index as taskIndex } from '@/routes/tasks';
import type { TaskFilterValues, TaskItem, Team } from '@/types';

defineProps<{
    currentTeam: Team;
    tasks: TaskItem[];
    filters: TaskFilterValues;
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Tasks',
                href: props.currentTeam
                    ? taskIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <Head title="All Tasks" />

    <main
        class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-8"
    >
        <header>
            <p
                class="text-sm font-medium text-emerald-700 dark:text-emerald-300"
            >
                {{ currentTeam.name }}
            </p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">
                All tasks
            </h1>
            <p class="mt-2 text-muted-foreground">
                Search, sort, and manage everything on your list.
            </p>
        </header>

        <TaskManager
            mode="index"
            :current-team="currentTeam"
            :tasks="tasks"
            :filters="filters"
        />
    </main>
</template>
