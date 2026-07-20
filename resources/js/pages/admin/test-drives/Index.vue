<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface TdRow {
    id: number;
    td_number: string;
    customer?: { id: number; name: string; mobile: string } | null;
    vehicle?: { id: number; stock_number: string; make: string; model: string } | null;
    lead?: { id: number; lead_number: string } | null;
    scheduled_at: string | null;
    status: string;
}

const props = defineProps<{
    drives: Paginated<TdRow>;
    filters: { status: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Test Drives', href: '/admin/test-drives' },
];

const status = ref(props.filters.status ?? '');
watch(status, (v) => router.get('/admin/test-drives', v ? { status: v } : {}, { preserveState: true, replace: true }));

function complete(id: number) {
    const feedback = prompt('Test drive feedback?') ?? '';
    router.post(`/admin/test-drives/${id}/complete`, { feedback, damage_acknowledged: false }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Test Drives" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Test Drives</h1>
                    <p class="text-sm text-muted-foreground">Test drives scheduled from sales leads.</p>
                </div>
                <select v-model="status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option value="scheduled">Scheduled</option><option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option><option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">TD #</th>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="px-4 py-3 font-medium">Vehicle</th>
                            <th class="px-4 py-3 font-medium">Scheduled</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="drives.data.length === 0"><td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No test drives.</td></tr>
                        <tr v-for="t in drives.data" :key="t.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-mono text-xs">{{ t.td_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ t.customer?.name ?? '—' }}</div>
                                <div class="text-xs text-muted-foreground">{{ t.customer?.mobile }}</div>
                            </td>
                            <td class="px-4 py-3">{{ t.vehicle ? `${t.vehicle.stock_number} · ${t.vehicle.make} ${t.vehicle.model}` : '—' }}</td>
                            <td class="px-4 py-3">{{ t.scheduled_at ? new Date(t.scheduled_at).toLocaleString() : '—' }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full bg-muted px-2 py-0.5 text-xs font-medium capitalize">{{ (t.status ?? '').replace(/_/g, ' ') }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <Button v-if="['scheduled','in_progress'].includes(t.status)" size="sm" variant="outline" @click="complete(t.id)">Complete</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="drives" />
        </div>
    </AppLayout>
</template>
