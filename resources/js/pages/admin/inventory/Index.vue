<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface StockRow {
    id: number;
    stock_number: string;
    title: string;
    registration_number: string | null;
    manufacturing_year: number | null;
    branch?: { id: number; name: string } | null;
    status: string;
    status_label: string;
    asking_price: string | null;
    landed_cost: string | null;
    published_web: boolean;
    age_days: number;
}

const props = defineProps<{
    vehicles: Paginated<StockRow>;
    branches: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
    filters: { search: string; status: string | null; branch_id: number | null; published: string | null; refurb_required: boolean };
    can: { viewCost: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/admin/inventory' },
];

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    branch_id: props.filters.branch_id ?? '',
    published: props.filters.published ?? '',
});

let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const q: Record<string, string> = {};
        if (filters.search) q.search = filters.search;
        if (filters.status) q.status = filters.status;
        if (filters.branch_id) q.branch_id = String(filters.branch_id);
        if (filters.published) q.published = filters.published;
        router.get('/admin/inventory', q, { preserveState: true, replace: true });
    }, 350);
});

function money(v: string | null): string {
    if (!v) return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

const statusStyle: Record<string, string> = {
    ready_for_sale: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    published: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    under_refurbishment: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
    booked: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    delivered: 'bg-muted text-muted-foreground',
};
function ageStyle(days: number): string {
    if (days > 60) return 'text-brand-red font-semibold';
    if (days > 30) return 'text-brand-orange font-medium';
    return 'text-muted-foreground';
}
</script>

<template>
    <Head title="Inventory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Inventory</h1>
                <p class="text-sm text-muted-foreground">Vehicle stock, ageing and publication.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Stock #, reg, make, model…" class="pl-9" />
                </div>
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="filters.branch_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All branches</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <select v-model="filters.published" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">Any publication</option>
                    <option value="yes">Published</option>
                    <option value="no">Unpublished</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Stock #</th>
                            <th class="px-4 py-3 font-medium">Vehicle</th>
                            <th class="px-4 py-3 font-medium">Branch</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th v-if="can.viewCost" class="px-4 py-3 font-medium">Landed Cost</th>
                            <th class="px-4 py-3 font-medium">Asking</th>
                            <th class="px-4 py-3 font-medium">Age</th>
                            <th class="px-4 py-3 font-medium">Web</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="vehicles.data.length === 0">
                            <td :colspan="can.viewCost ? 8 : 7" class="px-4 py-10 text-center text-muted-foreground">No vehicles in stock.</td>
                        </tr>
                        <tr
                            v-for="v in vehicles.data"
                            :key="v.id"
                            class="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                            @click="router.get(`/admin/inventory/${v.id}`)"
                        >
                            <td class="px-4 py-3 font-mono text-xs">{{ v.stock_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ v.title }}</div>
                                <div class="text-xs text-muted-foreground">{{ v.manufacturing_year }} · {{ v.registration_number ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ v.branch?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusStyle[v.status] ?? 'bg-muted text-muted-foreground'"
                                >
                                    {{ v.status_label }}
                                </span>
                            </td>
                            <td v-if="can.viewCost" class="px-4 py-3">{{ money(v.landed_cost) }}</td>
                            <td class="px-4 py-3">{{ money(v.asking_price) }}</td>
                            <td class="px-4 py-3" :class="ageStyle(v.age_days)">{{ v.age_days }}d</td>
                            <td class="px-4 py-3">
                                <span v-if="v.published_web" class="inline-flex size-2 rounded-full bg-emerald-500" title="Published" />
                                <span v-else class="inline-flex size-2 rounded-full bg-muted-foreground/40" title="Unpublished" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="vehicles" />
        </div>
    </AppLayout>
</template>
