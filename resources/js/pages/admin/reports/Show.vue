<script setup lang="ts">
import BarChart from '@/components/charts/BarChart.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, FileText } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

interface Column { key: string; label: string; align?: string; format?: string }
interface SummaryItem { label: string; value: number | string; format?: string }
interface Filter { key: string; label: string; type: string }

const props = defineProps<{
    report: { key: string; label: string; description: string; group: string; filters: Filter[] };
    filters: { date_from: string | null; date_to: string | null; branch_id: number | null };
    branches: { id: number; name: string }[];
    result: {
        columns: Column[];
        rows: Record<string, unknown>[];
        summary: SummaryItem[];
        chart: { type: string; labels: string[]; values: number[] } | null;
    };
    can: { export: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/admin/reports' },
    { title: props.report.label, href: '#' },
];

const form = reactive({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    branch_id: props.filters.branch_id ?? '',
});

const filterKeys = computed(() => props.report.filters.map((f) => f.key));
const showDates = computed(() => filterKeys.value.includes('date_from'));
const showBranch = computed(() => filterKeys.value.includes('branch_id'));

function query(): Record<string, string> {
    const q: Record<string, string> = {};
    if (showDates.value && form.date_from) q.date_from = form.date_from;
    if (showDates.value && form.date_to) q.date_to = form.date_to;
    if (showBranch.value && form.branch_id) q.branch_id = String(form.branch_id);
    return q;
}

function apply() {
    router.get(`/admin/reports/${props.report.key}`, query(), { preserveState: true, preserveScroll: true });
}

function exportUrl(format: 'csv' | 'pdf'): string {
    const params = new URLSearchParams({ ...query(), format });
    return `/admin/reports/${props.report.key}/export?${params.toString()}`;
}

function fmt(value: unknown, format?: string): string {
    if (value === null || value === undefined || value === '') return '—';
    if (format === 'money') return '₹' + Number(value).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    if (format === 'percent') return value + '%';
    if (format === 'days') return value + ' days';
    return String(value);
}

const chartLabels = computed(() => props.result.chart?.labels ?? []);
const chartValues = computed(() => props.result.chart?.values ?? []);
const hasChart = computed(() => chartLabels.value.length > 0);
</script>

<template>
    <Head :title="report.label" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ report.label }}</h1>
                    <p class="text-sm text-muted-foreground">{{ report.description }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="can.export" size="sm" variant="outline" as-child>
                        <a :href="exportUrl('csv')"><Download class="mr-1 size-4" /> CSV</a>
                    </Button>
                    <Button v-if="can.export" size="sm" variant="outline" as-child>
                        <a :href="exportUrl('pdf')"><FileText class="mr-1 size-4" /> PDF</a>
                    </Button>
                    <Button variant="outline" as-child><Link href="/admin/reports">Back</Link></Button>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-end gap-3 rounded-xl border border-sidebar-border/70 bg-muted/20 p-3 dark:border-sidebar-border">
                <template v-if="showDates">
                    <div class="grid gap-1"><Label class="text-xs">From</Label><Input v-model="form.date_from" type="date" class="h-9" /></div>
                    <div class="grid gap-1"><Label class="text-xs">To</Label><Input v-model="form.date_to" type="date" class="h-9" /></div>
                </template>
                <div v-if="showBranch" class="grid gap-1">
                    <Label class="text-xs">Branch</Label>
                    <select v-model="form.branch_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                        <option value="">All branches</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <Button size="sm" @click="apply">Apply</Button>
            </div>

            <!-- Summary tiles -->
            <div v-if="result.summary.length" class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card v-for="(s, i) in result.summary" :key="i">
                    <CardContent class="p-4">
                        <p class="text-xs text-muted-foreground">{{ s.label }}</p>
                        <p class="text-lg font-bold">{{ fmt(s.value, s.format) }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Chart -->
            <Card v-if="hasChart">
                <CardContent class="p-4">
                    <BarChart :labels="chartLabels" :values="chartValues" />
                </CardContent>
            </Card>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th
                                v-for="c in result.columns"
                                :key="c.key"
                                class="px-4 py-3 font-medium"
                                :class="c.align === 'right' ? 'text-right' : ''"
                            >{{ c.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="result.rows.length === 0">
                            <td :colspan="result.columns.length" class="px-4 py-10 text-center text-muted-foreground">No data for the selected period.</td>
                        </tr>
                        <tr v-for="(row, ri) in result.rows" :key="ri" class="border-b last:border-0 hover:bg-muted/30">
                            <td
                                v-for="c in result.columns"
                                :key="c.key"
                                class="px-4 py-2.5"
                                :class="c.align === 'right' ? 'text-right tabular-nums' : ''"
                            >{{ fmt(row[c.key], c.format) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
