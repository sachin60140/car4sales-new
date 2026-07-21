<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface Row {
    id: number;
    submission_number: string;
    title: string;
    registration_number: string | null;
    manufacturing_year: number | null;
    vendor?: { id: number; name: string } | null;
    expected_amount: string;
    overall_rating: number | null;
    status: string;
    status_label: string;
    stage: string;
    stage_label: string;
    created_at: string;
}

const props = defineProps<{
    submissions: Paginated<Row>;
    statuses: { value: string; label: string }[];
    filters: { search: string; status: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendor Submissions', href: '/admin/vendor-submissions' },
];

const filters = reactive({ search: props.filters.search ?? '', status: props.filters.status ?? '' });
let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const q: Record<string, string> = {};
        if (filters.search) q.search = filters.search;
        if (filters.status) q.status = filters.status;
        router.get('/admin/vendor-submissions', q, { preserveState: true, replace: true });
    }, 350);
});

function money(v: string): string {
    return '₹' + Number(v).toLocaleString('en-IN');
}

// Styling for every stage a submission can be in — its own status plus the
// settlement stages it moves through once approved.
const stageStyle: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    pending_review: 'bg-brand-orange/15 text-brand-orange',
    rejected: 'bg-brand-red/15 text-brand-red',
    approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    not_started: 'bg-muted text-muted-foreground',
    kyc_pending: 'bg-brand-orange/15 text-brand-orange',
    kyc_submitted: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
    agreement_ready: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-400',
    payment_requested: 'bg-brand-orange/15 text-brand-orange',
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    stocked: 'bg-emerald-600/15 text-emerald-700 dark:text-emerald-400',
};

function vehicleDetails(s: Row): string {
    return [s.registration_number, s.manufacturing_year].filter(Boolean).join('  ·  ');
}
</script>

<template>
    <Head title="Vendor Submissions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Vendor Submissions</h1>
                <p class="text-sm text-muted-foreground">Vehicles offered by sourcing partners, awaiting review.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Submission #, vehicle, vendor…" class="pl-9" />
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
                            <th class="px-4 py-3 font-medium">Submission #</th>
                            <th class="px-4 py-3 font-medium">Vehicle</th>
                            <th class="px-4 py-3 font-medium">Vendor</th>
                            <th class="px-4 py-3 font-medium">Expected</th>
                            <th class="px-4 py-3 font-medium">Rating</th>
                            <th class="px-4 py-3 font-medium">Current Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="submissions.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No vendor submissions.</td>
                        </tr>
                        <tr
                            v-for="s in submissions.data"
                            :key="s.id"
                            class="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                            @click="router.get(`/admin/vendor-submissions/${s.id}`)"
                        >
                            <td class="px-4 py-3 font-mono text-xs">{{ s.submission_number }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ s.title }}</p>
                                <p v-if="vehicleDetails(s)" class="text-xs text-muted-foreground">{{ vehicleDetails(s) }}</p>
                            </td>
                            <td class="px-4 py-3">{{ s.vendor?.name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ money(s.expected_amount) }}</td>
                            <td class="px-4 py-3">{{ s.overall_rating ? s.overall_rating + '★' : '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="stageStyle[s.stage] ?? 'bg-muted text-muted-foreground'"
                                    >{{ s.stage_label }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="submissions" />
        </div>
    </AppLayout>
</template>
