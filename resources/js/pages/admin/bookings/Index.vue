<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface BookingRow {
    id: number;
    booking_number: string;
    customer?: { id: number; name: string; mobile: string } | null;
    vehicle?: { id: number; stock_number: string; make: string; model: string } | null;
    selling_price: string;
    status: string;
    status_label: string;
    sales_executive?: { id: number; name: string } | null;
    branch?: { id: number; name: string } | null;
    created_at: string;
}

const props = defineProps<{
    bookings: Paginated<BookingRow>;
    statuses: { value: string; label: string }[];
    filters: { search: string; status: string | null };
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Bookings', href: '/admin/bookings' },
];

const filters = reactive({ search: props.filters.search ?? '', status: props.filters.status ?? '' });
let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const q: Record<string, string> = {};
        if (filters.search) q.search = filters.search;
        if (filters.status) q.status = filters.status;
        router.get('/admin/bookings', q, { preserveState: true, replace: true });
    }, 350);
});

function money(v: string): string {
    return '₹' + Number(v).toLocaleString('en-IN');
}

const statusStyle: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    approval_pending: 'bg-brand-orange/15 text-brand-orange',
    confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    ready_for_delivery: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    delivered: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    cancelled: 'bg-brand-red/15 text-brand-red',
    refunded: 'bg-brand-red/15 text-brand-red',
    forfeited: 'bg-brand-red/15 text-brand-red',
    cancellation_requested: 'bg-brand-orange/15 text-brand-orange',
};
</script>

<template>
    <Head title="Bookings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Bookings</h1>
                <p class="text-sm text-muted-foreground">Vehicle bookings and their status. Create bookings from a sales lead.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Booking #, customer…" class="pl-9" />
                </div>
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[860px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Booking #</th>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="px-4 py-3 font-medium">Vehicle</th>
                            <th class="px-4 py-3 font-medium">Price</th>
                            <th class="px-4 py-3 font-medium">Sales Exec</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="bookings.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No bookings yet.</td>
                        </tr>
                        <tr
                            v-for="b in bookings.data"
                            :key="b.id"
                            class="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                            @click="router.get(`/admin/bookings/${b.id}`)"
                        >
                            <td class="px-4 py-3 font-mono text-xs">{{ b.booking_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ b.customer?.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ b.customer?.mobile }}</div>
                            </td>
                            <td class="px-4 py-3">{{ b.vehicle ? `${b.vehicle.stock_number} · ${b.vehicle.make} ${b.vehicle.model}` : '—' }}</td>
                            <td class="px-4 py-3">{{ money(b.selling_price) }}</td>
                            <td class="px-4 py-3">{{ b.sales_executive?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusStyle[b.status] ?? 'bg-muted text-muted-foreground'"
                                    >{{ b.status_label }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="bookings" />
        </div>
    </AppLayout>
</template>
