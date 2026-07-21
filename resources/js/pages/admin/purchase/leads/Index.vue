<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface Lead {
    id: number;
    lead_number: string;
    seller_name: string;
    mobile: string;
    make: string | null;
    model: string | null;
    registration_number: string | null;
    expected_price: string | null;
    priority: string;
    status: string;
    next_follow_up_at: string | null;
    assignee?: { id: number; name: string } | null;
    branch?: { id: number; name: string } | null;
}

const props = defineProps<{
    leads: Paginated<Lead>;
    branches: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
    filters: { search: string; status: string | null; branch_id: number | null; priority: string | null };
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Purchase Leads', href: '/admin/purchase-leads' },
];

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    branch_id: props.filters.branch_id ?? '',
    priority: props.filters.priority ?? '',
});

let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const q: Record<string, string> = {};
        if (filters.search) q.search = filters.search;
        if (filters.status) q.status = filters.status;
        if (filters.branch_id) q.branch_id = String(filters.branch_id);
        if (filters.priority) q.priority = filters.priority;
        router.get('/admin/purchase-leads', q, { preserveState: true, replace: true });
    }, 350);
});

const priorityStyles: Record<string, string> = {
    hot: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400',
    normal: 'bg-muted text-muted-foreground',
    low: 'bg-muted text-muted-foreground',
};

function statusLabel(value: string): string {
    return props.statuses.find((s) => s.value === value)?.label ?? value;
}

function formatMoney(value: string | null): string {
    if (!value) return '—';
    return '₹' + Number(value).toLocaleString('en-IN');
}
</script>

<template>
    <Head title="Purchase Leads" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Purchase Leads</h1>
                    <p class="text-sm text-muted-foreground">Vehicle acquisition pipeline.</p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link href="/admin/purchase-leads/create"><Plus class="mr-1 size-4" /> New Lead</Link>
                </Button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Lead #, name, mobile, reg. no…" class="pl-9" />
                </div>
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="filters.branch_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All branches</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <select v-model="filters.priority" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">Any priority</option>
                    <option value="hot">Hot</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Lead #</th>
                            <th class="px-4 py-3 font-medium">Seller</th>
                            <th class="px-4 py-3 font-medium">Vehicle</th>
                            <th class="px-4 py-3 font-medium">Expected</th>
                            <th class="px-4 py-3 font-medium">Priority</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Assignee</th>
                            <th class="px-4 py-3 font-medium">Follow-up</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="leads.data.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">No purchase leads found.</td>
                        </tr>
                        <tr
                            v-for="lead in leads.data"
                            :key="lead.id"
                            class="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                            @click="router.get(`/admin/purchase-leads/${lead.id}`)"
                        >
                            <td class="px-4 py-3 font-mono text-xs">{{ lead.lead_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ lead.seller_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ lead.mobile }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ [lead.make, lead.model].filter(Boolean).join(' ') || '—' }}</div>
                                <div class="text-xs text-muted-foreground">{{ lead.registration_number ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ formatMoney(lead.expected_price) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="priorityStyles[lead.priority]"
                                >
                                    {{ lead.priority }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-400"
                                >
                                    {{ statusLabel(lead.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ lead.assignee?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs">
                                {{ lead.next_follow_up_at ? new Date(lead.next_follow_up_at).toLocaleString() : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="leads" />
        </div>
    </AppLayout>
</template>
