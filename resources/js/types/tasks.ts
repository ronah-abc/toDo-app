export type TaskPriority = 'low' | 'medium' | 'high';
export type TaskStatus = 'all' | 'active' | 'completed';
export type TaskDueFilter = 'all' | 'overdue' | 'today' | 'upcoming' | 'none';

export type TaskFilterValues = {
    search: string;
    status: TaskStatus;
    priority: 'all' | TaskPriority;
    due: TaskDueFilter;
};

export type TaskItem = {
    id: number;
    title: string;
    description: string | null;
    priority: TaskPriority;
    dueDate: string | null;
    completedAt: string | null;
};
