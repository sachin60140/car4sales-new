<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ActivityLog, BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps<{
    logs: Paginated<ActivityLog>;
    filters: { log: string | null; event: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Audit Logs', href: '/admin/audit/activity' },
];

const filters = reactive({
    log: props.filters.log ?? '',
    event: props.filters.event ?? '',
});

watch(filters, () => {
    const query: Record<string, string> = {};
    if (filters.log) query.log = filters.log;
    if (filters.event) query.event = filters.event;
    router.get('/admin/audit/activity', query, { preserveState: true, replace: true });
});

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head title="Activity Log" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Activity Log</h1>
                    <p class="text-sm text-muted-foreground">Record-change history across all modules.</p>
                </div>
                <div class="flex gap-2 text-sm">
                    <Link href="/admin/audit/activity" class="rounded-md bg-muted px-3 py-1.5 font-medium">Activity</Link>
                    <Link href="/admin/audit/logins" class="rounded-md px-3 py-1.5 text-muted-foreground hover:bg-muted">Logins</Link>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <select v-model="filters.log" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All modules</option>
                    <option value="branch">Branch</option>
                    <option value="department">Department</option>
                    <option value="team">Team</option>
                    <option value="user">User</option>
                    <option value="employee">Employee</option>
                </select>
                <select v-model="filters.event" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All events</option>
                    <option value="created">Created</option>
                    <option value="updated">Updated</option>
                    <option value="deleted">Deleted</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">When</th>
                            <th class="px-4 py-3 font-medium">Module</th>
                            <th class="px-4 py-3 font-medium">Event</th>
                            <th class="px-4 py-3 font-medium">Subject</th>
                            <th class="px-4 py-3 font-medium">By</th>
                            <th class="px-4 py-3 font-medium">Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No activity recorded yet.</td>
                        </tr>
                        <tr v-for="log in logs.data" :key="log.id" class="border-b align-top last:border-0 hover:bg-muted/30">
                            <td class="whitespace-nowrap px-4 py-3">{{ formatDate(log.created_at) }}</td>
                            <td class="px-4 py-3 capitalize">{{ log.log_name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{ log.event ?? log.description }}</span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ log.subject_type?.split('\\').pop() }}#{{ log.subject_id }}
                            </td>
                            <td class="px-4 py-3">{{ log.causer?.name ?? 'System' }}</td>
                            <td class="max-w-md px-4 py-3">
                                <pre class="max-h-24 overflow-auto whitespace-pre-wrap break-all rounded bg-muted/50 p-2 text-xs">{{ JSON.stringify(log.properties, null, 1) }}</pre>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="logs" />
        </div>
    </AppLayout>
</template>
