<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface JobRow {
    id: number;
    job_number: string;
    vehicle?: { id: number; stock_number: string; make: string; model: string } | null;
    vendor?: { id: number; name: string } | null;
    type: string;
    status: string;
    status_label: string;
    estimate_total: string;
    approved_total: string | null;
    actual_total: string;
    expected_completion: string | null;
}

const props = defineProps<{
    jobs: Paginated<JobRow>;
    statuses: { value: string; label: string }[];
    filters: { status: string | null };
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Workshop', href: '/admin/workshop' },
];

const status = ref(props.filters.status ?? '');
watch(status, (val) => router.get('/admin/workshop', val ? { status: val } : {}, { preserveState: true, replace: true }));

function money(v: string | null): string {
    if (!v) return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

const statusStyle: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    approved: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    in_progress: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
    qc_passed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    qc_failed: 'bg-brand-red/15 text-brand-red',
    cancelled: 'bg-brand-red/15 text-brand-red',
};
</script>

<template>
    <Head title="Workshop" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Workshop &amp; Refurbishment</h1>
                    <p class="text-sm text-muted-foreground">Refurbishment job cards. Create jobs from a vehicle's stock card.</p>
                </div>
                <select v-model="status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Job #</th>
                            <th class="px-4 py-3 font-medium">Vehicle</th>
                            <th class="px-4 py-3 font-medium">Vendor</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Estimate</th>
                            <th class="px-4 py-3 font-medium">Actual</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="jobs.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No workshop jobs.</td>
                        </tr>
                        <tr
                            v-for="j in jobs.data"
                            :key="j.id"
                            class="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                            @click="router.get(`/admin/workshop/${j.id}`)"
                        >
                            <td class="px-4 py-3 font-mono text-xs">{{ j.job_number }}</td>
                            <td class="px-4 py-3">
                                <div>{{ j.vehicle?.stock_number }}</div>
                                <div class="text-xs text-muted-foreground">{{ [j.vehicle?.make, j.vehicle?.model].filter(Boolean).join(' ') }}</div>
                            </td>
                            <td class="px-4 py-3">{{ j.vendor?.name ?? 'Internal' }}</td>
                            <td class="px-4 py-3 capitalize">{{ j.type }}</td>
                            <td class="px-4 py-3">{{ money(j.approved_total ?? j.estimate_total) }}</td>
                            <td class="px-4 py-3">{{ money(j.actual_total) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusStyle[j.status] ?? 'bg-muted text-muted-foreground'"
                                >
                                    {{ j.status_label }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="jobs" />
        </div>
    </AppLayout>
</template>
