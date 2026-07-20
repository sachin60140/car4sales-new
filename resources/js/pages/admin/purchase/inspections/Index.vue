<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Inspection {
    id: number;
    inspection_number: string;
    status: string;
    result: string | null;
    overall_grade: string | null;
    scheduled_at: string | null;
    total_repair_estimate: string;
    purchase_lead?: { id: number; lead_number: string; make: string | null; model: string | null; registration_number: string | null };
    inspector?: { id: number; name: string } | null;
    branch?: { id: number; name: string } | null;
}

const props = defineProps<{
    inspections: Paginated<Inspection>;
    filters: { status: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inspections', href: '/admin/inspections' },
];

const status = ref(props.filters.status ?? '');
watch(status, (v) => router.get('/admin/inspections', v ? { status: v } : {}, { preserveState: true, replace: true }));

const statusStyles: Record<string, string> = {
    scheduled: 'bg-muted text-muted-foreground',
    in_progress: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
    submitted: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    reviewed: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
};
</script>

<template>
    <Head title="Inspections" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Vehicle Inspections</h1>
                    <p class="text-sm text-muted-foreground">Inspection assignments and reports.</p>
                </div>
                <select v-model="status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="in_progress">In Progress</option>
                    <option value="submitted">Submitted</option>
                    <option value="reviewed">Reviewed</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Inspection #</th>
                            <th class="px-4 py-3 font-medium">Lead / Vehicle</th>
                            <th class="px-4 py-3 font-medium">Inspector</th>
                            <th class="px-4 py-3 font-medium">Grade</th>
                            <th class="px-4 py-3 font-medium">Repair Est.</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Scheduled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="inspections.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No inspections found.</td>
                        </tr>
                        <tr
                            v-for="ins in inspections.data"
                            :key="ins.id"
                            class="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                            @click="router.get(`/admin/inspections/${ins.id}`)"
                        >
                            <td class="px-4 py-3 font-mono text-xs">{{ ins.inspection_number }}</td>
                            <td class="px-4 py-3">
                                <div>{{ ins.purchase_lead?.lead_number }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ [ins.purchase_lead?.make, ins.purchase_lead?.model].filter(Boolean).join(' ') }}
                                    {{ ins.purchase_lead?.registration_number ?? '' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ ins.inspector?.name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ ins.overall_grade ?? '—' }}</td>
                            <td class="px-4 py-3">₹{{ Number(ins.total_repair_estimate).toLocaleString('en-IN') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusStyles[ins.status]">
                                    {{ ins.status.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs">{{ ins.scheduled_at ? new Date(ins.scheduled_at).toLocaleString() : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="inspections" />
        </div>
    </AppLayout>
</template>
