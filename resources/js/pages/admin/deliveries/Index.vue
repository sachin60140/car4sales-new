<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

interface DeliveryRow {
    id: number;
    delivery_number: string;
    customer?: { id: number; name: string; mobile: string } | null;
    vehicle?: { id: number; stock_number: string; title: string } | null;
    booking?: { id: number; booking_number: string } | null;
    branch?: { id: number; name: string } | null;
    status: string;
    status_label: string;
    scheduled_at: string | null;
    delivered_at: string | null;
}

const props = defineProps<{
    deliveries: Paginated<DeliveryRow>;
    statuses: { value: string; label: string }[];
    eligibleBookings: { id: number; booking_number: string; label: string }[];
    filters: { search: string; status: string | null };
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Deliveries', href: '/admin/deliveries' },
];

const filters = reactive({ search: props.filters.search ?? '', status: props.filters.status ?? '' });
let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const q: Record<string, string> = {};
        if (filters.search) q.search = filters.search;
        if (filters.status) q.status = filters.status;
        router.get('/admin/deliveries', q, { preserveState: true, replace: true });
    }, 350);
});

const showCreate = ref(false);
const createForm = useForm({ booking_id: null as number | null });
function submitCreate() {
    if (!createForm.booking_id) return;
    createForm.post('/admin/deliveries', {
        onSuccess: () => {
            showCreate.value = false;
            createForm.reset();
        },
    });
}

const statusStyle: Record<string, string> = {
    approval_pending: 'bg-brand-orange/15 text-brand-orange',
    approved: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-400',
    delivered: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    cancelled: 'bg-muted text-muted-foreground',
};

function fmtDate(v: string | null): string {
    return v ? new Date(v).toLocaleString() : '—';
}
</script>

<template>
    <Head title="Deliveries" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Deliveries</h1>
                    <p class="text-sm text-muted-foreground">Delivery approval checklist and vehicle handover.</p>
                </div>
                <Button v-if="can.create" size="sm" @click="showCreate = !showCreate"> <Plus class="mr-1 size-4" /> New Delivery </Button>
            </div>

            <div v-if="showCreate" class="rounded-xl border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                <p class="mb-2 text-sm font-medium">Open a delivery for a booking</p>
                <div v-if="eligibleBookings.length === 0" class="text-sm text-muted-foreground">No confirmed bookings are awaiting delivery.</div>
                <div v-else class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="createForm.booking_id"
                        class="h-9 min-w-[320px] rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                    >
                        <option :value="null">Select booking…</option>
                        <option v-for="b in eligibleBookings" :key="b.id" :value="b.id">{{ b.label }}</option>
                    </select>
                    <Button size="sm" :disabled="!createForm.booking_id || createForm.processing" @click="submitCreate">Open Delivery</Button>
                    <Button size="sm" variant="ghost" @click="showCreate = false">Cancel</Button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Delivery #, customer, stock…" class="pl-9" />
                </div>
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[880px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Delivery #</th>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="px-4 py-3 font-medium">Vehicle</th>
                            <th class="px-4 py-3 font-medium">Booking</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Delivered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="deliveries.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No deliveries yet.</td>
                        </tr>
                        <tr
                            v-for="d in deliveries.data"
                            :key="d.id"
                            class="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                            @click="router.get(`/admin/deliveries/${d.id}`)"
                        >
                            <td class="px-4 py-3 font-mono text-xs">{{ d.delivery_number }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ d.customer?.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ d.customer?.mobile }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ d.vehicle?.title }}</div>
                                <div class="text-xs text-muted-foreground">{{ d.vehicle?.stock_number }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ d.booking?.booking_number ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusStyle[d.status] ?? 'bg-muted text-muted-foreground'"
                                    >{{ d.status_label }}</span
                                >
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ fmtDate(d.delivered_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="deliveries" />
        </div>
    </AppLayout>
</template>
