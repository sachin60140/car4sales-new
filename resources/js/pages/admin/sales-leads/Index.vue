<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Phone, Plus, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface LeadRow {
    id: number;
    lead_number: string;
    name: string;
    mobile: string;
    city: string | null;
    status: string;
    status_label: string;
    priority: string;
    source: string;
    branch?: { id: number; name: string } | null;
    telecaller?: { id: number; name: string } | null;
    sales_executive?: { id: number; name: string } | null;
    interested_vehicle?: { id: number; stock_number: string; make: string; model: string } | null;
    next_follow_up_at: string | null;
    overdue: boolean;
}

const props = defineProps<{
    leads: Paginated<LeadRow>;
    statuses: { value: string; label: string }[];
    branches: { id: number; name: string }[];
    telecallers: { id: number; name: string }[];
    filters: { search: string; status: string | null; priority: string | null; branch_id: number | null; telecaller_id: number | null; queue: string | null };
    can: { create: boolean; assign: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales Leads', href: '/admin/sales-leads' },
];

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    branch_id: props.filters.branch_id ?? '',
    telecaller_id: props.filters.telecaller_id ?? '',
    queue: props.filters.queue ?? '',
});
let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(apply, 350);
});
function apply() {
    const q: Record<string, string> = {};
    Object.entries(filters).forEach(([k, v]) => {
        if (v !== '' && v !== null) q[k] = String(v);
    });
    router.get('/admin/sales-leads', q, { preserveState: true, replace: true });
}
function setQueue(v: string) {
    filters.queue = filters.queue === v ? '' : v;
}

const priorityStyle: Record<string, string> = {
    hot: 'bg-brand-red/15 text-brand-red',
    high: 'bg-brand-orange/15 text-brand-orange',
    normal: 'bg-muted text-muted-foreground',
    low: 'bg-muted text-muted-foreground',
};
const statusStyle: Record<string, string> = {
    new: 'bg-brand-orange/15 text-brand-orange',
    interested: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    booking: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    delivered: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    lost: 'bg-brand-red/15 text-brand-red',
    wrong_number: 'bg-brand-red/15 text-brand-red',
    duplicate: 'bg-brand-red/15 text-brand-red',
};
</script>

<template>
    <Head title="Sales Leads" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Sales Leads</h1>
                    <p class="text-sm text-muted-foreground">Telecaller work queue and lead pipeline.</p>
                </div>
                <Button v-if="can.create" as-child><Link href="/admin/sales-leads/create"><Plus class="mr-1 size-4" /> New Lead</Link></Button>
            </div>

            <!-- Quick queues -->
            <div class="flex flex-wrap gap-2">
                <button class="rounded-full px-3 py-1 text-sm font-medium" :class="filters.queue === 'my' ? 'bg-brand-maroon text-white dark:bg-brand-yellow dark:text-brand-maroon' : 'bg-muted text-muted-foreground'" @click="setQueue('my')">My Queue</button>
                <button class="rounded-full px-3 py-1 text-sm font-medium" :class="filters.queue === 'due' ? 'bg-brand-maroon text-white dark:bg-brand-yellow dark:text-brand-maroon' : 'bg-muted text-muted-foreground'" @click="setQueue('due')">Follow-ups Due</button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Name, mobile, lead #…" class="pl-9" />
                </div>
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="filters.priority" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">Any priority</option>
                    <option value="hot">Hot</option><option value="high">High</option><option value="normal">Normal</option><option value="low">Low</option>
                </select>
                <select v-model="filters.telecaller_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All telecallers</option>
                    <option v-for="t in telecallers" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Lead #</th>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="px-4 py-3 font-medium">Priority</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Telecaller</th>
                            <th class="px-4 py-3 font-medium">Follow-up</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="leads.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No leads in this view.</td>
                        </tr>
                        <tr v-for="l in leads.data" :key="l.id" class="cursor-pointer border-b last:border-0 hover:bg-muted/30" @click="router.get(`/admin/sales-leads/${l.id}`)">
                            <td class="px-4 py-3 font-mono text-xs">{{ l.lead_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ l.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ l.mobile }}<span v-if="l.city"> · {{ l.city }}</span></div>
                            </td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="priorityStyle[l.priority]">{{ l.priority }}</span></td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyle[l.status] ?? 'bg-muted text-muted-foreground'">{{ l.status_label }}</span></td>
                            <td class="px-4 py-3">{{ l.telecaller?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span v-if="l.next_follow_up_at" :class="l.overdue ? 'font-semibold text-brand-red' : 'text-muted-foreground'">
                                    {{ new Date(l.next_follow_up_at).toLocaleDateString() }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="leads" />
        </div>
    </AppLayout>
</template>
