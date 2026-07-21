<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface VisitRow {
    id: number;
    visit_number: string;
    customer?: { id: number; name: string; mobile: string } | null;
    lead?: { id: number; lead_number: string } | null;
    branch?: { id: number; name: string } | null;
    attended_by?: { id: number; name: string } | null;
    scheduled_at: string | null;
    status: string;
    status_label: string;
    outcome: string | null;
}

const props = defineProps<{
    visits: Paginated<VisitRow>;
    statuses: { value: string; label: string }[];
    filters: { status: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Visits', href: '/admin/visits' },
];

const status = ref(props.filters.status ?? '');
watch(status, (v) => router.get('/admin/visits', v ? { status: v } : {}, { preserveState: true, replace: true }));

function complete(id: number) {
    const outcome = prompt('Visit outcome?') ?? '';
    router.post(`/admin/visits/${id}/complete`, { outcome }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Visits" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Customer Visits</h1>
                    <p class="text-sm text-muted-foreground">Showroom visits scheduled from sales leads.</p>
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
                            <th class="px-4 py-3 font-medium">Visit #</th>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="px-4 py-3 font-medium">Scheduled</th>
                            <th class="px-4 py-3 font-medium">Attended By</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="visits.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No visits.</td>
                        </tr>
                        <tr v-for="v in visits.data" :key="v.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-mono text-xs">{{ v.visit_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ v.customer?.name ?? '—' }}</div>
                                <div class="text-xs text-muted-foreground">{{ v.customer?.mobile }}</div>
                            </td>
                            <td class="px-4 py-3">{{ v.scheduled_at ? new Date(v.scheduled_at).toLocaleString() : '—' }}</td>
                            <td class="px-4 py-3">{{ v.attended_by?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-muted px-2 py-0.5 text-xs font-medium">{{ v.status_label }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button v-if="['scheduled', 'confirmed'].includes(v.status)" size="sm" variant="outline" @click="complete(v.id)"
                                    >Complete</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="visits" />
        </div>
    </AppLayout>
</template>
