<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Check, ListTodo, Plus, Timer } from '@lucide/vue';
import { computed, ref } from 'vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import TaskManager from '@/components/TaskManager.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as taskIndex } from '@/routes/tasks';
import type { DashboardInvitation, TaskItem, Team } from '@/types';

const props = defineProps<{
    currentTeam: Team;
    tasks: TaskItem[];
    pendingInvitations?: DashboardInvitation[];
}>();

const taskManager = ref<{
    focusCreate: () => void;
    showActiveTasks: () => void;
} | null>(null);

const activeCount = computed(
    () => props.tasks.filter((task) => !task.completedAt).length,
);
const completedCount = computed(() => props.tasks.length - activeCount.value);

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: layoutProps.currentTeam
                    ? dashboard(layoutProps.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <Head title="My Tasks" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <main
        class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-8"
    >
        <header
            class="relative overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-6 shadow-sm md:p-8 dark:border-emerald-900 dark:from-emerald-950/50 dark:via-card dark:to-teal-950/40"
        >
            <div
                aria-hidden="true"
                class="absolute -top-16 -right-12 size-48 rounded-full bg-emerald-200/40 blur-3xl dark:bg-emerald-500/10"
            />
            <div class="relative flex items-center gap-4">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-900/15 dark:bg-emerald-500 dark:text-emerald-950"
                >
                    <Check class="size-6" />
                </div>
                <div class="space-y-1">
                    <p
                        class="text-sm font-semibold tracking-wide text-emerald-800 dark:text-emerald-300"
                    >
                        {{ currentTeam.name }}
                    </p>
                    <h1 class="text-3xl font-semibold tracking-tight">
                        My tasks
                    </h1>
                    <p class="text-muted-foreground">
                        Keep track of what needs to get done.
                    </p>
                </div>
            </div>
        </header>

        <section aria-label="Quick actions" class="grid gap-3 sm:grid-cols-3">
            <Button
                class="h-auto justify-start gap-3 px-4 py-4 text-left"
                @click="taskManager?.focusCreate()"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-primary-foreground/15"
                >
                    <Plus class="size-5" />
                </span>
                <span class="grid">
                    <span>Add a task</span>
                    <span class="text-xs font-normal opacity-80">
                        Capture something new
                    </span>
                </span>
            </Button>

            <Link
                :href="taskIndex(currentTeam.slug).url"
                class="flex h-auto items-center gap-3 rounded-lg border border-border bg-card px-4 py-3 text-sm font-medium text-card-foreground shadow-sm transition-colors outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-[3px] focus-visible:ring-ring"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                >
                    <ListTodo class="size-5" />
                </span>
                <span class="grid text-left">
                    <span>View all tasks</span>
                    <span class="text-xs font-normal text-muted-foreground">
                        {{ props.tasks.length }} total
                    </span>
                </span>
                <ArrowRight class="ml-auto size-4 text-muted-foreground" />
            </Link>

            <Button
                variant="outline"
                class="h-auto justify-start gap-3 px-4 py-4 text-left"
                @click="taskManager?.showActiveTasks()"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                >
                    <Timer class="size-5" />
                </span>
                <span class="grid">
                    <span>Active tasks</span>
                    <span class="text-xs font-normal text-muted-foreground">
                        {{ activeCount }} to do · {{ completedCount }} done
                    </span>
                </span>
            </Button>
        </section>
    </main>
</template>
