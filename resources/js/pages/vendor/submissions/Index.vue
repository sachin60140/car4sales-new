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
    expected_amount: string;
    status: string;
    status_label: string;
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

const statusStyle: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    pending_review: 'bg-brand-orange/15 text-brand-orange',
    approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    rejected: 'bg-brand-red/15 text-brand-red',
};
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
                        <div>
                            <p class="font-medium">{{ s.title }}</p>
                            <p class="text-xs text-muted-foreground">{{ s.submission_number }} · {{ s.created_at }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold">{{ money(s.expected_amount) }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyle[s.status]">{{ s.status_label }}</span>
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
