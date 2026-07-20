<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface FinanceRow {
    id: number;
    application_number: string;
    customer?: { id: number; name: string; mobile: string } | null;
    lender?: { id: number; name: string } | null;
    booking?: { id: number; booking_number: string } | null;
    loan_amount: string;
    sanction_amount: string | null;
    status: string;
    status_label: string;
}

const props = defineProps<{
    applications: Paginated<FinanceRow>;
    statuses: { value: string; label: string }[];
    filters: { search: string; status: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Finance', href: '/admin/finance' },
];

const filters = reactive({ search: props.filters.search ?? '', status: props.filters.status ?? '' });
let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const q: Record<string, string> = {};
        if (filters.search) q.search = filters.search;
        if (filters.status) q.status = filters.status;
        router.get('/admin/finance', q, { preserveState: true, replace: true });
    }, 350);
});

function money(v: string | null): string {
    if (!v) return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

const statusStyle: Record<string, string> = {
    documents_pending: 'bg-muted text-muted-foreground',
    sanctioned: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    disbursed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    rejected: 'bg-brand-red/15 text-brand-red',
    query_raised: 'bg-brand-orange/15 text-brand-orange',
};
</script>

<template>
    <Head title="Finance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Finance Applications</h1>
                <p class="text-sm text-muted-foreground">Customer loan files. Create a finance file from a finance-mode booking.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Application #, customer…" class="pl-9" />
                </div>
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Application #</th>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="px-4 py-3 font-medium">Lender</th>
                            <th class="px-4 py-3 font-medium">Loan</th>
                            <th class="px-4 py-3 font-medium">Sanction</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="applications.data.length === 0"><td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No finance applications.</td></tr>
                        <tr v-for="f in applications.data" :key="f.id" class="cursor-pointer border-b last:border-0 hover:bg-muted/30" @click="router.get(`/admin/finance/${f.id}`)">
                            <td class="px-4 py-3 font-mono text-xs">{{ f.application_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ f.customer?.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ f.customer?.mobile }}</div>
                            </td>
                            <td class="px-4 py-3">{{ f.lender?.name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ money(f.loan_amount) }}</td>
                            <td class="px-4 py-3">{{ money(f.sanction_amount) }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyle[f.status] ?? 'bg-muted text-muted-foreground'">{{ f.status_label }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="applications" />
        </div>
    </AppLayout>
</template>
