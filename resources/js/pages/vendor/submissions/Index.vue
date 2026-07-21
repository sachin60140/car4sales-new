<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import VendorLayout from '@/layouts/VendorLayout.vue';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { FilePlus2 } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface Row {
    id: number;
    submission_number: string;
    title: string;
    make: string | null;
    model: string | null;
    variant: string | null;
    manufacturing_year: number | null;
    registration_number: string | null;
    fuel_type: string | null;
    transmission: string | null;
    odometer_km: number | null;
    expected_amount: string;
    status: string;
    status_label: string;
    settlement_status: string;
    settlement_label: string;
    stage: string;
    stage_label: string;
    created_at: string;
}

const props = defineProps<{
    submissions: Paginated<Row>;
    statuses: { value: string; label: string }[];
    filters: { status: string | null };
}>();

const filters = reactive({ status: props.filters.status ?? '' });
watch(filters, () => {
    router.get('/vendor/submissions', filters.status ? { status: filters.status } : {}, { preserveState: true, replace: true });
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

function details(s: Row): string {
    const bits = [
        s.registration_number,
        s.manufacturing_year,
        [s.fuel_type, s.transmission].filter(Boolean).join(' · ') || null,
        s.odometer_km ? Number(s.odometer_km).toLocaleString('en-IN') + ' km' : null,
    ].filter(Boolean);
    return bits.join('  ·  ');
}
</script>

<template>
    <Head title="My Submissions" />

    <VendorLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">My Submissions</h1>
            <div class="flex items-center gap-2">
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <Button size="sm" as-child>
                    <Link href="/vendor/submissions/create"><FilePlus2 class="mr-1 size-4" /> New</Link>
                </Button>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-xl border border-sidebar-border/70">
            <div v-if="submissions.data.length === 0" class="px-4 py-14 text-center text-sm text-muted-foreground">
                No submissions found.
            </div>
            <ul v-else class="divide-y">
                <li v-for="s in submissions.data" :key="s.id">
                    <Link :href="`/vendor/submissions/${s.id}`" class="flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-muted/40">
                        <div class="min-w-0">
                            <p class="font-medium">{{ s.title }}</p>
                            <p v-if="details(s)" class="truncate text-xs text-muted-foreground">{{ details(s) }}</p>
                            <p class="text-[11px] text-muted-foreground/80">{{ s.submission_number }} · {{ s.created_at }}</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span class="text-sm font-semibold">{{ money(s.expected_amount) }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="stageStyle[s.stage] ?? 'bg-muted text-muted-foreground'">{{ s.stage_label }}</span>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>

        <div class="mt-4">
            <Pagination :paginator="submissions" />
        </div>
    </VendorLayout>
</template>
