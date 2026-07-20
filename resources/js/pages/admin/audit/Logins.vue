<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, LoginHistoryEntry, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    logins: Paginated<LoginHistoryEntry>;
    filters: { event: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Login History', href: '/admin/audit/logins' },
];

const event = ref(props.filters.event ?? '');

watch(event, (value) => {
    router.get('/admin/audit/logins', value ? { event: value } : {}, { preserveState: true, replace: true });
});

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}

const eventStyles: Record<string, string> = {
    login: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
    logout: 'bg-muted text-muted-foreground',
    failed: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
    forced_logout: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
};
</script>

<template>
    <Head title="Login History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Login History</h1>
                    <p class="text-sm text-muted-foreground">Login, logout and failed attempts with device and IP.</p>
                </div>
                <div class="flex gap-2 text-sm">
                    <Link href="/admin/audit/activity" class="rounded-md px-3 py-1.5 text-muted-foreground hover:bg-muted">Activity</Link>
                    <Link href="/admin/audit/logins" class="rounded-md bg-muted px-3 py-1.5 font-medium">Logins</Link>
                </div>
            </div>

            <select v-model="event" class="h-9 w-fit rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                <option value="">All events</option>
                <option value="login">Login</option>
                <option value="logout">Logout</option>
                <option value="failed">Failed</option>
                <option value="forced_logout">Forced Logout</option>
            </select>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">When</th>
                            <th class="px-4 py-3 font-medium">User</th>
                            <th class="px-4 py-3 font-medium">Event</th>
                            <th class="px-4 py-3 font-medium">Guard</th>
                            <th class="px-4 py-3 font-medium">IP Address</th>
                            <th class="px-4 py-3 font-medium">Device / Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="logins.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No login history yet.</td>
                        </tr>
                        <tr v-for="entry in logins.data" :key="entry.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="whitespace-nowrap px-4 py-3">{{ formatDate(entry.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ entry.user?.name ?? '—' }}</div>
                                <div class="text-xs text-muted-foreground">{{ entry.user?.email ?? entry.email ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="eventStyles[entry.event] ?? 'bg-muted'">
                                    {{ entry.event.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 uppercase">{{ entry.guard }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ entry.ip_address ?? '—' }}</td>
                            <td class="max-w-sm truncate px-4 py-3 text-xs text-muted-foreground" :title="entry.user_agent ?? ''">
                                {{ entry.device_uuid ?? entry.user_agent ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="logins" />
        </div>
    </AppLayout>
</template>
