<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps<{
    summary: Record<string, number>;
    telecallers: {
        telecaller_id: number;
        name: string;
        assigned: number;
        contacted: number;
        calls: number;
        connected: number;
        interested: number;
        lost: number;
        conversion: number;
    }[];
    sources: { source: string; total: number; interested: number }[];
    outcomes: { call_outcome: string; total: number }[];
    range: { from: string; to: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Telecaller Report', href: '/admin/reports/telecaller' },
];

const range = reactive({ from: props.range.from, to: props.range.to });
watch(range, () => router.get('/admin/reports/telecaller', { from: range.from, to: range.to }, { preserveState: true, replace: true }));

const cards = [
    { key: 'total', label: 'Leads (period)', accent: 'text-brand-maroon dark:text-brand-yellow' },
    { key: 'new', label: 'New', accent: 'text-brand-orange' },
    { key: 'contacted', label: 'Contacted', accent: 'text-emerald-600' },
    { key: 'unattended', label: 'Unattended', accent: 'text-brand-red' },
    { key: 'interested', label: 'Interested', accent: 'text-emerald-600' },
    { key: 'lost', label: 'Lost', accent: 'text-brand-red' },
    { key: 'followups_due', label: 'Follow-ups Due', accent: 'text-brand-orange' },
    { key: 'overdue', label: 'Overdue', accent: 'text-brand-red' },
];
</script>

<template>
    <Head title="Telecaller Report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Telecaller Performance</h1>
                    <p class="text-sm text-muted-foreground">Lead handling, calls and conversion. Click a row to drill down.</p>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <Input v-model="range.from" type="date" class="h-9" />
                    <span class="text-muted-foreground">to</span>
                    <Input v-model="range.to" type="date" class="h-9" />
                </div>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card v-for="card in cards" :key="card.key">
                    <CardContent class="p-4">
                        <p class="text-xs text-muted-foreground">{{ card.label }}</p>
                        <p class="text-2xl font-bold tabular-nums" :class="card.accent">{{ summary[card.key] ?? 0 }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Telecaller table -->
            <Card>
                <CardHeader><CardTitle class="text-base">Telecaller Contribution</CardTitle></CardHeader>
                <CardContent class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="py-2 pr-3 font-medium">Telecaller</th>
                                <th class="py-2 pr-3 font-medium">Assigned</th>
                                <th class="py-2 pr-3 font-medium">Contacted</th>
                                <th class="py-2 pr-3 font-medium">Calls</th>
                                <th class="py-2 pr-3 font-medium">Connected</th>
                                <th class="py-2 pr-3 font-medium">Interested</th>
                                <th class="py-2 pr-3 font-medium">Lost</th>
                                <th class="py-2 pr-3 font-medium">Conv.%</th>
                                <th class="py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in telecallers" :key="t.telecaller_id" class="border-b last:border-0">
                                <td class="py-2 pr-3 font-medium">{{ t.name }}</td>
                                <td class="py-2 pr-3">{{ t.assigned }}</td>
                                <td class="py-2 pr-3">{{ t.contacted }}</td>
                                <td class="py-2 pr-3">{{ t.calls }}</td>
                                <td class="py-2 pr-3">{{ t.connected }}</td>
                                <td class="py-2 pr-3 font-medium text-emerald-600">{{ t.interested }}</td>
                                <td class="py-2 pr-3 text-brand-red">{{ t.lost }}</td>
                                <td class="py-2 pr-3">{{ t.conversion }}%</td>
                                <td class="py-2 text-right">
                                    <Link
                                        :href="`/admin/sales-leads?telecaller_id=${t.telecaller_id}`"
                                        class="text-xs text-brand-maroon underline dark:text-brand-yellow"
                                        >View leads →</Link
                                    >
                                </td>
                            </tr>
                            <tr v-if="!telecallers.length">
                                <td colspan="9" class="py-6 text-center text-muted-foreground">No telecaller activity in this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Source performance -->
                <Card>
                    <CardHeader><CardTitle class="text-base">Lead Source Performance</CardTitle></CardHeader>
                    <CardContent>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-muted-foreground">
                                    <th class="py-2 pr-3 font-medium">Source</th>
                                    <th class="py-2 pr-3 font-medium">Leads</th>
                                    <th class="py-2 pr-3 font-medium">Interested</th>
                                    <th class="py-2 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in sources" :key="s.source" class="border-b last:border-0">
                                    <td class="py-2 pr-3 capitalize">{{ (s.source ?? '').replace(/_/g, ' ') }}</td>
                                    <td class="py-2 pr-3">{{ s.total }}</td>
                                    <td class="py-2 pr-3 text-emerald-600">{{ s.interested }}</td>
                                    <td class="py-2 text-right">
                                        <Link :href="`/admin/sales-leads?search=&status=`" class="text-xs text-muted-foreground">—</Link>
                                    </td>
                                </tr>
                                <tr v-if="!sources.length">
                                    <td colspan="4" class="py-4 text-center text-muted-foreground">No data.</td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <!-- Outcome distribution -->
                <Card>
                    <CardHeader><CardTitle class="text-base">Call Outcome Distribution</CardTitle></CardHeader>
                    <CardContent>
                        <ul class="space-y-2 text-sm">
                            <li v-for="o in outcomes" :key="o.call_outcome" class="flex items-center justify-between">
                                <span class="capitalize">{{ (o.call_outcome ?? '').replace(/_/g, ' ') }}</span>
                                <span class="font-medium">{{ o.total }}</span>
                            </li>
                            <li v-if="!outcomes.length" class="py-4 text-center text-muted-foreground">No calls logged.</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
