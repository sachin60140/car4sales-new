<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface RtoRow {
    id: number;
    rto_number: string;
    vehicle?: { id: number; stock_number: string; registration_number: string | null; title: string } | null;
    buyer?: { id: number; name: string; mobile: string } | null;
    assignee?: { id: number; name: string } | null;
    branch?: { id: number; name: string } | null;
    status: string;
    status_label: string;
    expected_completion: string | null;
    hold_amount: string;
}

const props = defineProps<{
    cases: Paginated<RtoRow>;
    statuses: { value: string; label: string }[];
    filters: { search: string; status: string | null; mine: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RTO Cases', href: '/admin/rto-cases' },
];

const filters = reactive({ search: props.filters.search ?? '', status: props.filters.status ?? '', mine: props.filters.mine ?? false });
let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const q: Record<string, string> = {};
        if (filters.search) q.search = filters.search;
        if (filters.status) q.status = filters.status;
        if (filters.mine) q.mine = '1';
        router.get('/admin/rto-cases', q, { preserveState: true, replace: true });
    }, 350);
});

function money(v: string | null): string {
    if (!v || Number(v) === 0) return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

const closedish = ['rc_handed_over', 'closed'];
function statusStyle(s: string): string {
    if (closedish.includes(s)) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400';
    if (s === 'objection_raised') return 'bg-brand-red/15 text-brand-red';
    if (s.startsWith('rc_') || s === 'transfer_approved') return 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-400';
    return 'bg-brand-orange/15 text-brand-orange';
}
</script>

<template>
    <Head title="RTO Cases" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">RTO Transfer Cases</h1>
                <p class="text-sm text-muted-foreground">Ownership transfer tracking — documents, expenses and holds.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="RTO #, app #, reg no…" class="pl-9" />
                </div>
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="filters.mine" type="checkbox" class="size-4 rounded border-input" /> Assigned to me
                </label>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[920px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">RTO #</th>
                            <th class="px-4 py-3 font-medium">Vehicle</th>
                            <th class="px-4 py-3 font-medium">Buyer</th>
                            <th class="px-4 py-3 font-medium">Assignee</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Hold</th>
                            <th class="px-4 py-3 font-medium">ETA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="cases.data.length === 0"><td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No RTO cases.</td></tr>
                        <tr v-for="c in cases.data" :key="c.id" class="cursor-pointer border-b last:border-0 hover:bg-muted/30" @click="router.get(`/admin/rto-cases/${c.id}`)">
                            <td class="px-4 py-3 font-mono text-xs">{{ c.rto_number }}</td>
                            <td class="px-4 py-3">
                                <div>{{ c.vehicle?.title }}</div>
                                <div class="text-xs text-muted-foreground">{{ c.vehicle?.registration_number ?? c.vehicle?.stock_number }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ c.buyer?.name ?? '—' }}</div>
                                <div class="text-xs text-muted-foreground">{{ c.buyer?.mobile }}</div>
                            </td>
                            <td class="px-4 py-3">{{ c.assignee?.name ?? '—' }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyle(c.status)">{{ c.status_label }}</span></td>
                            <td class="px-4 py-3">{{ money(c.hold_amount) }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ c.expected_completion ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="cases" />
        </div>
    </AppLayout>
</template>
